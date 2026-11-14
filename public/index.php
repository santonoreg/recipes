<?php
declare(strict_types=1);

// με τον ενσωματωμένο server της PHP (php -S) τα στατικά αρχεία σερβίρονται απευθείας
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . rawurldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    if (is_file($file) && !str_ends_with($file, '.php')) {
        return false;
    }
}

if (!is_file(dirname(__DIR__) . '/vendor/autoload.php')) {
    http_response_code(500);
    exit('Λείπουν οι βιβλιοθήκες: τρέξε "composer install" στον φάκελο της εφαρμογής.');
}
require dirname(__DIR__) . '/src/bootstrap.php';

App\App::run();
