Si vous n'avez pas encore installé les pré-requis ni cloné le dépôt, retournez
aux sections précédentes: installer les pré-requis [pour linux](00prerequis-linux.md)
ou [pour windows/WSL](00prerequis-wsl.md) puis [cloner le dépôt](01cloner-depot.md)

# Contruire les images

Avant de pouvoir utiliser pv-jury, il faut construire les images docker
utilisées par l'application

Commencer en faisant une copie du fichier `..env.dist` nommée `.env`
~~~sh
cp ..env.dist .env
~~~
Il FAUT consulter `.env` et l'éditer AVANT de continuer. Notamment, les
variables suivantes doivent être configurées le cas échéant:

`APT_PROXY`
: proxy pour l'installation des paquets Debian, e.g `http://monproxy.tld:3142`

`APT_MIRROR`
`SEC_MIRROR`
: miroirs à utiliser. Il n'est généralement pas nécessaire de modifier ces
  valeurs

`TIMEZONE`
: Fuseau horaire, si vous n'êtes pas en France métropolitaine, e.g
  `Indian/Reunion`

`PRIVAREG`
: nom d'un registry docker interne vers lequel les images pourraient être
  poussées. Il n'est pas nécessaire de modifier ce paramètre.

Une fois le fichier configuré, les images peuvent être construites
~~~sh
./sbin/build
~~~

--

Une fois que vous avez construit les images, vous pouvez démarrer l'application.
[>> Démarrer pv-jury](03demarrage.md)

-*- coding: utf-8 mode: markdown -*- vim:sw=4:sts=4:et:ai:si:sta:fenc=utf-8:noeol:binary