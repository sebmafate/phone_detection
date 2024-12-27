---
layout: default
title: Changelog
lang: fr_FR
---

# Changelog

## 2024-12-26 v3.0.0

Changements pour être compatible avec Debian 12.

1. Utilisation de la librairie php phpseclib3 pour toutes les communications avec les antennes (SSH et SFTP).
2. Modification du script d'installation des dépendances.
3. Utilisations du même mécanisme de communication entre l'antenne locale et jeedom qu'entre les antennes distantes et jeedom.

> Important: L’utilisateur configuré doit être dans le groupe sudoers et avoir le droit de faire un sudo sans confirmer son mot de passe. Cette pratique est dangereuse d'un point de vue sécurité, et il est recommandé de créer un utilisateur qui ne pourra se connecter que depuis votre jeedom.

Si vous avez besoin d’aide pour la création et la configuration de cet utilisateur:

```text
1. sudo adduser jeedom
2. sudo visudo

(a la fin du fichier)
jeedom ALL=(ALL) NOPASSWD:ALL
```

Assurez-vous que vous pouvez vous connectez avec cet utilisateur "jeedom" dans notre exemple, et que la commande sudo ls ne demande pas de mot de passe. Si ce n'est pas le cas, le plugin ne fonctionnera pas sur l'antenne distante:

```text
su - jeedom
sudo ls
```

## 2024-03-03 v2.2.6

En cas de problème Bluetooth, le monitoring des devices est arrêté, et le problème est reporte par l'antenne au plugin phone_detection. Celui-ci arrête le daemon sur l'antenne distante. Le comportement était different pour le daemon local, qui reportait bien un problème, mais qui n’était pas arrêté par le plugin. Ce problème est maintenant fixé.

## 2024-03-03 v2.2.5

Correction autour du point (2) de la mise a jour précédente. Si l'interface passe down. on va arrêter le monitoring si
tous les mobiles ne retournent plus de réponses. Auparavant si on avait le message: "No response for mac XXX" 5 fois de suite, alors le monitoring s’arrêtait.

## 2024-03-01 v2.2.4

Amelioration autour de l’état de l'interface HCI:

1. Si l'interface HCI n'est pas UP au démarrage du demon, le demon va essayer de la passer UP. En cas d’échec le demon s’arrête. Si vous avez la gestion du demon active, le demon va être redémarré a intervale régulier par jeedom.
2. Si l'interface HCI passe DOWN alors que le demon tourne et monitor des telephones. Le demon va effectuer 5 sequences de monitoring avec la meme périodicité que d'habitude. Si le problème persiste, le demon va arrêter le monitoring, et informer Jeedom du problème, qui va stopper complètement le demon. Si vous avez la gestion du demon active, celui-ci va être redémarrer automatiquement par Jeedom, et l'interface DOWN devrait être fixe par le point (1).

Une fois la version 2.2.4 installe, n'oubliez pas de mettre a jour vos antennes, et de redémarrer le daemon local si vous l'utilisez.

## 2024-02-26 v2.2.3

Correction du problème 'sending frame failed (-19)' qui apparaissait sur des distributions autres que raspberry, quel que soit la version du kernel Linux.
Une fois la version 2.2.3 installée, n'oubliez pas de mettre a jour vos antennes, et de redémarrer le demon local si vous l'utilisez.

## 2024-01-04 v2.2.2

* Changement des paramètres de polling par default (10/30) vers (15/60). Il est recommande de ne pas descendre sous les 15 secondes pour la fréquence de polling des telephones absents.
* Fix pour le problème de droits avec l'antenne locale qui démarrait mais n’était pas autoriser a envoyer des requêtes Bluetooth.

## 2024-01-02 v2.2.1

Correction de bugs suite au passage en 2.2.0. Certains mobiles ne sont plus détectés, en fonction de différentes
conditions (ordre de polling, presence ou non du mobile, délai de réponse, ...).

## 2023-12-26 v2.2.0

Une nouvelle approche pour éviter les problèmes Bluetooth, principalement vu sous Debian 11 sur raspberry. Je n'avais pas ce problème sous Debian 10.
Au lieu d'avoir une approche multi-thread (un par mobile), les mobiles sont maintenant traites dans un seul thread, avec des appels asynchrones.
Cela se base sur la class aiobtname de François Wautier. Avec cette nouvelle approche, le monitoring de telephone est beaucoup plus stable, je
n'ai plus besoin de redémarrer le daemon phone_detection, ou de redémarrer mes antennes a intervals réguliers.

