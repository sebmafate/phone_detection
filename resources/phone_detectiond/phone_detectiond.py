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

from datetime import date, datetime

BASE_PATH = os.path.join(os.path.dirname(__file__), '..', '..', '..', '..')
BASE_PATH = os.path.abspath(BASE_PATH)
PLUGIN_NAME = "phone_detection"
SAVE_LOCK = threading.Lock()

# DEVICES_FILENAME = os.path.join(BASE_PATH, "devices.json")
DEVICES_FILENAMENAME = "devices.json"

# store last 15 responses for terminal
LAST_RESPONSES = collections.deque([], 15)

DEVICES = {}


class Phone:
    def __init__(self, macAddress, deviceId):
        self.macAddress = macAddress
        self.deviceId = deviceId
        self.isReachable = False
        self.lastStateDate = datetime.now()

    def setReachable(self):
        if not self.isReachable:
            self.isReachable = True
            self.lastStateDate = datetime.now()

    def setNotReachable(self):
        if self.isReachable:
            self.isReachable = False
            self.lastStateDate = datetime.now()

    def toJson(self):
        r = {
            'macAddress': self.macAddress,
            'deviceId': self.deviceId,
            'isReachable': self.isReachable,
            'lastStateDate': self.lastStateDate.isoformat()
        }
        return r

    @staticmethod
    def fromJson(macAddress, deviceId, isReachable=False, lastStateDate=datetime.now()):
        obj = Phone(macAddress, deviceId)
        obj.isReachable = isReachable
        obj.lastStateDate = lastStateDate
        return obj


class PhoneEncoder(json.JSONEncoder):
    def default(self, obj):  # pylint: disable=E0202
        if isinstance(obj, Phone):
            return obj.toJson()
        return json.JSONEncoder.default(self, obj)


class JeedomCallback:
    def __init__(self, apikey, url, sleeptime):
        logging.debug('Create {} daemon'.format(PLUGIN_NAME))
        self.apikey = apikey
        self.url = url
        self.sleeptime = sleeptime
        self.messages = []
        self._stop = False
        self.t = threading.Thread(target=self.run)
        self.daemon = True
        self.t.start()

    def stop(self):
        self._stop = True

    def run(self):
        while not self._stop:
            devices = loadDevices()
            if devices is not None:
                for key in devices:
                    logging.debug('Ping {} [{}] phone'.format(key, devices[key].macAddress))
                    try:
                        result = subprocess.run(['sudo', 'hcitool', 'name', devices[key].macAddress], stdout=subprocess.PIPE)
                        logging.debug('Result: {}'.format(result.stdout))
                        if result.stdout:
                            devices[key].setReachable()
                        else:
                            devices[key].setNotReachable()

                    except Exception as error:
                        logging.error('Error on ping {} [{}] device: {}'.format(key, devices[key].macAddress, error))
                saveDevices(devices)
            time.sleep(self.sleeptime)

    def _request(self, m):
        response = None
        logging.debug('Send to jeedom :  {}'.format(m))
        r = requests.post('{}?apikey={}'.format(self.url, self.apikey),
                          data=json.dumps(m),
                          verify=False)
        if r.status_code != 200:
            logging.error(
                'Error on send request to jeedom, return code {} - {}'.format(r.status_code, r.reason))

        else:
            response = r.json()
            logging.debug('Jeedom reply :  {}'.format(response))
        return response

    def send(self, message):
        self.messages.append(message)

    def send_now(self, message):
        return self._request(message)

    def test(self):
        logging.debug('Send to test to jeedom')
        r = self.send_now({'action': 'test'})
        if not r or not r.get('success'):
            logging.error('Calling jeedom failed')
            return False
        return True


def store_response(response):
    LAST_RESPONSES.append(str(response))


def convert_log_level(level='error'):
    LEVELS = {'debug': logging.DEBUG,
              'info': logging.INFO,
              'notice': logging.WARNING,
              'warning': logging.WARNING,
              'error': logging.ERROR,
              'critical': logging.CRITICAL,
              'none': logging.NOTSET}
    return LEVELS.get(level, logging.NOTSET)


