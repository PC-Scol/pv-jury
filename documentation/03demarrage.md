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

Par exemple, si l'application est installée sur un serveur `pv-jury.int.tld`,
elle pourrait être accessible à l'adresse <http://pv-jury.int.tld>, il faut donc
faire les modifications suivantes dans le fichier `.env`
~~~sh
LSN_ADDR=80
APP_URL=http://pv-jury.int.tld
~~~

Ensuite relancer le serveur avec la commande suivante:
~~~sh
./start -r
~~~

## Activer l'accès en https

Si vous souhaitez activer l'accès en https, il y a un certain nombre
d'opérations supplémentaires à effectuer

Cette documentation expose 3 façons de procéder: reverse proxy externe, support
https natif, reverse proxy tournant dans un autre container sur le même serveur
docker (ou sur le même cluster docker swarm)

### Reverse proxy externe

C'est la solution la plus simple: si vous avez déjà un reverse proxy partagé, il
vous suffit de le configurer pour faire reverse proxy depuis une adresse
publique e.g <https://pv-jury.pub.tld> vers l'adresse interne configurée
ci-dessus, e.g <http://pv-jury.int.tld>

N'oubliez pas de modifier la configuration pour indiquer la nouvelle adresse
publique
~~~sh
APP_URL=https://pv-jury.pub.tld
~~~

Puis relancez le serveur
~~~sh
./start -r
~~~

### Support https natif

Si vous n'avez pas de reverse proxy partagé, il est possible d'activer le
support https natif, géré directement par le serveur apache qui fait tourner
pv-jury

Modifiez le fichier `.env` pour activer ssl. Modifiez aussi l'adresse de
l'application pour mentionner l'accès en https:
~~~sh
ENABLE_SSL=1
LSN_ADDR_SSL=443
APP_URL=https://pv-jury.int.tld
~~~

Vous devez bien entendu disposer d'un certificat. Copiez le certificat et la clé
privée dans le répertoire `_web/ssl`
~~~sh
cp path/to/mycert.crt path/to/mycert.key _web/ssl
~~~

Si le certificat ne contient pas la chaine autorité, vous devez aussi copier le
fichier autorité
~~~sh
cp path/to/ca.crt _web/ssl
~~~
NB: vous pouvez aussi inclure directement l'autorité dans le certificat

Ensuite, il faut modifier le fichier `_web/apache/ssl.conf` pour mentionner les
certificats
~~~conf
SSLCertificateFile    /etc/ssl/certs/mycert.crt
SSLCertificateKeyFile /etc/ssl/private/mycert.key
~~~

Si l'autorité est dans un fichier à part, il faut aussi le mentionner
~~~conf
SSLCertificateChainFile /etc/ssl/certs/myca.crt
~~~

Puis relancez le serveur
~~~sh
./start -r
~~~

## Reverse proxy tournant dans un autre container

Il faut créer le fichier `docker-compose.local.yml` qui permet de définir une
configuration différente. En l'occurrence, on doit désactiver l'écoute, place
l'application sur le bon réseau, et ajoute les labels nécessaires

Prenons l'exemple d'un reverse proxy traefik qui tourne sur le réseau `pubnet`
sur le même serveur docker (ou le même cluster docker swarm) que celui qui fait
tourner pv-jury. Voici un exemple de fichier `docker-compose.local.yml`:
~~~yaml
services:
  web:
    networks:
      pubnet:
    ports: !reset []
    labels:
      - "traefik.http.routers.http-pv-jury-web.entryPoints=http"
      - "traefik.http.routers.http-pv-jury-web.rule=Host(`pv-jury.pub.tld`)"
      - "traefik.http.routers.http-pv-jury-web.middlewares=pv-jury-redirect"
      - "traefik.http.routers.http-pv-jury-web.service=pv-jury-web"
      - "traefik.http.routers.https-pv-jury-web.entryPoints=https"
      - "traefik.http.routers.https-pv-jury-web.rule=Host(`pv-jury.pub.tld`)"
      - "traefik.http.routers.https-pv-jury-web.tls=true"
      - "traefik.http.routers.https-pv-jury-web.tls.certresolver=myresolver"
      - "traefik.http.routers.https-pv-jury-web.service=pv-jury-web"
      - "traefik.enable=true"
      - "traefik.http.services.pv-jury-web.loadbalancer.server.port=80"
      - "traefik.http.middlewares.pv-jury-redirect.redirectscheme.scheme=https"

networks:
  pubnet:
    external: true
~~~
NB: bien entendu, s'il s'agit d'un cluster docker swarm, les labels doivent être
sous la clé `deploy`

Puis relancez le serveur
~~~sh
./start -r
~~~


-*- coding: utf-8 mode: markdown -*- vim:sw=4:sts=4:et:ai:si:sta:fenc=utf-8:noeol:binary