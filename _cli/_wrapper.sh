#!/bin/bash
# -*- coding: utf-8 mode: sh -*- vim:sw=4:sts=4:et:ai:si:sta:fenc=utf-8
# S'assurer que le script PHP est lancé avec l'utilisateur www-data
# Tous les chemins suivants sont relatifs au répertoire qui contient ce script

# Chemin relatif de la racine du projet
PROJPATH=..

# Chemin relatif vers le lanceur PHP
LAUNCHERPATH=.launcher.php

# Chemin relatif des scripts PHP wrappés
WRAPPEDPATH=

###############################################################################
MYDIR="$(dirname -- "$0")"
MYNAME="$(basename -- "$0")"

if [ ! -L "$0" ]; then
    echo "\
$0
Ce script doit être lancé en tant que lien symbolique avec un nom de la forme
'monscript.php' et lance le script PHP du même nom situé dans le même répertoire
avec l'utilisateur www-data

Vérification des liens..."
    cd "$MYDIR"
    for i in *.php*; do
        [ -f "$i" ] || continue
        name="bin/${i%.*}.php"
        dest="../_cli/_wrapper.sh"
        link="../bin/${i%.*}.php"
        if [ -L "$link" ]; then
            echo "* $name OK"
        elif [ -e "$link" ]; then
            echo "* $name KO (not a link)"
        else
            echo "* $name NEW"
            ln -s "$dest" "$link" || exit 1
        fi
    done
    exit 0
fi

MYTRUESELF="$(readlink -f "$0")"
MYTRUEDIR="$(dirname -- "$MYTRUESELF")"
PROJDIR="$(cd "$MYTRUEDIR${PROJPATH:+/$PROJPATH}"; pwd)"
launcher="$MYTRUEDIR/$LAUNCHERPATH"
class="$MYTRUEDIR${WRAPPEDPATH:+/$WRAPPEDPATH}/${MYNAME%.php}.phpc"
script="$MYTRUEDIR${WRAPPEDPATH:+/$WRAPPEDPATH}/${MYNAME%.php}.php"

[ -f /g/init.env ] && source /g/init.env

www_data="${DEVUSER_USERENT%%:*}"
[ -n "$www_data" ] || www_data=www-data

cmd=(php "$launcher")
[ -n "$MEMPROF_PROFILE" ] && cmd+=(-dextension=memprof.so)
if [ -f "$class" ]; then
  cmd+=("$(<"$class")")
else
  cmd+=("$script")
fi
cmd+=("$@")

if [ "$(id -u)" -eq 0 ]; then
    su-exec "$www_data" "${cmd[@]}"
else
    exec "${cmd[@]}"
fi
