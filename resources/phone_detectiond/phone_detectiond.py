#!/usr/bin/env python3
'''
phone_detectiond daemon
created by Sebastien FERRAND
sebastien.ferrand@vbmaf.net
04/11/2019

2021/05/25: Benoit Rech 
support multi-antennas. Each antenna sends the presence information
to plugin phone_dectection that runs on jeedom. Jeedom consolidates the information from the 
different antennas to build the "global" device status.

2022/01/03: Benoit Rech
Use pybluez instead of system calls which can cause locks on raspberry
Install hcidump to be able to monitor hci frame directly on the antenna.

2023/12/13: Benoit Rech
Do not use system calls, or pyBluez because there are lots of issues on Debian 11 (bullseye).
Rely on a class (aiobtname.py) developped by François Wautier, which uses direct HCI socket 
with the system, and asynchonous calls that avoid using multi-threads. 
A request is sent for each mobile, and the mobile's reponses are parsed on the fly.
The polling interval is also more accurate, as well as the monitoring of the 'unreachable' threshold.
'''

import logging
import os
import sys
import time
import signal
import json
import argparse
import subprocess
import socketserver
import requests
import threading
import collections
import gc
import re
import random
import asyncio as aio
import aiobtname
from math import gcd
from functools import partial


from datetime import date, datetime, timedelta

BASE_PATH = os.path.join(os.path.dirname(__file__), '..', '..', '..', '..')
BASE_PATH = os.path.abspath(BASE_PATH)
PLUGIN_NAME = "phone_detection"
DATEFORMAT = '%Y-%m-%d %H:%M:%S'
LOGLEVEL = logging.WARNING;
PAGE_TIMEOUT=2500

DEVICES = {}

"""
Classe permettant de regrouper les informations d'un téléphone
"""
class Phone:
    def __init__(self, macAddress, deviceId):
        self.macAddress = macAddress.upper()
        self.deviceId = deviceId
        self.humanName = ''
        self.isReachable = False
        self.isReachableLastPolling = False
        self.lastStateDate = datetime.utcnow()
        self.lastRefreshDate = datetime.utcnow()
        self.lastPollDate = datetime.utcnow() - timedelta(hours=1)
        self.mustUpdate = False

    def setReachable(self):
        self.lastStateDate = datetime.utcnow()
        self.isReachableLastPolling = True
        if not self.isReachable:
            self.isReachable = True
            logging.info('[{}] Set "{}" phone present [{}]'.format(self.deviceId, self.humanName, self.macAddress))
            self.mustUpdate = True
            return True

        self.mustUpdate = False
        return False

    def setNotReachable(self):
        thresholdDate = self.lastStateDate + timedelta(seconds=int(args.absentThreshold))
        logging.debug('[{}]: lastStateDate: {}'.format(self.deviceId, self.lastStateDate))
        logging.debug('[{}]: thresholdDate: {}'.format(self.deviceId, thresholdDate))
        logging.debug('[{}]: datetime.utcnow(): {}'.format(self.deviceId, datetime.utcnow()))
        logging.debug('[{}]: isReachableLastPolling: {}'.format(self.deviceId, self.isReachableLastPolling))
        logging.debug('[{}]: isReachable: {}, is datetime.utcnow() > thresholdDate ? {}'.format(self.deviceId, self.isReachable, datetime.utcnow() > thresholdDate))
        self.isReachableLastPolling = False
        if self.isReachable and datetime.utcnow() > thresholdDate:
            self.isReachable = False
            self.lastStateDate = datetime.utcnow()
            logging.info('[{}] Set "{}" phone absent [{}]'.format(self.deviceId, self.humanName, self.macAddress))
            self.mustUpdate = True
            return True

        self.mustUpdate = False
        return False

    def toJson(self):
        r = {
            'macAddress': self.macAddress,
            'deviceId': self.deviceId,
            'isReachable': self.isReachable,
            'lastStateDate': self.lastStateDate.isoformat(),
            'humanName' : self.humanName
        }
        return r

    @staticmethod
    def fromJson(macAddress, deviceId, humanName, isReachable=False, lastStateDate=datetime.utcnow()):
        obj = Phone(macAddress, deviceId)
        obj.isReachable = isReachable
        obj.lastStateDate = lastStateDate
        obj.humanName = humanName
        return obj

