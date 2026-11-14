<?php
declare(strict_types=1);

namespace App;

/** Δρομολόγηση και ελεγκτές: δημόσιο frontend + backend διαχείρισης. */
final class App
{
    private static string $base = '';

    public static function base(): string
    {
        return self::$base;
    }

    public static function run(): void
    {
        $uri = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
        // δουλεύει και σε υποφάκελο (π.χ. /recipes/) και όταν ο ριζικός φάκελος προωθεί στο public/
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        if ($base !== '' && !str_starts_with($uri, $base . '/') && $uri !== $base) {
            $base = (string) preg_replace('~/public$~', '', $base);
        }
        self::$base = $base;
        $path = '/' . trim(substr($uri, strlen($base)), '/');
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: same-origin');

        try {
            self::dispatch($method, $path);
        } catch (\Throwable $e) {
            error_log((string) $e);
            http_response_code(500);
            self::view('error', ['title' => 'Σφάλμα', 'message' => 'Κάτι πήγε στραβά. Δοκίμασε ξανά.']);
        }
    }

    private static function dispatch(string $method, string $path): void
    {
        $get = $method === 'GET' || $method === 'HEAD';
        $post = $method === 'POST';

        // ---------- δημόσιο ----------
        if ($get && $path === '/') {
            self::view('home', ['title' => 'Συνταγές', 'recipes' => Recipes::all()]);
            return;
        }
        if ($get && preg_match('~^/recipe/(\d+)$~', $path, $m)) {
            $row = Recipes::find((int) $m[1]) ?? self::notFound();
            self::view('recipe', ['title' => $row['title'], 'row' => $row, 'recipe' => $row['recipe']]);
            return;
        }
        if ($get && preg_match('~^/recipe/(\d+)/pdf$~', $path, $m)) {
            $row = Recipes::find((int) $m[1]) ?? self::notFound();
            $pdf = Pdf::render($row);
            $name = Pdf::fileName((string) $row['title']);
            header('Content-Type: application/pdf');
            header('Content-Length: ' . strlen($pdf));
            header("Content-Disposition: attachment; filename=\"recipe-{$row['id']}.pdf\"; filename*=UTF-8''" . rawurlencode($name));
            echo $pdf;
            return;
        }
        if ($get && preg_match('~^/media/(\d+)/(video|poster)$~', $path, $m)) {
            $row = Recipes::find((int) $m[1]) ?? self::notFound();
            $file = Recipes::filePath($row, $m[2]) ?? self::notFound();
            Media::stream($file);
        }

        // ---------- σύνδεση ----------
        if ($path === '/admin/setup') {
            self::setup($post);
            return;
        }
        if ($path === '/admin/login') {
            self::login($post);
            return;
        }
        if (!str_starts_with($path, '/admin')) {
            self::notFound();
        }

        // ---------- διαχείριση (μόνο συνδεδεμένος) ----------
        if (!Auth::check()) {
            if ($get && $path === '/admin') {
                self::redirect(Auth::hasUsers() ? 'admin/login' : 'admin/setup');
            }
            http_response_code(401);
            self::json(['error' => 'Χρειάζεται σύνδεση.']);
        }
        if ($post && !Auth::verifyCsrf()) {
            http_response_code(419);
            self::json(['error' => 'Η φόρμα έληξε. Φόρτωσε ξανά τη σελίδα.']);
        }

        if ($get && $path === '/admin') {
            self::view('admin/dashboard', [
                'title' => 'Διαχείριση',
                'recipes' => Recipes::all(),
                'jobs' => Db::all('SELECT * FROM jobs ORDER BY id DESC LIMIT 15'),
                'whisper' => Tools::python() !== null,
                'apiKey' => Config::get('ANTHROPIC_API_KEY') !== '',
            ]);
            return;
        }
        if ($post && $path === '/admin/logout') {
            Auth::logout();
            self::redirect('');
        }
        if ($post && $path === '/admin/upload') {
            self::uploadChunk();
        }
        if ($post && $path === '/admin/jobs') {
            self::createJob();
        }
        if ($get && $path === '/admin/jobs') {
            self::json(['jobs' => array_map(self::jobView(...), Db::all('SELECT * FROM jobs ORDER BY id DESC LIMIT 15'))]);
        }
        if ($post && preg_match('~^/admin/jobs/(\d+)/delete$~', $path, $m)) {
            Db::run("DELETE FROM jobs WHERE id = ? AND status IN ('done','error')", [(int) $m[1]]);
            self::json(['ok' => true]);
        }
        if ($post && preg_match('~^/admin/recipes/(\d+)/delete$~', $path, $m)) {
            $ok = Recipes::delete((int) $m[1]);
            if (($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json') {
                self::json(['ok' => $ok]);
            }
            self::redirect('admin');
        }
        self::notFound();
    }

    // ---------- ενέργειες ----------

    private static function setup(bool $post): void
    {
        if (Auth::hasUsers()) {
            self::redirect('admin/login');
        }
        $error = '';
        if (!Auth::isLocalRequest()) {
            $error = 'Ο πρώτος διαχειριστής δημιουργείται μόνο από τον ίδιο τον υπολογιστή (localhost) '
                . 'ή με την εντολή: php bin/create-admin.php';
        } elseif ($post) {
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            if (!Auth::verifyCsrf()) {
                $error = 'Η φόρμα έληξε. Δοκίμασε ξανά.';
            } elseif (!preg_match('~^[\w.@-]{3,40}$~u', $username)) {
                $error = 'Το όνομα χρήστη θέλει 3–40 χαρακτήρες (γράμματα, αριθμοί, . _ - @).';
            } elseif (mb_strlen($password) < 8) {
                $error = 'Ο κωδικός θέλει τουλάχιστον 8 χαρακτήρες.';
            } elseif ($password !== (string) ($_POST['password2'] ?? '')) {
                $error = 'Οι δύο κωδικοί δεν ταιριάζουν.';
            } else {
                Auth::createUser($username, $password);
                Auth::attempt($username, $password);
                self::redirect('admin');
            }
        }
        self::view('admin/setup', ['title' => 'Πρώτη ρύθμιση', 'error' => $error, 'local' => Auth::isLocalRequest()]);
    }

    private static function login(bool $post): void
    {
        if (Auth::check()) {
            self::redirect('admin');
        }
        if (!Auth::hasUsers()) {
            self::redirect('admin/setup');
        }
        $error = '';
        if ($post) {
            $error = Auth::verifyCsrf()
                ? Auth::attempt(trim((string) ($_POST['username'] ?? '')), (string) ($_POST['password'] ?? ''))
                : 'Η φόρμα έληξε. Δοκίμασε ξανά.';
            if ($error === null) {
                self::redirect('admin');
            }
        }
        self::view('admin/login', ['title' => 'Σύνδεση', 'error' => (string) $error]);
    }

    /** Τμηματικό ανέβασμα βίντεο (κομμάτια ~1 MB), ώστε να μη μας κόβουν τα όρια του php.ini. */
    private static function uploadChunk(): never
    {
        $uid = (string) ($_GET['uid'] ?? '');
        $ext = strtolower((string) ($_GET['ext'] ?? ''));
        $index = (int) ($_GET['i'] ?? 0);
        if (!preg_match('~^[a-f0-9]{32}$~', $uid) || !in_array($ext, ['mp4', 'm4v', 'mov', 'webm', 'mkv', 'avi'], true)) {
            http_response_code(400);
            self::json(['error' => 'Μη έγκυρο αρχείο. Δεκτά: mp4, mov, webm, mkv, avi.']);
        }
        $file = Config::ensureDir(Config::dataDir() . '/uploads') . "/$uid.$ext";
        if ($index === 0) {
            @unlink($file);
        }
        $in = fopen('php://input', 'rb');
        $out = fopen($file, 'ab');
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);
        self::json(['ok' => true, 'size' => filesize($file)]);
    }

