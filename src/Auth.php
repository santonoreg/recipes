<?php
declare(strict_types=1);

namespace App;

/** Σύνδεση διαχειριστή: session, CSRF, περιορισμός αποτυχημένων προσπαθειών. */
final class Auth
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW = 900; // 15 λεπτά

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $secure = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        session_name('recipes_sid');
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => $secure, 'path' => '/']);
        session_start();
    }

    /** Έχει cookie συνεδρίας; (ώστε οι δημόσιες σελίδες να μην ανοίγουν session χωρίς λόγο) */
    public static function check(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE && empty($_COOKIE['recipes_sid'])) {
            return false;
        }
        self::start();
        // ισχύει μόνο όσο ο λογαριασμός υπάρχει ακόμη
        return !empty($_SESSION['user_id'])
            && Db::one('SELECT 1 AS ok FROM users WHERE id = ?', [$_SESSION['user_id']]) !== null;
    }

    public static function username(): string
    {
        return (string) ($_SESSION['username'] ?? '');
    }

    public static function hasUsers(): bool
    {
        return (int) Db::one('SELECT COUNT(*) AS n FROM users')['n'] > 0;
    }

    public static function createUser(string $username, string $password): void
    {
        Db::run(
            'INSERT INTO users (username, password_hash, created_at) VALUES (?,?,?)
             ON CONFLICT(username) DO UPDATE SET password_hash = excluded.password_hash',
            [$username, password_hash($password, PASSWORD_DEFAULT), Db::now()],
        );
    }

    /** Επιστρέφει null αν η σύνδεση πέτυχε, αλλιώς το μήνυμα σφάλματος. */
    public static function attempt(string $username, string $password): ?string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $since = time() - self::WINDOW;
        Db::run('DELETE FROM login_attempts WHERE at < ?', [$since]);
        $fails = (int) Db::one('SELECT COUNT(*) AS n FROM login_attempts WHERE ip = ?', [$ip])['n'];
        if ($fails >= self::MAX_ATTEMPTS) {
            return 'Πολλές αποτυχημένες προσπάθειες. Δοκίμασε ξανά σε 15 λεπτά.';
        }
        $user = Db::one('SELECT * FROM users WHERE username = ?', [$username]);
        // επαλήθευση και όταν δεν υπάρχει ο χρήστης, ώστε ο χρόνος απόκρισης να μην τον προδίδει
        $hash = $user['password_hash'] ?? '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
        if (!password_verify($password, (string) $hash) || !$user) {
            Db::run('INSERT INTO login_attempts (ip, at) VALUES (?,?)', [$ip, time()]);
            return 'Λάθος όνομα χρήστη ή κωδικός.';
        }
        self::start();
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['username'] = (string) $user['username'];
        Db::run('DELETE FROM login_attempts WHERE ip = ?', [$ip]);
        return null;
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        session_destroy();
        setcookie('recipes_sid', '', ['expires' => 1, 'path' => '/']);
    }

    public static function csrf(): string
    {
        self::start();
        return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function verifyCsrf(): bool
    {
        self::start();
        $sent = (string) ($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        return $sent !== '' && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $sent);
    }

    /** Η πρώτη δημιουργία διαχειριστή από τον browser επιτρέπεται μόνο από τον ίδιο τον υπολογιστή. */
    public static function isLocalRequest(): bool
    {
        return in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
            && empty($_SERVER['HTTP_X_FORWARDED_FOR']);
    }
}
