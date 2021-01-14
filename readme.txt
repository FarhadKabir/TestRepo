===  Altapay for PrestaShop ===


== Code Analysis==

PHPStan is being used for running static code analysis. It's configuration file 'phpstan.neon' is available is this repository. The directories mentioned under scnDirectories option, in phpstan.neon.dist file, are required for running the analysis. These directories belong to prestashop. If you don't have these packages, you'll need to download and extract them first and then make sure their paths are correctly reflected in phpstan.neon.dist file. Once done, we can run the analysis: 
1. First install composer packages using 'composer install'
2. Then run 'vendor/bin/phpstan analyze' to run the analysis. It'll print out any errors detected by PHPStan.

Php-CS-Fixer is being used for fixing php coding standard relared issues. to run it simmply use following command
```php vendor/bin/php-cs-fixer fix --dry-run --diff```

