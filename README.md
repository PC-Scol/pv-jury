# pv-jury

pv-jury est une application servant à mettre en forme les PV de Jury édités
depuis PEGASE

Les fonctionnalités principales sont les suivantes:
- enregistrer le fichier CSV au format Excel en appliquant le format numérique
  aux notes. Aucune autre donnée n'est rajoutée.
- mettre en forme le PV pour impression, sous une forme se rapprochant de
  l'édition sous APOGEE. Rajouter les statistiques par élément pédagogique
- obtenir un lien à partager aux enseignants ou aux membres du jury permettant
  de consulter le détail du PV par étudiant

Les fonctionnalités suivantes sont prévues dans le futur:
- exclusion de certains éléments pédagogiques de l'édition du PV.
  C'est utile quand la maquette est modélisée par blocs de compétences, afin
  d'exclure les éléments pédagogiques du second semestre si l'édition concerne
  uniquement le premier semestre

> [!TIP]
> **Obtenir de l'aide**
> Envoyez un message sur le [forum PC-SCOL](https://forum.pc-scol.fr)
> en mentionnant `@jclain`

## Faire l'installation initiale

pv-jury est conçu pour fonctionner en tant que container sous docker. il peut
ainsi fonctionner sur n'importe quel système Linux, Windows ou MacOS X

* Installez d'abord les pré-requis
  * Installation des [pré-requis pour Debian](documentation/00prerequis-linux.md)
    et autres distributions Linux. Ce mode d'installation est celui à
    sélectionner pour la production, mais peut aussi être utilisé pour les tests
    ou le développement, notamment si le poste de l'utilisateur est sous Linux.
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
* Enfin, il faut démarrer l'application.
  [Démarrer pv-jury](documentation/03demarrage.md)

## Installer une mise à jour

Généralement, il faut reconstruire les images avant de relancer les services:
~~~sh
cd pv-jury

# mettre à jour le dépôt
git pull

# reconstruire les images
./sbin/build -r

# redémarrer les services concernés
docker compose up -d
~~~

-*- coding: utf-8 mode: markdown -*- vim:sw=4:sts=4:et:ai:si:sta:fenc=utf-8:noeol:binary