"""
Class gérant le thread de détection pour l'ensemble des telephones
"""
class PhonesDetection:
    def __init__(self, btController, absentInterval, presentInterval, callback):
        self.btController = btController
        self.absentInterval = absentInterval
        self.presentInterval = presentInterval
        self._stop = False
        self.callback = callback
        self.macList = []
        self.nbConnectionFailure = 0

    def start(self):

        for device in DEVICES.values():
            logging.info('[{}] Starting monitoring for {} [{}]'.format(device.deviceId, device.humanName, device.macAddress))
            deviceStatus = self.callback.getDeviceStatus(device.deviceId)
            logging.debug('Jeedom {} device status: {}'.format(device.deviceId, deviceStatus))
            if deviceStatus == True:
                device.setReachable()
            else:
                device.setNotReachable()

        self._stop = False
        self.t = threading.Thread(target=self.__run)
        self.t.daemon = True
        self.t.start()

    def stop(self, waitForStop = True):
        for device in DEVICES.values():
            logging.info('[{}] Stop monitoring for {} [{}]'.format(device.deviceId, device.humanName, device.macAddress))

        self._stop = True
        if waitForStop:
            self.t.join()
            del self.t
            gc.collect()

    def isMonitoringAlive(self):
        return int(self.t.is_alive())

    def isValidMacAddress(sef, mac):
        allowed = re.compile(r"""
                         (
                             ^([0-9A-F]{2}[-]){5}([0-9A-F]{2})$
                            |^([0-9A-F]{2}[:]){5}([0-9A-F]{2})$
                         )
                         """,
                         re.VERBOSE|re.IGNORECASE)

        return re.search(allowed, mac)

    def isPollingRequested(self, phone):
        if phone.isReachableLastPolling:
            nextPollDate = phone.lastPollDate + timedelta(seconds=self.presentInterval)
        else:
            nextPollDate = phone.lastPollDate + timedelta(seconds=self.absentInterval)

        return (datetime.utcnow() >= nextPollDate)

    def processResponse(self, data):
        logging.debug('Received response from device: {}: {}'.format(data['mac'], data['name']))
        mac = data['mac'].upper()
        if mac in self.macList:
            self.macList.remove(mac)
        for device in DEVICES.values():
            if device.macAddress == mac:
                logging.debug('[{}] {} ({}) is reachable'.format(device.deviceId, device.macAddress, device.humanName))            
                if device.setReachable() == True:
                    if self.callback.setDeviceStatus(device.deviceId, device.isReachable):
                        device.mustUpdate = False
                break

    def processTimeout(self, data):
        logging.debug('Received timeout for device: {}'.format(data['mac']))
        mac = data['mac'].upper()
        if mac in self.macList:
            self.macList.remove(mac)
        for device in DEVICES.values():
            if device.macAddress == mac:
                logging.debug('[{}] {} ({}) is unreachable'.format(device.deviceId, device.macAddress, device.humanName))            
                if device.setNotReachable() == True:
                    if self.callback.setDeviceStatus(device.deviceId, device.isReachable):
                        device.mustUpdate = False
                break        

    async def GetPhonesInformation(self):

        try:
            #First create and configure a raw socket
            sock = aiobtname.create_bt_socket(int(self.btController))
            self.nbConnectionFailure = 0
        except Exception as e:
            logging.error('Impossible de se connecter au bluetooth hci{}, exception: {}: {}'.format(self.btController, type(e), e))
            self.nbConnectionFailure = self.nbConnectionFailure + 1
            if self.nbConnectionFailure > 5:
                self.stop(False)
            return

        conn = None
        self.macList = []
        for device in DEVICES.values():
            if self.isValidMacAddress(device.macAddress) and self.isPollingRequested(device):
                logging.debug('Adding [{}]: {} ({})'.format(device.deviceId, device.macAddress, device.humanName))
                self.macList.append(device.macAddress)
                device.lastPollDate = datetime.utcnow()

        logging.debug('Number of devices to poll: {}'.format(len(self.macList)))
        if len(self.macList) != 0:
            # Randomize the order of the requests in the list.
            random.shuffle(self.macList)
            try:
                #create a connection with the raw socket
                event_loop = aio.get_event_loop()
                fac = event_loop._create_connection_transport(sock, aiobtname.BTNameRequester, None, None)
                conn, btctrl = await event_loop.create_task(fac)
                btctrl.processResponse = self.processResponse
                btctrl.processTimeout = self.processTimeout

                timetowait = 5.000  # 5 seconds, pagetimeout is 2500 slots (1562.50 ms)
                retries = 2
                request = partial(btctrl.request, self.macList)
                await aio.sleep(0.1)
                for i in range(retries):
                    if len(self.macList) != 0:
                        logging.debug('Sending bluetooth name request to {} devices (try {}/{})'.format(len(self.macList), i + 1, retries))
                        request()
                        # Time for the devices to reply or to get timeout
                        await aio.sleep(timetowait)

            except Exception as e:
                logging.info('Exception: {}/{}'.format(type(e), e))
                logging.error('Erreur {} durant le monitoring des devices'.format(e))

            finally:
                if conn is not None:
                    conn.close()

            # list of mac address that are in unknown state (no Timeout, no answer)
            # force Polling at next round.
            await aio.sleep(1)
            for mac in self.macList:
                logging.warning('No response for mac {}'.format(mac))
                for device in DEVICES.values():
                    if mac == device.macAddress:
                        device.isReachableLastPolling = False


    def __run(self):
        sleepTime = gcd(self.absentInterval, self.presentInterval)
        logging.debug('sleeptime: {} GCD({}, {})'.format(sleepTime, self.absentInterval, self.presentInterval))
        while not self._stop:
            startTime = time.time()
            event_loop = aio.new_event_loop()
            try:
                coro = self.GetPhonesInformation()
                event_loop.run_until_complete(coro)
                # Process with periodic refresh
                for device in DEVICES.values():
                    refreshDate = device.lastRefreshDate + timedelta(seconds=300)
                    if datetime.utcnow() > refreshDate:
                        logging.debug('{}: periodic refresh (300s) --> status: {}'.format(device.humanName, device.isReachable))
                        device.lastRefreshDate = datetime.utcnow()
                        self.callback.setDeviceStatus(device.deviceId, device.isReachable)
            except Exception as e:
                logging.error('Unknow exception {} while monitoring mobiles'.format(e))

            finally:
                event_loop.close()
                endTime = time.time()

            elapsedTime = endTime - startTime
            remainingTime = max(0, sleepTime - elapsedTime)
            logging.debug('elapsed time {}, next loop in {} seconds'.format(elapsedTime, remainingTime))
            time.sleep(remainingTime)

