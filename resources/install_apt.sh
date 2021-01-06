#!/bin/bash
PROGRESS_FILE=/tmp/jeedom/phone_detection/dependance
if [ ! -z $1 ]; then
	PROGRESS_FILE=$1
fi
touch ${PROGRESS_FILE}
echo 0 > ${PROGRESS_FILE}
echo "Installation des dépendances"
sudo apt-get update >> ${PROGRESS_FILE}
echo 10 >> ${PROGRESS_FILE}
sudo apt-get install -y python3 >> ${PROGRESS_FILE}
echo 30 >> ${PROGRESS_FILE}
sudo apt-get install -y python3-pip >> ${PROGRESS_FILE}
echo 40 >> ${PROGRESS_FILE}
sudo apt-get install -y python3-requests >> ${PROGRESS_FILE}
echo 50 >> ${PROGRESS_FILE}
sudo apt-get install -y python3-setuptools >> ${PROGRESS_FILE}
echo 60 >> ${PROGRESS_FILE}
sudo apt-get install -y python3-rpi.gpio >> ${PROGRESS_FILE}
echo 70 >> ${PROGRESS_FILE}
sudo apt-get install -y python3-dev >> ${PROGRESS_FILE}
echo 80 >> ${PROGRESS_FILE}
BASEDIR=$(dirname "$0")
sudo apt-get install -y bluez >> ${PROGRESS_FILE}
echo 100 >> ${PROGRESS_FILE}
sudo usermod -aG bluetooth www-data >> ${PROGRESS_FILE}
echo "Installation des dépendances terminé !" >> ${PROGRESS_FILE}
