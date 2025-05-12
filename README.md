> [!IMPORTANT]
> **En cas de livraison d'une nouvelle version de l'application**
> Prenez le temps de lire [ces instructions](UPDATE.md) AVANT de commencer à
> faire quoi que ce soit.

# pv-jury

`pv-jury` est une application servant à mettre en forme les PV de Jury édités
depuis PEGASE

Les fonctionnalités principales sont les suivantes:
- éditer le PV au format Excel pour impression.
  Des statistiques par élément pédagogique sont rajoutées à la fin du tableau
- possibilité d'exclusion de certains objets maquettes de l'édition du PV.
  C'est utile quand la maquette est modélisée par blocs de compétences, afin
  d'exclure les éléments pédagogiques du second semestre si l'édition concerne
  uniquement le premier semestre
- obtenir un lien à partager aux enseignants ou aux membres du jury permettant
  de consulter le détail du PV par étudiant
- enregistrer le fichier CSV au format Excel en appliquant le format numérique
  aux notes. Aucune autre donnée n'est rajoutée.

Pour l'édition du PV, plusieurs modèles sont disponibles:
- modèle classique APOGEE: le résultat se rapproche de l'édition sous APOGEE.
  L'édition se fait pour une seule session
- modèle classique APOGEE avec coefficients: une variante du modèle précédent
  qui ajoute la colonne "Coefficient"
- modèle PEGASE: le résultat est le plus proche de la forme originale fournie
  par PEGASE. L'édition se fait pour les sessions sélectionnées. Il est possible
  de choisir quelles colonnes seront incluses dans l'édition.

NB: dans certaines circonstances, s'il y a des acquis capitalisés en session 2
l'année antérieure, PEGASE ne les remonte pas sur la bonne session. `pv-jury`
pallie ce bug en faisant le calcul suivant, pour chaque objet:
- si l'édition comporte les deux sessions
- si le résultat est AJOURNE *et*
  si un acquis capitalisé est présent en session 2
- alors l'objet est réputé capitalisé en session 1

> [!TIP]
> **Obtenir de l'aide**
> Envoyez un message sur le [forum PC-SCOL](https://forum.pc-scol.fr)
> en mentionnant `@jclain`

## Faire l'installation initiale

pv-jury est conçu pour fonctionner en tant que container sous docker. il peut
ainsi fonctionner sur n'importe quel système Linux, Windows ou MacOS X

Les commandes listées ci-dessous sont pour un démarrage rapide si vous savez ce
que vous faites. Si c'est la première fois, il est conseillée de cliquer sur les
liens pour avoir des détails sur la procédure.

* Installez d'abord les pré-requis
  * Installation des [pré-requis pour linux](documentation/00prerequis-linux.md)
    (Debian ou autres distributions Linux. Ce mode d'installation est celui à
    sélectionner pour la production, mais peut aussi être utilisé pour les tests
    ou le développement, notamment si le poste de l'utilisateur est sous Linux.
    ~~~sh
    sudo apt update && sudo apt install git curl rsync tar unzip python3 gawk
    ~~~
    ~~~sh
    curl -fsSL https://get.docker.com | sudo sh
    ~~~
    ~~~sh
    [ -n "$(getent group docker)" ] || sudo groupadd docker
    sudo usermod -aG docker $USER
    ~~~

    > [!IMPORTANT]
    > **Configuration du proxy**
    > Si vous utilisez un proxy, veuillez consulter la page
    > [pré-requis pour linux](documentation/00prerequis-linux.md)
    > pour des instructions sur la façon de le configurer
  * Installation des [pré-requis pour WSL](documentation/00prerequis-wsl.md), le
    sous-système Linux pour Windows. Ce mode d'installation est approprié pour
    les tests ou le développement.
* Puis ouvrez un terminal et clonez le dépôt
  ~~~sh
  git clone https://github.com/PC-Scol/pv-jury.git
  ~~~
  ~~~sh
  cd pv-jury
  ~~~
* Ensuite, il faut construire les images docker nécessaires.
  [Construire les images](documentation/02construire-images.md)
  ~~~sh
  ./sbin/build
  ~~~
  ~~~sh
  nano .env
  ~~~
  ~~~sh
  ./sbin/build
  ~~~
* Enfin, on peut démarrer l'application.
  [Démarrer pv-jury](documentation/03demarrage.md)
  ~~~sh
  ./start
  ~~~
  Si le paramétrage par défaut n'est pas modifié, l'application est maintenant
  accessible à l'adresse <http://localhost:8080>

## Installer une mise à jour

Veuillez suivre [ces instructions](UPDATE.md) AVANT de commencer à faire quoi
que ce soit.

-*- coding: utf-8 mode: markdown -*- vim:sw=4:sts=4:et:ai:si:sta:fenc=utf-8:noeol:binary
