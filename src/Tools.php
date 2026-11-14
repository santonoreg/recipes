<?php
declare(strict_types=1);

namespace App;

/** Εντοπισμός των εξωτερικών εργαλείων: PHP CLI, Python (Whisper), yt-dlp, ffmpeg. */
final class Tools
{
    /** PHP της γραμμής εντολών, για τον worker στο παρασκήνιο. */
    public static function php(): string
    {
        $custom = Config::get('PHP_BIN');
        if ($custom !== '') {
            return $custom;
        }
        $exe = PHP_OS_FAMILY === 'Windows' ? 'php.exe' : 'php';
        $candidates = [];
        if (PHP_BINARY !== '') {
            if (preg_match('~^php(\d[\d.]*)?(\.exe)?$~i', basename(PHP_BINARY))) {
                return PHP_BINARY;
            }
            $candidates[] = dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . $exe; // php-cgi, php-fpm
        }
        $candidates[] = PHP_BINDIR . DIRECTORY_SEPARATOR . $exe;
        foreach ($candidates as $c) {
            if (is_file($c)) {
                return $c;
            }
        }
        return 'php';
    }

    /** Python με faster-whisper (και προαιρετικά yt-dlp / imageio-ffmpeg). Null αν δεν βρεθεί. */
    public static function python(): ?string
    {
        $custom = Config::get('PYTHON_BIN');
        if ($custom !== '') {
            return $custom;
        }
        $root = Config::root();
        foreach ([
            "$root/.venv/Scripts/python.exe", "$root/.venv/bin/python",
            "$root/old/.venv/Scripts/python.exe", "$root/old/.venv/bin/python",
        ] as $c) {
            if (is_file($c)) {
                return $c;
            }
        }
        return null;
    }

    /** @return list<string> */
    public static function ytdlp(): array
    {
        $custom = Config::get('YTDLP_BIN');
        if ($custom !== '') {
            return [$custom];
        }
        $py = self::python();
        return $py !== null ? [$py, '-m', 'yt_dlp'] : ['yt-dlp'];
    }

    public static function ffmpeg(): string
    {
        $custom = Config::get('FFMPEG_BIN');
        if ($custom !== '') {
            return $custom;
        }
        // το imageio-ffmpeg του Python φέρνει δικό του ffmpeg· το βρίσκουμε μία φορά
        $cache = Config::dataDir() . '/ffmpeg.path';
        $cached = is_file($cache) ? trim((string) file_get_contents($cache)) : '';
        if ($cached !== '' && is_file($cached)) {
            return $cached;
        }
        $py = self::python();
        if ($py !== null) {
            $res = Proc::run([$py, '-c', 'import imageio_ffmpeg; print(imageio_ffmpeg.get_ffmpeg_exe())']);
            $found = trim($res['out']);
            if ($res['code'] === 0 && is_file($found)) {
                file_put_contents($cache, $found);
                return $found;
            }
        }
        return 'ffmpeg';
    }
}
