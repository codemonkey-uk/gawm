#!/bin/bash
echo {

function deck
{
echo \"$1\": [
first=true
IFS=$(echo -en "\n\b")
for OUTPUT in $(find $1 -type f | cut -d/ -f 2)
do
    if [ "$first" = false ]; then
        echo ,
    fi
    echo -n \"$OUTPUT\"
    first=false
done
echo ]
}

deck alias
echo -n ,
deck motives
echo -n ,
deck murder
echo -n ,
deck object
echo -n ,
deck rels
echo -n ,
deck wildcards

echo }