"""
Convertisseur Phone <-> json
"""
class PhoneEncoder(json.JSONEncoder):
    def default(self, obj):  # pylint: disable=E0202
        if isinstance(obj, Phone):
            return obj.toJson()
        if obj is None:
            return ""
        # if isinstance(obj, Response):
        #     return obj.cleaned_data()

        return json.JSONEncoder.default(self, obj)

"""
Permet d'interroger Jeedom à partir du démon
"""
class JeedomCallback:
    def __init__(self, apikey, url, daemonname):
        logging.info('Create {} daemon'.format(PLUGIN_NAME))
        self.apikey = apikey
        self.url = url
        self.daemonname = daemonname;
        self.messages = []

    def __request(self, m):
        response = None
        m['source'] = self.daemonname;
        for i in range (0,3):
            logging.debug('Send to jeedom :  {}'.format(m))
            r = requests.post('{}?apikey={}'.format(self.url, self.apikey), data=json.dumps(m), verify=False)
            logging.debug('Status Code :  {}'.format(r.status_code))
            if r.status_code != 200:
                logging.error('Error on send request to jeedom, return code {} - {}'.format(r.status_code, r.reason))
                time.sleep(0.150)
            else:
                response = r.json()
                logging.debug('Jeedom reply :  {}'.format(response))
                break
        return response

    def send(self, message):
        self.messages.append(message)

    def __send_now(self, message):
        return self.__request(message)

    def test(self):
        logging.debug('Send to test connection to jeedom')
        r = self.__send_now({'action': 'test'})
        if not r or not r.get('success'):
            logging.error('Calling jeedom failed')
            return False
        return True

    def heartbeat(self, isMonitoringAlive, version):
        r = self.__send_now({'action':'heartbeat', 'version': version, 'alive': isMonitoringAlive})
        #r = self.__send_now({'action':'heartbeat', 'version': version, 'alive': 1, 'monitor': isMonitoringAlive})
        if not r or not r.get('success'):
            logging.error('Error during heartbeat')
            return False
        return True

    def getDeviceStatus(self, deviceId):
        r = self.__send_now({'action':'get_status', 'id': deviceId })
        if not r or not r.get('success'):
            logging.error('Error calling getDeviceStatus')
            return False
        return r['value'] == 1

    def setDeviceStatus(self, deviceId, status):
        logging.debug('[{}]: device status: {}'.format(deviceId, status))

        r = self.__send_now({'action': 'update_device_status', 'id' : deviceId, 'value': (0,1)[status]})
        if not r or not r.get('success'):
            logging.error('Error during update status')
            return False
        return True

    def updateGlobalDevice(self):
        r = self.__send_now({'action': 'refresh_group'})
        if not r or not r.get('success'):
            logging.error('Error during updateGlobalDevice')
            return False
        return True

    def getDevices(self):
        logging.info('Get devices from Jeedom')
        devices = self.__send_now({'action':'get_devices'})
        if not devices or not devices.get('success'):
            logging.error('FAILED')
            return None
        # values = json.loads(devices)
        r = {}
        for key in devices['value']:
            item = devices['value'][key]
            r[key] = Phone(item['macAddress'], item['id'])
            r[key].humanName = item['name']
            r[key].isReachable = item['state']
            r[key].isReachableLastPolling = False
            try:
                r[key].lastStateDate = datetime.strptime(item['lastValueDate'], DATEFORMAT)
            except:
                r[key].lastStateDate = datetime.utcnow()
        return r

