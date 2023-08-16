---
layout: default
title: Changelog
lang: fr_FR
---

# Changelog

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
