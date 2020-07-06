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

deck aliases
echo -n ,
deck motives
echo -n ,
deck murder_cause
echo -n ,
deck murder_discovery
echo -n ,
deck objects
echo -n ,
deck relationships
echo -n ,
deck wildcards

echo }