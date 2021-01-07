#!/usr/bin/env python3
'''
phone_detectiond daemon
created by Sebastien FERRAND
sebastien.ferrand@vbmaf.net
04/11/2019
'''
import logging
import os
import sys
import time
import signal
import json
import argparse
import socketserver
import requests
import threading
import uuid
import subprocess
import collections
import gc

from multiprocessing.dummy import Pool as ThreadPool

from datetime import date, datetime, timedelta

BASE_PATH = os.path.join(os.path.dirname(__file__), '..', '..', '..', '..')
BASE_PATH = os.path.abspath(BASE_PATH)
PLUGIN_NAME = "phone_detection"
DEVICES = {}
THREADS = {}
DATEFORMAT = '%Y-%m-%d %H:%M:%S'
LOGLEVEL = logging.WARNING;

"""
Classe permettant de regrouper les informations d'un téléphone
"""
class Phone:
    def __init__(self, macAddress, deviceId):
        self.macAddress = macAddress
        self.deviceId = deviceId
        self.humanName = ''
        self.isReachable = False
        self.lastStateDate = datetime.utcnow()
        self.lastRefreshDate = datetime.utcnow()
        self.mustUpdate = False

    def setReachable(self):
        self.lastStateDate = datetime.utcnow()
        if not self.isReachable:
            self.isReachable = True
            logging.info('Set {}\'s phone present'.format(self.humanName))
            self.mustUpdate = True
            return True

        self.mustUpdate = False
        return False

    def setNotReachable(self):
        thresholdDate = self.lastStateDate + timedelta(seconds=int(args.absentThreshold))
        logging.debug('lastStateDate: {}'.format(self.lastStateDate))
        logging.debug('thresholdDate: {}'.format(thresholdDate))
        logging.debug('datetime.utcnow(): {}'.format(datetime.utcnow()))
        logging.debug('is datetime.utcnow() > thresholdDate ? {}'.format(datetime.utcnow() > thresholdDate))
        if self.isReachable and datetime.utcnow() > thresholdDate:
            self.isReachable = False
            self.lastStateDate = datetime.utcnow()
            logging.info('Set "{}" phone absent'.format(self.humanName))
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
Class gérant le thread de détection pour 1 device
"""
class PhoneDetection:
    def __init__(self, device, btController, interval, present_interval, callback):
        self.device = device
        self.btController = btController
        self.interval = interval
        self.present_interval = present_interval
        self._stop = False
        self.callback = callback

    def start(self):
        logging.info('Start thread detection for {} [{}]'.format(self.device.humanName, self.device.macAddress))

        deviceStatus = self.callback.getDeviceStatus(int(self.device.deviceId))
        logging.debug('Jeedom {} device status: {}'.format(self.device.deviceId, deviceStatus))
        self.device.isReachable = deviceStatus
        self._stop = False
        self.t = threading.Thread(target=self.__run)
        self.t.daemon = True
        self.t.start()
    
    def stop(self, waitForStop = True):
        logging.info('Stop thread detection for {} [{}]'.format(self.device.humanName, self.device.macAddress))
        self._stop = True
        if waitForStop:
            self.t.join()
            del self.t 
            gc.collect()
    
    def GetPhoneInformation(self):
        logging.debug('Get phone information {}'.format(self.device.deviceId))
        result = subprocess.run(['sudo', 'hcitool', '-i', self.btController, 'name', self.device.macAddress], stdout = subprocess.PIPE)
        if result.stdout:
            logging.debug('{} is present'.format(self.device.deviceId))
            self.device.setReachable()
        else:
            logging.debug('{} is absent'.format(self.device.deviceId))
            self.device.setNotReachable()
        
        logging.debug('{} {}'.format(self.device.deviceId, ("is up to date", "must be update")[self.device.mustUpdate]))

    def __run(self):
        sleepTime = self.interval
        while not self._stop:
            self.GetPhoneInformation()
            if self.device.mustUpdate:
                logging.debug('{} status has changed to \'{}\'! Notify Jeedom.'.format(self.device.humanName, ('absent','present')[self.device.isReachable]))
                if self.callback.setDeviceStatus(int(self.device.deviceId), self.device.isReachable):
                    self.device.lastStateDate = datetime.utcnow()
                    self.device.mustUpdate = False
            else:
                refreshDate = self.device.lastRefreshDate + timedelta(seconds=300)
                if datetime.utcnow() > refreshDate:
                    logging.debug('{} forced refresh !'.format(int(self.device.deviceId)))
                    self.device.lastRefreshDate = datetime.utcnow()
                    self.callback.setDeviceStatus(int(self.device.deviceId), self.device.isReachable)

            if self.device.isReachable:
                sleepTime = self.present_interval
            else:
                sleepTime = self.interval
            
            time.sleep(sleepTime)

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
    def __init__(self, apikey, url, daemonname): # , sleeptime, present_sleeptime, btController
        logging.info('Create {} daemon'.format(PLUGIN_NAME))
        self.apikey = apikey
        self.url = url
        self.daemonname = daemonname;
        # self.sleeptime = sleeptime
        # self.present_sleeptime = present_sleeptime
        # self.btController = btController
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
                sleep(0.150)
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

    def heartbeat(self):
        r = self.__send_now({'action':'heartbeat'})
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
        logging.debug('device status: {}'.format(status))

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
        for key in devices["value"]:
            item = devices["value"][key]
            r[key] = Phone(item["macAddress"], item["id"])
            r[key].humanName = item['name']
            r[key].isReachable = item["state"]
            try:
                #r[key].lastStateDate = datetime.fromisoformat(item['lastValueDate'])
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
        logging.debug("Message received in socket")
        message = json.loads(self.data.decode())
        lmessage = dict(message)
        del lmessage['apikey']
        logging.debug(lmessage)
        response = {'result': None, 'success': True}
        stop = False
        if message.get('apikey') != _apikey:
            logging.error("Invalid apikey from socket : {}".format(self.data))
            return

        action = message.get('action')
        args = message.get('args')

        if action == 'update_device' or action == 'insert_device':
            mid = args[0]
            name = args[1]
            macAddress = args[2]

            if mid in DEVICES:
                # update
                logging.debug('Update device in device.json')
                DEVICES[mid].humanName = name
                DEVICES[mid].deviceId = mid
                DEVICES[mid].macAddress = macAddress
                response['result'] = 'Update OK'
            else:
                # insert
                logging.debug('Add new device in device.json')
                DEVICES[mid] = Phone(macAddress, mid)
                DEVICES[mid].humanName = name
                THREADS[mid] = PhoneDetection(DEVICES[mid], BTCONTROLLER, INTERVAL, PRESENTINTERVAL, jc)
                response['result'] = 'Insert OK'
                THREADS[mid].start()
        
        if action == 'remove_device':
            mid = args[0]
            if mid in DEVICES:
                del DEVICES[mid]
                if mid in THREADS:
                    THREADS[mid].stop(False)
                    del THREADS[mid]
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
    def __init__(self, callback):
        self._stop = False
        self.callback = callback

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
            self.callback.heartbeat()
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
    logging.info("Stopping all threads")
    for key in THREADS:
        logging.info("\t==> {}".format(THREADS[key].device.humanName))
        THREADS[key].stop(False)
    HEARTBEAT.stop(False)
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

logging.info('Start {}d'.format(PLUGIN_NAME))
logging.info('Log level : {}'.format(args.loglevel))
logging.info('Socket : {}'.format(args.socket))
logging.info('SocketHost : {}'.format(args.sockethost))
logging.info('SocketPort : {}'.format(args.socketport))
logging.info('PID file : {}'.format(args.pidfile))
logging.info('Device : {}'.format(args.device))
logging.info('Callback : {}'.format(args.callback))
logging.info('Daemon Name : {}'.format(args.daemonname))
logging.info('Interval : {}'.format(args.interval))
logging.info('Present Interval : {}'.format(args.present_interval))
logging.info('AbsentThreshold: {}'.format(args.absentThreshold))
logging.info('Python version : {}'.format(sys.version))

_pidfile = args.pidfile
_sockfile = args.socket
_apikey = args.apikey

BTCONTROLLER = args.device
INTERVAL = int(args.interval)
PRESENTINTERVAL = int(args.present_interval)

# Configuration du handler pour intercepter les commandes
# kill -9 et kill -15
signal.signal(signal.SIGINT, handler)
signal.signal(signal.SIGTERM, handler)

# Ecrit le PID du démon dans un fichier
pid = str(os.getpid())
logging.debug("Writing PID " + pid + " to " + str(args.pidfile))
with open(args.pidfile, 'w') as fp:
    fp.write("%s\n" % pid)

# Configure et test le callback vers jeedom
jc = JeedomCallback(args.apikey, args.callback, args.daemonname) # , int(args.interval), int(args.present_interval), args.device
if not jc.test():
    sys.exit()

# Demarrage des heartbeat vers jeedom
HEARTBEAT = HeartbeatThread(jc)
HEARTBEAT.start()

# Démarre le serveur qui écoute les requests de jeedom
if args.socket != None and len(args.socket) > 0:
    logging.info('Use Unix socket for Jeedom -> daemon communication')
    if os.path.exists(args.socket):
        os.unlink(args.socket)
    server = socketserver.UnixStreamServer(args.socket, JeedomHandler)
else:
    logging.info('Use TCP socket for Jeedom -> daemon communication')
    socketserver.TCPServer.allow_reuse_address = True
    server = socketserver.TCPServer((args.sockethost, int(args.socketport)), JeedomHandler)

handlerThread = threading.Thread(target=server.serve_forever)
handlerThread.start()

# Récupération des devices dans Jeedom
DEVICES = jc.getDevices()

jc.updateGlobalDevice()

# Démarrage des threads
THREADS = {}

for key in DEVICES:
    THREADS[key] = PhoneDetection(DEVICES[key], args.device, INTERVAL, PRESENTINTERVAL, jc)
    THREADS[key].start()