"""
Intercepte les demandes de Jeedom : update_device, insert_device et remove_device
"""
class JeedomHandler(socketserver.BaseRequestHandler):

    def handle(self):
        # self.request is the TCP socket connected to the client
        self.data = self.request.recv(1024)
        logging.debug('Message received in socket, length: {}'.format(len(self.data)))
        message = json.loads(self.data.decode())
        logging.debug(message)

        response = {'result': None, 'success': True}
        stop = False
        if message['apikey'] != _apikey:
            logging.error("Invalid apikey from socket : {}".format(self.data))
            return
        del message['apikey']

        action = message['action']
        args = message['args']

        if action == 'update_device' or action == 'insert_device':
            mid = args[0]
            name = args[1]
            macAddress = args[2]

            if mid in DEVICES:
                # update
                logging.debug('Update device in device: {}'.format(mid))
                DEVICES[mid].humanName = name
                DEVICES[mid].deviceId = int(mid)
                DEVICES[mid].macAddress = macAddress
                response['result'] = 'Update OK'
            else:
                # insert
                logging.debug('Add new device in device: {}'.format(mid))
                DEVICES[mid] = Phone(macAddress, mid)
                DEVICES[mid].humanName = name
                response['result'] = 'Insert OK'

        if action == 'remove_device':
            mid = args[0]
            if mid in DEVICES:
                del DEVICES[mid]
            response['result'] = 'Remove OK'

        if action == 'logdebug':
            logging.debug('Dynamically change log to debug')
            log = logging.getLogger()
            for hdlr in log.handlers[:]:
               log.removeHandler(hdlr)
               logging.basicConfig(level=logging.DEBUG,
                                   format=FORMAT, datefmt="%Y-%m-%d %H:%M:%S")
            response['result'] = 'logdebug OK'
            logging.debug('logging level is now DEBUG')

        if action == 'lognormal':
            logging.debug('Dynamically restore the default log level')
            log = logging.getLogger()
            for hdlr in log.handlers[:]:
               log.removeHandler(hdlr)
               logging.basicConfig(level=LOGLEVEL,
                                   format=FORMAT, datefmt="%Y-%m-%d %H:%M:%S")
            response['result'] = 'lognormal OK'

        if action == 'stop':
            logging.debug('Receive stop request from jeedom')
            stop = True

        self.request.sendall(json.dumps(response, cls=PhoneEncoder).encode())

        if stop == True:
            os.kill(os.getpid(),signal.SIGTERM)



