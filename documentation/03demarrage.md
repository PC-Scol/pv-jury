Si vous n'avez pas encore construit les images, vous devez le faire au préalable.
[Construire les images](02construire-images.md)

# Démarrer pv-jury

Les fichiers suivants sont générés lors de la construction des images:
- `.env`
- `_web/apache/auth_cas.conf`
- `_web/apache/ssl.conf`
- `public/logo.png`

Il faut les consulter et les modifier le cas échéant avant de démarrer
l'application

Une fois la configuration effectuée, le démarrage se fait avec la commande
suivante:
~~~sh
./start
~~~

Avec le paramétrage par défaut, l'application est alors accessible à l'adresse
<http://localhost:8080>

En cas de modification de la configuration, il suffit de relancer la commande
ci-dessus pour relancer les services le cas échéant

Arrêter l'application avec la commande suivante:
~~~sh
./start -k
~~~

Afficher les logs avec la commande suivante:
~~~sh
docker compose logs -f
~~~

## Configurer l'authentification CAS

Par défaut, l'application n'est pas authentifiée

Pour activer l'authentification CAS, il faut modifier les deux paramètres
suivants dans le fichier `.env`
~~~sh
AUTH_CAS=1
CAS_URL=https://cas.univ.fr/cas
~~~

Le fichier `_web/apache/auth_cas.conf` permet de configurer les
autorisations. La configuration par défaut autorise TOUS les utilisateurs
authentifiés, ce qui inclue aussi généralement les étudiants.
Si le serveur CAS fournit les attributs nécessaires, il est possible de filtrer
par exemple sur l'affiliation pour n'autoriser que les personnels.

Ensuite relancer le serveur avec la commande suivante:
~~~sh
./start -r
~~~

NB: bien entendu, l'application doit être autorisée à utiliser le serveur CAS.
D'une manière générale, pour un déploiement sur le réseau interne avec un nom
DNS connu, l'autorisation va de soi. N'hésitez pas à contacter votre
administrateur système si nécessaire, en donnant la valeur du paramètre
`APP_URL` qui est le nom de l'application tel que connu du serveur CAS.

## Modification du logo

Pour remplacer le logo par celui de votre université dans l'application web, il
faut remplacer le fichier `public/logo.png` par votre propre image au format
PNG (il faut garder le même nom)
~~~sh
cp ~/path/to/monlogo public/logo.png
~~~

L'image DOIT avoir une hauteur de 50 pixel. La largeur importe peu.

## Mettre l'application en production

La configuration par défaut n'est appropriée que pour le développement.

Pour rendre l'application disponible à tous les agents concernés, il faut
modifier les paramètres `LSN_ADDR` et `APP_URL`

Par exemple, si l'application est installée sur un serveur `pv-jury.univ.tld`,
elle pourrait être accessible à l'adresse <http://pv-jury.univ.tld>, il faut
donc faire les modifications suivantes dans le fichier `.env`
~~~sh
LSN_ADDR=80
APP_URL=http://pv-jury.univ.tld
~~~

Ensuite relancer le serveur avec la commande suivante:
~~~sh
./start -r
~~~

NB: activer l'accès en https n'est pas documenté ici. envoyer un message sur le
forum si vous êtes intéressé

-*- coding: utf-8 mode: markdown -*- vim:sw=4:sts=4:et:ai:si:sta:fenc=utf-8:noeol:binary