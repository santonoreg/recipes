<?php
declare(strict_types=1);

// Επεξεργασία μίας εργασίας στο παρασκήνιο:  php bin/worker.php <job id>
if (PHP_SAPI !== 'cli') {
    exit(1);
}
require dirname(__DIR__) . '/src/bootstrap.php';

set_time_limit(0);
ignore_user_abort(true);
$id = (int) ($argv[1] ?? 0);
if ($id <= 0) {
    fwrite(STDERR, "Χρήση: php bin/worker.php <job id>\n");
    exit(1);
}
App\Pipeline::run($id);