"""
Class gérant le threadle heartbeat
"""
class HeartbeatThread:
    def __init__(self, jeedomCallback, monitoringCallback, version):
        self._stop = False
        self.jeedomCallback = jeedomCallback
        self.monitoringCallback = monitoringCallback
        self.version = version

    def start(self):
        logging.info('Start heartbeat thread')
        self._stop = False
        self.t = threading.Thread(target=self.__run)
        self.t.daemon = True
        self.t.start()

    def stop(self, waitForStop = True):
        logging.info('Stop heartbeat thread')
        self._stop = True
        if waitForStop:
            self.t.join()
            del self.t
            gc.collect()

    def __run(self):
        sleepTime = 30
        while not self._stop:
            isMonitoringAlive = self.monitoringCallback.isMonitoringAlive()
            self.jeedomCallback.heartbeat(isMonitoringAlive, self.version)
            time.sleep(sleepTime)


"""
Converti le loglevel envoyer par jeedom
"""
def convert_log_level(level='error'):
    LEVELS = {'debug': logging.DEBUG,
              'info': logging.INFO,
              'notice': logging.WARNING,
              'warning': logging.WARNING,
              'error': logging.ERROR,
              'critical': logging.CRITICAL,
              'none': logging.NOTSET,
              'default': logging.INFO }
    return LEVELS.get(level, logging.NOTSET)

def handler(signum=None, frame=None):
    logging.debug("Signal %i caught, exiting..." % int(signum))
    print("Signal %i caught, exiting..." % int(signum))
    shutdown()

"""
shutdown: nettoie les ressources avant de quitter
"""
def shutdown():
    logging.info("=========== Shutdown ===========")
    logging.info("Stopping monitoring and heartbeat threads")
    monitoringThread.stop(False)
    heartbeatThread.stop(False)
    logging.info("Shutting down local server")
    server.shutdown()
    server.server_close()
    if (_sockfile != None and len(str(_sockfile)) > 0):
        logging.info("Removing Socket file " + str(_sockfile))
        if os.path.exists(_sockfile):
            os.remove(_sockfile)
    logging.info("Removing PID file " + str(_pidfile))
    if os.path.exists(_pidfile):
        os.remove(_pidfile)
    logging.info("Exit 0")
    logging.info("=================================")


def setBluetoothPageTimeout(interface, timeout):

    if subprocess.call('sudo hciconfig {} pageto {}'.format(args.device, timeout), shell=True) != 0:
        logging.error('Unable to set PageTimeout to {} for controller hci{}'.format(interface, timeout))
        return -1

    logging.info('PageTimeout set to {}s for controller hci{}.'.format(timeout * 0.000625, interface))
    return 0

### Init & Start
parser = argparse.ArgumentParser()
parser.add_argument('--loglevel', help='LOG Level', default='warning')
parser.add_argument('--socket', help='Daemon socket', default='')
parser.add_argument('--sockethost', help='Daemon socket host', default='')
parser.add_argument('--socketport', help='Daemon socket port', default='0')
parser.add_argument('--pidfile', help='PID File', default='/tmp/{}d.pid'.format(PLUGIN_NAME))
parser.add_argument('--apikey', help='API Key', default='nokey')
parser.add_argument('--device', help='{} port'.format(PLUGIN_NAME), default='hci0')
parser.add_argument('--callback', help='Jeedom callback', default='http://localhost')
parser.add_argument('--daemonname', help='Name of the antenna', default='local')
parser.add_argument('--interval', help='Presence checking interval when phone is absent', default=10)
parser.add_argument('--present_interval', help='Presence checking interval when phone is present', default=30)
parser.add_argument('--absentThreshold', help='Time to consider a device absent', default=180)
args = parser.parse_args()

