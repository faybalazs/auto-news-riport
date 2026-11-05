<?php
declare(strict_types=1);

/**
 * Közös indítás: automatikus betöltés, időzóna, konfiguráció, tárhely.
 * A config.php helye: elsőként a webgyökér fölötti "xperion-config.php",
 * különben az alkalmazás mappájában lévő "config.php".
 */

use Xperion\App;
use Xperion\Config;

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('Az alkalmazáshoz PHP 8.1 vagy újabb kell (jelenleg: ' . PHP_VERSION . ').');
}

define('XPERION_ROOT', dirname(__DIR__));
define('XPERION_VERSION', '0.19.0');
/** A kiadás dátuma (a lábléc mutatja): a CHANGELOG.md adott sorával egyezik. */
define('XPERION_RELEASE', '2026-11-05');

require __DIR__ . '/autoload.php';

ini_set('display_errors', '0');
error_reporting(E_ALL);

$config = Config::load([
    dirname(XPERION_ROOT) . '/xperion-config.php',
    XPERION_ROOT . '/config.php',
]);
date_default_timezone_set($config->string('timezone', 'Europe/Budapest') ?: 'Europe/Budapest');

$storage = $config->string('storage_dir') ?: XPERION_ROOT . '/storage';
if (!is_dir($storage)) {
    @mkdir($storage, 0750, true);
}
if (is_dir($storage) && !is_file($storage . '/.htaccess')) {
    @file_put_contents($storage . '/.htaccess', "Require all denied\nDeny from all\n");
    @file_put_contents($storage . '/index.html', '');
}

$config->setUploadedKeyFile($storage . '/google-service-account.json');

return new App($config, $storage);
