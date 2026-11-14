<?php
declare(strict_types=1);

namespace App;

/** Ρυθμίσεις από το .env (ίδια κλειδιά με την παλιά εφαρμογή Python, συν μερικά νέα). */
final class Config
{
    private static string $root = '';
    /** @var array<string,string> */
    private static array $values = [];

    public static function load(string $root): void
    {
        self::$root = rtrim(str_replace('\\', '/', $root), '/');
        self::$values = [];
        $file = self::$root . '/.env';
        if (!is_file($file)) {
            return;
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]) {
                $value = substr($value, 1, -1);
            }
            self::$values[$key] = $value;
        }
    }

    public static function root(): string
    {
        return self::$root;
    }

    /** Μεταβλητή περιβάλλοντος αν υπάρχει, αλλιώς το .env, αλλιώς η προεπιλογή. */
    public static function get(string $key, string $default = ''): string
    {
        $env = getenv($key);
        if ($env !== false && $env !== '') {
            return $env;
        }
        $value = self::$values[$key] ?? '';
        return $value !== '' ? $value : $default;
    }

    public static function int(string $key, int $default): int
    {
        $v = self::get($key);
        return is_numeric($v) ? (int) $v : $default;
    }

    public static function float(string $key, float $default): float
    {
        $v = self::get($key);
        return is_numeric($v) ? (float) $v : $default;
    }

    /** Απόλυτη διαδρομή· οι σχετικές λύνονται ως προς τον φάκελο της εφαρμογής. */
    public static function path(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        if ($path !== '' && ($path[0] === '/' || preg_match('~^[A-Za-z]:/~', $path))) {
            return rtrim($path, '/');
        }
        return self::$root . '/' . trim($path, '/');
    }

    /** Φάκελος όπου μπαίνουν τα βίντεο/εικόνες κάθε συνταγής (OUTPUT_DIR). */
    public static function outputDir(): string
    {
        return self::ensureDir(self::path(self::get('OUTPUT_DIR', 'output')));
    }

    public static function dataDir(): string
    {
        return self::ensureDir(self::path(self::get('DATA_DIR', 'data')));
    }

    public static function dbPath(): string
    {
        $custom = self::get('DB_PATH');
        return $custom !== '' ? self::path($custom) : self::dataDir() . '/recipes.sqlite';
    }

    public static function ensureDir(string $dir): string
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        return $dir;
    }
}
