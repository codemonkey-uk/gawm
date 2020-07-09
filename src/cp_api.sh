#!/bin/bash
mkdir api
cp src/api/*.json api/
for F in $(ls src/api/ | grep "php$")
do
	sed -e "s/GAWM_DB_USER/\"${GAWM_DB_USER}\"/g" -e "s/GAWM_DB_HOST/\"${GAWM_DB_HOST}\"/g" -e "s/GAWM_DB_PWD/\"${GAWM_DB_PWD}\"/g" < src/api/$F > api/$F
done