On se degage de pybluez, et de l'api bluez avec cette version, qui utilise directement les libraries python3 et les sockets HCI pour communiquer
avec les mobiles.

Il n'y a pas d'installation particulière de dépendances. Installer le plugin de manière classique, et mettez a jour vos antennes.

## 2023-11-03 v2.1.0

Pile un an apres la dernière modification majeure :)

Pas mal de fixes, et d'ameliorations autour de la gestion des antennes et du Bluetooth. N'oubliez pas de mettre a jour vos antennes !
  
* Fixe un problème sur l’arrêt des antennes via le plugin. Cela générait une exception, et le process phone_detectiond.py n’était pas toujours arrêté. En cas de redémarrage, une error "Socket already in use" était générée.
* Les logs des antennes étaient rapatriées toutes les 15 minutes sur le jeedom, et le fichier était re-initialise a chaque fois. Maintenant, les logs sont concaténées pour chaque antenne.
* la version indiquée par les antennes étaient folklorique. La version provenait de valeurs hardcodees dans le code php. Maintenant, un fichier version.txt est créé a l'installation ou la mise a jour du plugin phone_detection, et envoyé a chaque antenne via "envoyer les fichiers".
* Il arrivait que le driver Bluetooth soit inutilisable via l'API bluez. Il suffit en general d'un 'hciconfig hci0 reset' pour le rendre de nouveau opérationnel.
  * Cette commande est utilisée au démarrage du demon, si on ne parvient pas a initialiser la libraire Bluetooth avec le bon module Bluetooth.
  * Une fois que le demon tourne sur l'antenne, un thread est créé pour chaque mobile a surveiller. Auparavant, si une exception était générée car le module Bluetooth n’était pas opérationnel, le thread pouvait s’arrêter, et ainsi ne plus monitorer le mobile, bien que le demon soit vu actif par jeedom. Maintenant, le thread va essayer 3 fois de réinitialiser le module Bluetooth. En cas d’échec, le thread va s’arrêter, sinon il va reprendre une surveillance active du telephone.
  * Le demon sur l'antenne envoie de manière régulière des informations a jeedom pour lui indiquer qu'il est toujours vivant. J'ai ajoute plusieurs informations dans ce message envoyé. D'une part, la version du demon phone_detectiond.py qui tourne sur l'antenne. D'autre part, le nombre de thread actif, c'est a dire le nombre de telephone toujours surveille, et le nombre total de telephone normalement surveille. Si ce nombre est different, cela signifie qu'il y a une un problème Bluetooth que le daemon n'a pas sur résoudre tout seul. Dans ce cas, le plugin phone_detection sur jeedom va arrêter le demon. Si vous avez active la surveillance active du demon, celui-ci sera automatiquement redémarré.

J’espère avoir fixe les problèmes remontés récréments dans le forum, sinon, on aura en tout cas plus d'information pour comprendre les problèmes.
  
## 2022-11-03 v2.0.0

* Utilisation de pybluez pour effectuer un appel python au lieu d'un appel système de hciconfig pour la demande d'information du mobile.
  Il semble que cela solutionne les problèmes de blocage du daemon qui pouvait arriver sur raspberry.
  Installation de hcidump qui permet de surveiller en temps reel les paquets envoyés et reçus par l'antenne Bluetooth. Pour voir les paquets, il suffit d’exécuter la commande 'hcidump -t -X'

## 2021-10-18 v0.5.0

* Correction d'un problème de mise a jour de l'état du groupe de téléphone, suite a la perte d'une antenne. L'état était systématiquement passé a 0, même si certains téléphones étaient encore visible au travers d'autres antennes.

## 2021-05-26 v0.4.0

* Ajout du support "multi-antennes" permettant d’étendre la couverture Bluetooth gérer par le plugin. Le multi-antennes utilisent le même principe que le plugin BLEA, en utilisant des équipements distantes possédant une clé Bluetooth et envoyant les informations au plugin phone_detection installe sur Jeedom.
* Modification de l'interface 'configuration des équipements', pour être en phase avec le design 4.1 / 4.2. Cela comprend notamment la suppression du menu a gauche listant les équipements.

## v0.3

Version stable du plugin, avec une unique antenne gérée sur le serveur Jeedom.

# Documentation

[Documentation]({{site.baseurl}}/)
