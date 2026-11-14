<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/helpers.php';

mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Athens');
App\Config::load(dirname(__DIR__));
date_default_timezone_set(App\Config::get('TIMEZONE', 'Europe/Athens'));
