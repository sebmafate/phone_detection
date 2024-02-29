---
layout: default
title: Changelog
lang: fr_FR
---

# Changelog

## 2024-02-26 v2.2.3

Correction du probleme 'sending frame failed (-19)' qui apparaissait sur des distributions autres que raspberry, quel que soit la version du kernel linux.
Une fois la version 2.2.3 installe, n'oubliez pas de mettre a jour vos antennes, et de redemarrer le deamon local si vous l'utilisez.

## 2024-01-04 v2.2.2

* Changement des parametres de polling par default (10/30) vers (15/60). Il est recommande de ne pas descendre sous les 15 secondes pour la frequence de polling des telephones absents.
* Fix pour le probleme de droits avec l'antenne locale qui demarrait mais n'etait pas autoriser a envoyer des requetes bluetooth.

## 2024-01-02 v2.2.1

Correction de bugs suite au passage en 2.2.0. Certains mobiles ne sont plus detectes, en fonction de differentes
conditions (ordre de polling, presence ou non du mobile, delai de reponse, ...).

## 2023-12-26 v2.2.0

Une nouvelle approche pour eviter les problemes bluetooth, principalement vu sous debian 11 sur raspberry. Je n'avais pas ce probleme sous debian 10.
Au lieu d'avoir une approche multi-thread (un par mobile), les mobiles sont maintenant traites dans un seul thread, avec des appels asynchrones.
Cela se base sur la class aiobtname de François Wautier. Avec cette nouvelle approche, le monitoring de telephone est beaucoup plus stable, je
n'ai plus besoin de redemarrer le deamon phone_detection, ou de rebooter mes antennes a intervals reguliers.

On se degage de pybluez, et de l'api bluez avec cette version, qui utilise directement les libraries python3 et les sockets HCI pour communiquer
avec les mobiles.

Il n'y a pas d'installation particuliere de dependances. Installer le plugin de maniere classique, et mettez a jour vos antennes.

## 2023-11-03 v2.1.0

Pile un an apres la derniere modification majeure :)

Pas mal de fixes, et d'ameliorations autour de la gestion des antennes et du bluetooth. N'oubliez pas de mettre a jour vos antennes !
  
* Fixe un probleme sur l'arret des antennes via le plugin. Cela generait une exception, et le process phone_detectiond.py n'etait pas toujours arrete. En cas de redemarrage, une error "Socket already in use" etait generee.
* Les logs des antennes etaient rappatriees toutes les 15 minutes sur le jeedom, et le fichier etait re-initialise a chaque fois. Maintenant, les logs sont concatenees pour chaque antenne.
* la version indiquee par les antennes etaient folklorique. La version provenait de valeurs hardcodees dans le code php. Maintenant, un fichier version.txt est cree a l'installation ou la mise a jour du plugin phone_detection, et envoye a chaque antenne via "envoye les fichiers".
* Il arrivait que le driver bluetooth soit inutilisable via l'API bluez. Il suffit en general d'un 'hciconfig hci0 reset' pour le rendre de nouveau operationel.
  * Cette commande est utilisee au demarage du demon, si on ne parvient pas a initialiser la libraire bluetooth avec le bon module bluetooth.
  * Une fois que le demon tourne sur l'antenne, un thread est cree pour chaque mobile a surveiller. Auparavant, si une exception etait genere car le module bluetooth n'etait pas operationel, le thread pouvait s'arreter, et ainsi ne plus monitorer le mobile, bien que le demon soit vu actif par jeedom. Maintenant, le thread va essayer 3 fois de reinitialiser le module bluetooth. En cas d'echec, le thread va s'arreter, sinon il va reprendre une surveillance active du telephone.
  * Le demon sur l'antenne envoie de maniere reguliere des informations a jeedom pour lui indiquer qu'il est toujours vivant. J'ai ajoute plusieurs informations dans ce message envoye. D'une part, la version du demon phone_detectiond.py qui tourne sur l'antenne. D'autre part, le nombre de thread actif, c'est a dire le nombre de telephone toujours surveille, et le nombre total de telephone normalement surveille. Si ce nombre est different, cela signifie qu'il y a une un probleme bluetooth que le daemon n'a pas sur resoudre tout seul. Dans ce cas, le plugin phone_detection sur jeedom va arreter le demon. Si vous avez active la surveillance active du demon, celui-ci sera automatiquement redemarre.

J'espere avoir fixe les problemes remontes recemments dans le forum, sinon, on aura en tout cas plus d'information pour comprendre les problemes.
  
## 2022-11-03 v2.0.0

* Utilisation de pybluez pour effectuer un appel python au lieu d'un appel système de hciconfig pour la demande d'information du mobile.
  Il semble que cela solutionne les problèmes de blocage du daemon qui pouvait arriver sur raspberry.
  Installation de hcidump qui permet de surveiller en temps reel les paquets envoyés et reçus par l'antenne bluetooth. Pour voir les paquets, il suffit d’exécuter la commande 'hcidump -t -X'

## 2021-10-18 v0.5.0

* Correction d'un probleme de mise a jour de l'état du groupe de téléphone, suite a la perte d'une antenne. L'état était systématiquement passé a 0, même si certains téléphones étaient encore visible au travers d'autres antennes.

## 2021-05-26 v0.4.0

* Ajout du support "multi-antennes" permettant d’étendre la couverture Bluetooth gérer par le plugin. Le multi-antennes utilisent le même principe que le plugin BLEA, en utilisant des équipements distantes possédant une clé Bluetooth et envoyant les informations au plugin phone_detection installe sur Jeedom.
* Modification de l'interface 'configuration des équipements', pour être en phase avec le design 4.1 / 4.2. Cela comprend notamment la suppression du menu a gauche listant les équipements.

## v0.3

Version stable du plugin, avec une unique antenne gérée sur le serveur Jeedom.

# Documentation

[Documentation]({{site.baseurl}}/)
