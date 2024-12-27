# Plugin phone_detection pour Jeedom

## 1. Description

Ce plugin est développé pour fonctionner avec Jeedom.

Il permet de détecter la présence d'un ou plusieurs téléphones en utilisant l'adresse MAC de la puce Bluetooth.

## 2. Installation

Pour le moment, ce plugin n'est pas configuré dans le market Jeedom, j'attends qu'il soit stable avant de le publier. L'installation doit donc se faire manuellement.

### a. Installation via jeedom market

Jeedom permet d'installer facilement un plugin :

Pour cela dans le gestionnaire de plugins, cliquez sur le bouton "Market"
![Add plugins](images/add_plugin.png)

Dans la fenêtre qui s'ouvre, renseignez comme suit les informations :
![](images/add_plugin_github.jpg)

### b. Installation via le code source

Il est aussi possible d'installer en utilisant le code source. Attention, cette manipulation est réservée à un public plutôt averti, vous pouvez en effet rendre votre Jeedom instable.

- Dans une console (en ssh ou en local), rendez vous dans le répertoire /var/www/html/plugins
- Exécutez la commande suivante : `git clone https://github.com/sebmafate/phone_detection/ --branch develop`
- Voilà, c'est installé !

## 3. Configuration

- Lancez l'installation des dépendances
- Adaptez la configuration, enregistrez
- Lancez le démon

Allez dans l'écran "Plugins > Sécurité > Détection de téléphone (Bluetooth)" :

- Ajoutez un téléphone en cliquant sur le bouton +
- Puis donnez un nom à votre équipement et renseignez l'adresse MAC de la puce Bluetooth de votre téléphone
- Enregistrez
- Patientez quelques secondes et le statut de votre téléphone est mis à jour !

Voici un exemple de configuration :
![Exemple de configuration](images/example_config.png)

## 4. Configuration d'antennes distantes

La documentation est reprise du plugin BLEA, car l’implémentation du multi-antennes du plugin utilise le même principe que le plugin BLEA.

Le Bluetooth ayant une portée relativement limitée, il est possible qu'une partie de votre habitation soit hors portée de votre antenne selon l'emplacement de votre box Jeedom.
Mais il existe une solution: il est possible d'étendre le réseau en installant des antennes supplémentaires.

Le plus simple est d'utiliser un Raspberry pi (existant ou dédié selon l'équipement que vous avez déjà). On va supposer ici que le Raspberry est déjà installé avec une raspbian et que ssh ainsi que le Bluetooth sont activé.

## Créé l'antenne

Vous devez vous rendre sur la page du plugin (Plugins > Protocole Domotique) et cliquer sur "Antennes"

1) cliquez sur "Ajouter"
2) choisissez un nom
3) Entrez l'ip et le port (22 par défaut)
4) Entrez le nom d'utilisateur ("pi" par défaut) et le mot de passe
5) Entrez l'équipement Bluetooth sur le pi ("hci0" sur une installation par défaut)
6) Sauvegardez

## Installation du démon

Si il n'y a pas eu d'erreur et que votre antenne est bien créée dans le plugin, il faut maintenant installer les dépendances nécessaire et lancer le démon sur l'antenne qui va se charger de faire le lien entre les équipements Bluetooth à portée de l'antenne et le plugin (et donc Jeedom).

1) Cliquez sur le bouton "Envoyer les fichiers", cela peut prendre un peu de temps, patientez. Un bandeau vert confirmant la réussite va apparaître, rouge s'il y a eu un problème. Dans ce cas, vérifiez le log "phone_detection", vérifiez la configuration (ip, user, password, ...)
2) Cliquez ensuite sur le bouton "Lancer les dépendances". De nouveau, cela peut prendre du temps, patientez. Un bandeau vert confirmera la réussite ou rouge sinon (pareil, vérifiez la log phone_detection)
3) Optionnel, vous pouvez récupérer manuellement le log d'installation des dépendances en cliquant sur "Log dépendances" et vérifier la log, un fichier log spécifique sera disponible dans la config du plugin.
4) Si tout c'est bien déroulé, vous pouvez cliquer sur "Lancer le démon", après maximum une minute la date de dernière communication devrait se mettre à jour, cela veut dire que le démon communique correctement avec le plugin phone_detection.
5) Dernière étape optionnelle mais recommandée: activez la gestion automatique du démon en cliquant sur le bouton correspondant. Cela fera en sorte que le plugin tentera de relancer automatiquement le démon distant en cas de perte de connexion (pratique si votre pi distant a été temporairement débranché du secteur ou qu'il a été redémarré suite à des mises à jours).

## Important

Si vous utilisez un Raspberry a la fois pour BLEA et phone_detection, vous devez utiliser 2 clés Bluetooth différentes (hci0 et hci1). En effet de nombreux utilisateurs ont reporté des problèmes en utilisant la même clé pour les 2 plugins (plus d'informations sur le forum).

Merci Fabrice d'avoir partager une solution si la clé Bluetooth interne disparaît après un redémarrage de Raspberry.

source [forum - Multi-antenne pour le plugin phone_detection](https://community.jeedom.com/t/multi-antenne-pour-le-plugin-phone-detection/48841/34)
Les RPi ne savent plus voir le port Bluetooth interne quand un port externe est présent.
En d’autre terme. Au moment ou vous raccordez votre clé BT externe, hciconfig voit hci0 (l’interne à ce moment) et l’hci1 (externe à ce moment).
Au reboot : il n’y a plus que hci0 qui est visible (et c’est le BT externe).

La solution est présente sur le forum de Raspberry. Il faut mettre à jour un fichier (qui n’est, pour d’obscure raison, pas encore en production)
=> Le ficher à mettre à jour est le suivant :
[pi-bluetooth/btuart at master · RPi-Distro/pi-bluetooth (github.com)](https://github.com/RPi-Distro/pi-bluetooth/blob/master/usr/bin/btuart)

La source de mon information : [Bluetooth issue after updating to Kernel 5.4.51 on RPi4 - Raspberry Pi Forums](https://www.raspberrypi.org/forums/viewtopic.php?f=28&t=282948)