FORMAT = '[%(asctime)-15s][%(levelname)s][%(name)s](%(threadName)s) : %(message)s'
LOGLEVEL = convert_log_level(args.loglevel);
logging.basicConfig(level=LOGLEVEL,
                    format=FORMAT, datefmt="%Y-%m-%d %H:%M:%S")
urllib3_logger = logging.getLogger('urllib3')
urllib3_logger.setLevel(logging.CRITICAL)

# Recupere la version du plugin
try:
    with open(os.path.dirname(__file__) + '/version.txt', 'r') as fp:
        version = fp.read()
        version = version.rstrip('\r\n')
        fp.close()
except FileNotFoundError:
    version = '?.?.?'

logging.info('=========')
logging.info('Start {}d'.format(PLUGIN_NAME))
logging.info('Version: {}'.format(version))
logging.info('Log level : {}'.format(args.loglevel))
logging.info('Socket : {}'.format(args.socket))
logging.info('SocketHost : {}'.format(args.sockethost))
logging.info('SocketPort : {}'.format(args.socketport))
logging.info('PID file : {}'.format(args.pidfile))
logging.info('Device : {}'.format(args.device))
logging.info('Callback : {}'.format(args.callback))
logging.info('Daemon Name : {}'.format(args.daemonname))
logging.info('Polling Interval when device is Absent : {}'.format(args.interval))
logging.info('Polling Interval when device is Present : {}'.format(args.present_interval))
logging.info('Threshold to consider device Absent: {}'.format(args.absentThreshold))
logging.info('Python version : {}'.format(sys.version))

_pidfile = args.pidfile
_sockfile = args.socket
_apikey = args.apikey

btController = args.device[3:]
logging.info('Using bluetooth controller {} (id={})'.format(args.device, btController))
if setBluetoothPageTimeout(int(btController), PAGE_TIMEOUT) == -1:
	sys.exit(1)

absentInterval = int(args.interval)
presentInterval = int(args.present_interval)

# Configuration du handler pour intercepter les commandes
# kill -9 et kill -15
signal.signal(signal.SIGINT, handler)
signal.signal(signal.SIGTERM, handler)

# Ecrit le PID du démon dans un fichier
pid = str(os.getpid())
logging.debug("Writing PID " + pid + " to " + str(args.pidfile))
with open(args.pidfile, 'w') as fp:
    fp.write("%s\n" % pid)
    fp.close()

# Configure et test le callback vers jeedom
jc = JeedomCallback(args.apikey, args.callback, args.daemonname)
if not jc.test():
    sys.exit(1)

# Démarre le serveur qui écoute les requests de jeedom
if args.socket != None and len(args.socket) > 0:
    logging.info('Use Unix socket for Jeedom -> daemon communication')
    if os.path.exists(args.socket):
        os.unlink(args.socket)
    server = socketserver.UnixStreamServer(args.socket, JeedomHandler)
else:
    try:
        logging.info('Use TCP socket for Jeedom -> daemon communication')
        socketserver.TCPServer.allow_reuse_address = True
        server = socketserver.TCPServer((args.sockethost, int(args.socketport)), JeedomHandler)
    except OSError as e:
        if e.errno == 98:
            logging.info('TCP socket in use, wait 5 seconds and retry')
            time.sleep(5)
            socketserver.TCPServer.allow_reuse_address = True
            try:
                server = socketserver.TCPServer((args.sockethost, int(args.socketport)), JeedomHandler)
            except:
                logging.error('Unable to create TCP socket for Jeedom to daemon communication. Exiting')
                sys.exit(1)


handlerThread = threading.Thread(target=server.serve_forever)
handlerThread.start()

# Récupération des devices dans Jeedom
DEVICES = jc.getDevices()

jc.updateGlobalDevice()

# Démarrage du thread de monitoring des mobiles
monitoringThread = PhonesDetection(btController, absentInterval, presentInterval, jc)
monitoringThread.start()

# Demarrage des heartbeat vers jeedom
heartbeatThread = HeartbeatThread(jc, monitoringThread, version)
heartbeatThread.start()
