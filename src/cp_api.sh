#!/bin/bash
mkdir -p build/api
for F in $(ls src/api/ | grep "php$")
do
	sed -e "s/GAWM_DB_USER/\"${GAWM_DB_USER}\"/g" -e "s/GAWM_DB_HOST/\"${GAWM_DB_HOST}\"/g" -e "s/GAWM_DB_PWD/\"${GAWM_DB_PWD}\"/g" < src/api/$F > build/api/$F
done

mkdir -p build/css
cp src/css/* build/css

mkdir -p build/js
cp src/js/* build/js

mkdir -p build/assets
cp assets/* build/assets

cp src/*.html build

# should probably be a submodule, or use php package management or whatever
# but for now just put it in the same folder as gawm
# git clone https://github.com/vinkla/hashids.git
cp -r ../hashids/src build/api/Hashids
