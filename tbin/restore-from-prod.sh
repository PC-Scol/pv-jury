#!/bin/bash
# -*- coding: utf-8 mode: sh -*- vim:sw=4:sts=4:et:ai:si:sta:fenc=utf-8
source /etc/nulib.sh || exit 1

SRCHOST=root@sda-services02-prod2021.univ.run
VARPATH=/var/lib/docker/volumes/pv-jury_app-data/_data/var

restart=1
args=(
    "restaurer les données depuis la prod"
    #"usage"
    -n,--no-restart restart=
)
parse_args "$@"; set -- "${args[@]}"

cd "$MYDIR/.."

vardir="$SRCHOST:$VARPATH"
estep "Mise à jour du répertoire var"
rsync -vrLpt --delete "$vardir/" "devel/var/"

mv devel/var/pvs.prod.db devel/var/pvs.devel.db

if [ -n "$restart" ]; then
    ./start -R
fi
