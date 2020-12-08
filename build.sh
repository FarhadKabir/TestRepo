#!/usr/bin/env bash
if command -v dpkg-query -l zip
then
  mkdir dist
  zip dist/altapay-for-woocommerce.zip -r * -x "dist/*" "tests/*" "bin/*" build.sh guide.md .gitignore phpunit.xml.dist phpstan.neon.dist composer.json composer.lock @
else
  echo "Zip package is not currently installed"
fi