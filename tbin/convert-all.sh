#!/bin/bash
# -*- coding: utf-8 mode: sh -*- vim:sw=4:sts=4:et:ai:si:sta:fenc=utf-8
source /etc/nulib.sh || exit 1

mef=
args=(
    "description"
    #"usage"
    -x,--mef .
)
parse_args "$@"; set -- "${args[@]}"

if [ $# -gt 0 ]; then
    files=("$@")
else
    setx -a files=ls_files . "pv-*.csv"
fi

for file in "${files[@]}"; do
    setx filename=basename "$file"
    cmd=(
        "$MYDIR/../bin/convert-pv-jury.php"
        -f "$file"
    )
    "${cmd[@]}" \
        -o "csv-${filename%.*}.csv" \
        -j "json-${filename%.*}.json" \
        -d >"yaml-${filename%.*}.yml"
    "${cmd[@]}" -o "xlsx-${filename%.*}.xlsx"
    [ -n "$mef" ] && "${cmd[@]}" -x "mef-${filename%.*}.xlsx"
done
