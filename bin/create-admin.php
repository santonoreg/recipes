<?php
declare(strict_types=1);

// Δημιουργία διαχειριστή ή αλλαγή κωδικού:  php bin/create-admin.php <όνομα χρήστη>
if (PHP_SAPI !== 'cli') {
    exit(1);
}
require dirname(__DIR__) . '/src/bootstrap.php';

$username = trim((string) ($argv[1] ?? ''));
if (!preg_match('~^[\w.@-]{3,40}$~u', $username)) {
    fwrite(STDERR, "Χρήση: php bin/create-admin.php <όνομα χρήστη>  (3–40 χαρακτήρες)\n");
    exit(1);
}
fwrite(STDOUT, "Κωδικός για «{$username}» (τουλάχιστον 8 χαρακτήρες): ");
$password = rtrim((string) fgets(STDIN), "\r\n");
if (mb_strlen($password) < 8) {
    fwrite(STDERR, "Ο κωδικός θέλει τουλάχιστον 8 χαρακτήρες.\n");
    exit(1);
}
App\Auth::createUser($username, $password);
fwrite(STDOUT, "Έτοιμο. Σύνδεση στο /admin\n");
