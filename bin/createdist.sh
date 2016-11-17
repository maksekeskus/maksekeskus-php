#!/bin/bash

set -e

die () {
    echo >&2 "$@"
    exit 1
}

[ "$#" -eq 1 ] || die "Version argument is mandatory (for example 1.0)"

VERSION=$1

rm -rf maksekeskus-$VERSION

mkdir maksekeskus-$VERSION/
cp -rp lib maksekeskus-$VERSION/
cp composer.json maksekeskus-$VERSION/

cd maksekeskus-$VERSION/
composer install --no-dev
rm composer.lock composer.json

cd ..
tar -zcvf maksekeskus-$VERSION.tar.gz maksekeskus-$VERSION
zip -r maksekeskus-$VERSION.zip maksekeskus-$VERSION
rm -rf maksekeskus-$VERSION

