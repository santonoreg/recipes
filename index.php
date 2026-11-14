<?php
declare(strict_types=1);

// Είσοδος από τον ριζικό φάκελο, για servers όπου τα .htaccess / mod_rewrite δεν είναι ενεργά.
// Όταν το rewrite δουλεύει, το .htaccess στέλνει τα πάντα στο public/ και αυτό το αρχείο δεν εκτελείται ποτέ.
define('APP_ROOT_FRONT', true);
require __DIR__ . '/public/index.php';
