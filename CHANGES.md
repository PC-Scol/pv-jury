## Release 0.8.1 du 23/03/2025-12:42

Cette version corrige et documente la prise en compte du proxy

* `bf5de20` documenter la configuration du proxy
* `ace311f` maj runphp pour utiliser le proxy
* `67d6dc7` maj doc

## Release 0.8.0 du 21/03/2025-19:19

Cette version ajoute et documente le support https natif

> [!IMPORTANT]
> La mise à jour *nécessite* le rajout de deux paramètres dans le fichier `.env`
>
> Copiez/collez la commande ci-dessous pour vous simplifier la vie
~~~sh
sed -i '/^LSN_ADDR=/a\
\
# Activer l'\''accès via https\
ENABLE_SSL=\
LSN_ADDR_SSL=8443' .env
~~~
Puis vous pouvez faire la mise à jour normalement

* `b9de8c4` maj doc
* `5414fa8` redirection automatique vers https
* `e6cf97c` support natif https

## Release 0.7.1 du 19/03/2025-04:48

* `2c4d448` maj doc
* `a66d7e8` utiliser reset plutôt que override pour la compatibilité
* `0c8317c` maj config demo
* `c39d037` maj .gitignore

## Release 0.7.0 du 17/03/2025-17:47

* `1a408e8` script start pour simplifier le démarrage
* `9836851` support de fichier compose local
* `03da6d0` bug: prise en compte du serveur CAS

## Release 0.6.0 du 14/03/2025-15:55

* `fb0bfcb` ajout de la licence
* `e04f120` configuration demo https
* `e389269` maj config demo

## Release 0.5.2 du 05/03/2025-09:20

## Release 0.5.1 du 05/03/2025-09:01

## Release 0.5.0 du 05/03/2025-08:53

* `51f0d2e` maj doc
* `ab74d46` afficher les points jury pour tous les objets

## Release 0.4.1 du 04/03/2025-16:42

* `2851135` améliorer l'affichage
* `c5e884d` corriger l'affichage de l'information résultats
* `7e52355` maj doc

## Release 0.4.0 du 04/03/2025-16:13

Afficher un avertissement si le fichier ne contient pas de résultats sur l'objet
délibéré. Dans ce cas, l'encart "nb étudiant/admis/ajournés" est vide

* `3179db9` ne pas planter si le fichier est invalide

## Release 0.3.4 du 04/03/2025-12:27

maj deps: bug dans nulib/spout

## Release 0.3.3 du 03/03/2025-13:04

maj projet pour dist automatique

## Release 0.3.2 du 01/03/2025-16:26

* `ca9f8bb` maj doc

## Release 0.3.1 du 01/03/2025-15:25

* `c1902ac` maj doc
* `391d8e5` ajout config de demo

## Release 0.3.0 du 01/03/2025-14:08

* `72304da` configuration pman
* `067fdf2` ajouter un avertissement de ne pas modifier le fichier source

## Version 0.2.1 du 21/02/2025-02:47

* `6a42ef9` finaliser le support de l'exclusion d'objets maquettes
* `41a040f` tech: le fichier témoin ne doit pas être suivi

## Version 0.2.0 du 19/02/2025-11:38

* `886d3de` support de l'exclusion d'objets maquettes
* `a303cc2` améliorer le téléchargement au format excel
* `8428d62` possibilité de supprimer un import
* `b1cfaba` support de l'authentification basique

## Version 0.1.7 du 17/02/2025-07:36