    private static function createJob(): never
    {
        $url = trim((string) ($_POST['url'] ?? ''));
        $text = trim((string) ($_POST['text'] ?? ''));
        $upload = (string) ($_POST['upload'] ?? '');
        if ($upload !== '') {
            if (!preg_match('~^[a-f0-9]{32}\.[a-z0-9]{3,4}$~', $upload) || !is_file(Config::dataDir() . "/uploads/$upload")) {
                http_response_code(400);
                self::json(['error' => 'Το ανέβασμα δεν ολοκληρώθηκε.']);
            }
            $id = Pipeline::createJob('upload', $url, $upload, $text);
        } elseif (preg_match('~^https?://\S+$~i', $url)) {
            $id = Pipeline::createJob('url', $url, '', $text);
        } else {
            http_response_code(400);
            self::json(['error' => 'Δώσε έναν σύνδεσμο που ξεκινά με http:// ή https://']);
        }
        Pipeline::start($id);
        self::json(['ok' => true, 'id' => $id]);
    }

    /** @param array<string,mixed> $job */
    public static function jobView(array $job): array
    {
        // αν ο worker «πέθανε» στη μέση, μην το δείχνουμε για πάντα σαν να τρέχει
        $stale = in_array($job['status'], ['queued', 'running'], true)
            && time() - (int) strtotime((string) $job['updated_at']) > 1800;
        if ($stale) {
            Db::run("UPDATE jobs SET status = 'error', error = ? WHERE id = ?", ['Η επεξεργασία διακόπηκε.', $job['id']]);
            $job['status'] = 'error';
            $job['error'] = 'Η επεξεργασία διακόπηκε.';
        }
        return [
            'id' => (int) $job['id'],
            'kind' => $job['kind'],
            'source' => $job['source_url'] !== '' ? $job['source_url'] : 'Ανεβασμένο βίντεο',
            'status' => $job['status'],
            'log' => $job['log'],
            'error' => $job['error'],
            'recipe_id' => $job['recipe_id'] !== null ? (int) $job['recipe_id'] : null,
            'created_at' => $job['created_at'],
        ];
    }

    // ---------- βοηθητικά ----------

    /** @param array<string,mixed> $data */
    public static function view(string $template, array $data = []): void
    {
        $data['isAdmin'] = Auth::check();
        extract($data, EXTR_SKIP);
        ob_start();
        require Config::root() . "/templates/$template.php";
        $content = (string) ob_get_clean();
        require Config::root() . '/templates/layout.php';
    }

    private static function redirect(string $path): never
    {
        header('Location: ' . url($path), true, 303);
        exit;
    }

    /** @param array<string,mixed> $data */
    private static function json(array $data): never
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private static function notFound(): never
    {
        http_response_code(404);
        self::view('error', ['title' => 'Δεν βρέθηκε', 'message' => 'Η σελίδα που ζήτησες δεν υπάρχει.']);
        exit;
    }
}
