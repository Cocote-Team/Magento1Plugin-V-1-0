# Magento 1 Module for Cocote.com website.

This module generates feed and communicates with cocote.com website to let it aggregate your products data.

Installation
To install the module just copy content of the module to main folder of your application.

After that clear cache.

If needed relogin to admin area.

First you need to configure the module in system->configuration->Cocote and from there you can also generate feed .xml file.
File will be refreshed each day at 3.00 A.M. by cron tasks.


# Plugin Cocote pour Magento 1

Ce module communique avec Cocote.com et genere un flux xml de vos offres produits.

Pour installer ce module:

1) Transfert et copie des fichiers

Telecharger ce module (via le boutton ci-dessus 'clone or dowload') sur votre serveur et copier son contenu dans le répertoire de votre site magento 1.

2) Caches

Vider les caches magento (Système > Gestion du Cache > "Flush Magento Cache") et activer le cache 'Cocote Cache'

Le module cocote est ensuite accessible depuis Système->configuration (l’icône doit apparaître sur la colonne de gauche)