def handler(signum=None, frame=None):
    logging.debug("Signal %i caught, exiting..." % int(signum))
    print("Signal %i caught, exiting..." % int(signum))
    shutdown()


def shutdown():
    logging.debug("Shutdown")
    # logging.debug('Saving {} state'.format(PLUGIN_NAME))
    # z.save_state()
    # logging.debug('Closing {}'.format(PLUGIN_NAME))
    # z.close()
    logging.debug("Shutting down callback server")
    jc.stop()
    logging.debug("Shutting down local server")
    # server.shutdown()
    logging.debug("Removing PID file " + str(_pidfile))
    if os.path.exists(_pidfile):
        os.remove(_pidfile)
    logging.debug("Removing Socket file " + str(_sockfile))
    if os.path.exists(_sockfile):
        os.remove(_sockfile)
    logging.debug("Exit 0")


def saveDevices(devices):
    logging.debug("Save devices to file")

    # SAVE_LOCK.locked()
    with open(DEVICES_FILENAME, 'w') as fp:
        json.dump(devices, fp, cls=PhoneEncoder, sort_keys=True,
                  indent=4, separators=(',', ': '))
    # SAVE_LOCK.release()


def loadDevices():
    logging.debug("Load devices from file")
    # SAVE_LOCK.locked()
    r = None
    if os.path.exists(DEVICES_FILENAMENAME):
        with open(DEVICES_FILENAME, 'r') as fp:
            r = json.load(fp)
            for key, item in r.items():
                r[key] = Phone.fromJson(item['macAddress'], item['deviceId'],
                                        item['isReachable'], datetime.fromisoformat(item['lastStateDate']))
    # SAVE_LOCK.release()
    return r


### Init & Start
parser = argparse.ArgumentParser()
parser.add_argument('--loglevel', help='LOG Level', default='error')
parser.add_argument('--socket', help='Daemon socket', default='/tmp/jeedom/{}/{}d.sock'.format(PLUGIN_NAME, PLUGIN_NAME))
parser.add_argument('--pidfile', help='PID File', default='/tmp/jeedom/{}/{}d.pid'.format(PLUGIN_NAME, PLUGIN_NAME))
parser.add_argument('--apikey', help='API Key', default='nokey')
parser.add_argument('--device', help='{} port'.format(PLUGIN_NAME), default='hci0')
parser.add_argument('--callback', help='Jeedom callback', default='http://localhost')
parser.add_argument('--interval', help='Presence checking interval', default=10)
parser.add_argument('--absentThreshold', help='Time to consider a device absent', default=180)
args = parser.parse_args()

FORMAT = '[%(asctime)-15s][%(levelname)s][%(name)s](%(threadName)s) : %(message)s'

logging.basicConfig(level=convert_log_level(args.loglevel),
                    format=FORMAT, datefmt="%Y-%m-%d %H:%M:%S")

urllib3_logger = logging.getLogger('urllib3')
urllib3_logger.setLevel(logging.CRITICAL)

logging.info('Start {}d'.format(PLUGIN_NAME))
logging.info('Log level : {}'.format(args.loglevel))
logging.info('Socket : {}'.format(args.socket))
logging.info('PID file : {}'.format(args.pidfile))
logging.info('Apikey : {}'.format(args.apikey))
logging.info('Device : {}'.format(args.device))
logging.info('Callback : {}'.format(args.callback))
logging.info('Interval : {}'.format(args.interval))
logging.info('AbsentThreshold: {}'.format(args.absentThreshold))
logging.info('Python version : {}'.format(sys.version))

_pidfile = args.pidfile
_sockfile = args.socket
_apikey = args.apikey

signal.signal(signal.SIGINT, handler)
signal.signal(signal.SIGTERM, handler)


persistent_file = os.path.join(os.path.dirname(
    __file__), '{}.json'.format(PLUGIN_NAME))

pid = str(os.getpid())
logging.debug("Writing PID " + pid + " to " + str(args.pidfile))
with open(args.pidfile, 'w') as fp:
    fp.write("%s\n" % pid)

jc = JeedomCallback(args.apikey, args.callback, int(args.interval))
# if not jc.test():
#     sys.exit()
