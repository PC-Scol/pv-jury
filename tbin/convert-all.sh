#!/bin/bash
# -*- coding: utf-8 mode: sh -*- vim:sw=4:sts=4:et:ai:si:sta:fenc=utf-8
source /etc/nulib.sh || exit 1

yaml=
json=
csv=
xlsx=
mef=
args=(
    "description"
    #"usage"
    -d,--yaml .
    -j,--json .
    -c,--csv .
    -x,--xlsx .
    -m,--mef .
)
parse_args "$@"; set -- "${args[@]}"

if [ $# -gt 0 ]; then
    files=("$@")
else
    setx -a files=ls_files . "pv-*.csv"
fi

if [ -z "$yaml" -a -z "$json" -a -z "$csv" -a -z "$xlsx" -a -z "$mef" ]; then
    yaml=1
    json=1
    csv=1
    xlsx=1
    mef=
fi

for file in "${files[@]}"; do
    setx filename=basename "$file"
    action "$filename"
    cmd=(
        "$MYDIR/../bin/convert-pv-jury.php"
        "$file"
    )
    if [ -n "$yaml" ]; then
        if "${cmd[@]}" -d >"yaml-${filename%.*}.yml"; then
            action yaml true
        else
            action yaml false
        fi
    fi
    [ -n "$json" ] && action json "${cmd[@]}" -j "json-${filename%.*}.json"
    [ -n "$csv" ] && action csv "${cmd[@]}" -o "csv-${filename%.*}.csv"
    [ -n "$xlsx" ] && action xlsx "${cmd[@]}" -o "xlsx-${filename%.*}.xlsx"
    [ -n "$mef" ] && action mef "${cmd[@]}" -x "mef-${filename%.*}.xlsx"
    adone
done
