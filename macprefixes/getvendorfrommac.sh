#!/bin/bash
LC_ALL=C
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
OUIFILE=$DIR/oui.txt

if [ -z "$1" ]
	then
		echo "Error";
		exit 0;
fi

cat $OUIFILE | grep -m1 $(echo $1 | tr '[a-z]' '[A-Z]' | tr -dc '[0-9A-F]' | cut -c1-6) | cut -f2- ;
