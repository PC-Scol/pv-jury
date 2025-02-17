Si vous n'avez pas encore installé les pré-requis ni cloné le dépôt, retournez
aux sections précédentes: installer les pré-requis [pour linux](00prerequis-linux.md)
ou [pour windows/WSL](00prerequis-wsl.md) puis [cloner le dépôt](01cloner-depot.md)

# Contruire les images

Avant de pouvoir utiliser pv-jury, il faut construire les images docker
utilisées par l'application

Lancer une première fois la commande `sbin/build` pour générer les fichiers de
configuration
~~~sh
./sbin/build
~~~
Le fichier `.env`, entre autres, est généré. Il FAUT consulter ce fichier et
l'éditer **AVANT** de continuer. Notamment, les variables suivantes doivent être
configurées le cas échéant:

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

Une fois le fichier configuré, les images peuvent être construites en relançant
une deuxième fois la commande `sbin/build`
~~~sh
./sbin/build
~~~

--

Une fois que vous avez construit les images, vous pouvez démarrer l'application.
[>> Démarrer pv-jury](03demarrage.md)

-*- coding: utf-8 mode: markdown -*- vim:sw=4:sts=4:et:ai:si:sta:fenc=utf-8:noeol:binary