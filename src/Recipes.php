<?php
declare(strict_types=1);

namespace App;

/** Αποθήκη συνταγών (SQLite + φάκελος με βίντεο/φωτογραφία ανά συνταγή). */
final class Recipes
{
    /** @return list<array<string,mixed>> */
    public static function all(): array
    {
        return array_map(self::hydrate(...), Db::all('SELECT * FROM recipes ORDER BY created_at DESC, id DESC'));
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        $row = Db::one('SELECT * FROM recipes WHERE id = ?', [$id]);
        return $row ? self::hydrate($row) : null;
    }

    /** @param array<string,mixed> $row */
    private static function hydrate(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['recipe'] = json_decode((string) $row['data'], true) ?: [];
        return $row;
    }

    /**
     * @param array<string,mixed> $recipe
     * @param array<string,mixed> $meta dir, video_file, poster_file, source_url, source_title, uploader, duration, post_text, transcript, created_at
     */
    public static function create(array $recipe, array $meta): int
    {
        return Db::insert(
            'INSERT INTO recipes (title, description, data, dir, video_file, poster_file, source_url, source_title,
                                  uploader, duration, post_text, transcript, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                trim((string) ($recipe['title'] ?? '')) ?: 'Συνταγή',
                trim((string) ($recipe['description'] ?? '')),
                json_encode($recipe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $meta['dir'],
                $meta['video_file'] ?? '',
                $meta['poster_file'] ?? '',
                $meta['source_url'] ?? '',
                $meta['source_title'] ?? '',
                $meta['uploader'] ?? '',
                $meta['duration'] ?? null,
                $meta['post_text'] ?? '',
                $meta['transcript'] ?? '',
                $meta['created_at'] ?? Db::now(),
            ],
        );
    }

    /** Σβήνει τη συνταγή μαζί με τα αρχεία της. */
    public static function delete(int $id): bool
    {
        $row = Db::one('SELECT dir FROM recipes WHERE id = ?', [$id]);
        if (!$row) {
            return false;
        }
        Db::run('DELETE FROM recipes WHERE id = ?', [$id]);
        Db::run('UPDATE jobs SET recipe_id = NULL WHERE recipe_id = ?', [$id]);
        $dir = self::dirPath((string) $row['dir']);
        if ($dir !== null) {
            Media::rmTree($dir);
        }
        return true;
    }

    /** Απόλυτη διαδρομή του φακέλου μιας συνταγής, μόνο αν βρίσκεται πράγματι μέσα στο OUTPUT_DIR. */
    public static function dirPath(string $dir): ?string
    {
        if ($dir === '' || !preg_match('~^[A-Za-z0-9._-]+$~', $dir) || $dir === '.' || $dir === '..') {
            return null;
        }
        $path = Config::outputDir() . '/' . $dir;
        return is_dir($path) ? $path : null;
    }

    /** Διαδρομή αρχείου (βίντεο ή φωτογραφία) μιας συνταγής, ή null αν λείπει. */
    public static function filePath(array $recipe, string $kind): ?string
    {
        $file = (string) ($kind === 'video' ? $recipe['video_file'] : $recipe['poster_file']);
        $dir = self::dirPath((string) $recipe['dir']);
        if ($dir === null || $file === '' || basename($file) !== $file) {
            return null;
        }
        $path = $dir . '/' . $file;
        return is_file($path) ? $path : null;
    }
}
