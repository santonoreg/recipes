<?php
declare(strict_types=1);

namespace App;

/** Λήψη βίντεο + κειμένου post μέσω yt-dlp. */
final class Downloader
{
    /** @return list<array{0:string, 1:list<string>}> Πρώτα χωρίς cookies, μετά με όσα cookies έχουν ρυθμιστεί. */
    private static function attempts(): array
    {
        $attempts = [['χωρίς σύνδεση', []]];
        $file = Config::get('COOKIES_FILE');
        if ($file !== '' && is_file(Config::path($file))) {
            $attempts[] = ['με cookies.txt', ['--cookies', Config::path($file)]];
        }
        $browser = Config::get('COOKIES_BROWSER');
        if ($browser !== '') {
            $attempts[] = ["με cookies από $browser", ['--cookies-from-browser', $browser]];
        }
        return $attempts;
    }

    /**
     * @param callable(string):void $log
     * @return array{video:string, title:string, description:string, uploader:string, url:string, duration:?float}
     */
    public static function download(string $url, string $dir, callable $log): array
    {
        if (!preg_match('~^https?://~i', $url)) {
            throw new DownloadException('Ο σύνδεσμος πρέπει να ξεκινά με http:// ή https://');
        }
        $base = [
            ...Tools::ytdlp(),
            '-o', $dir . '/video.%(ext)s',
            // ≤720p αρκεί για να διαβαστούν υλικά/ετικέτες, και κατεβαίνει γρήγορα
            '-f', 'bv*[height<=720]+ba/b[height<=720]/bv*+ba/b',
            // προτίμηση σε H.264, που παίζει σε όλους τους browsers (το AV1 όχι σε παλιότερα iPhone)
            '--format-sort', 'vcodec:h264',
            '--merge-output-format', 'mp4',
            '--ffmpeg-location', Tools::ffmpeg(),
            '--write-info-json', '--no-write-playlist-metafiles',
            '--no-playlist', '--playlist-items', '1',
            '--quiet', '--no-warnings', '--no-progress',
            '--socket-timeout', '30', '--retries', '2',
        ];

        $reasons = [];
        foreach (self::attempts() as [$label, $extra]) {
            $log("Λήψη ($label)…");
            foreach (glob($dir . '/video.*') ?: [] as $old) {
                @unlink($old);
            }
            $res = Proc::run([...$base, ...$extra, '--', $url]);
            $infoFile = $dir . '/video.info.json';
            $videos = array_values(array_filter(
                glob($dir . '/video.*') ?: [],
                fn (string $f) => !str_ends_with($f, '.json') && !str_ends_with($f, '.part'),
            ));
            if ($res['code'] === 0 && $videos) {
                $info = is_file($infoFile) ? (json_decode((string) file_get_contents($infoFile), true) ?: []) : [];
                @unlink($infoFile);
                usort($videos, fn ($a, $b) => (str_ends_with($b, '.mp4') <=> str_ends_with($a, '.mp4')));
                return [
                    'video' => $videos[0],
                    'title' => (string) ($info['title'] ?? ''),
                    'description' => (string) ($info['description'] ?? ''),
                    'uploader' => (string) ($info['uploader'] ?? $info['channel'] ?? ''),
                    'url' => (string) ($info['webpage_url'] ?? $url),
                    'duration' => isset($info['duration']) ? (float) $info['duration'] : null,
                ];
            }
            $lines = array_values(array_filter(array_map('trim', explode("\n", $res['err']))));
            $msg = str_replace('ERROR: ', '', $lines ? end($lines) : 'άγνωστο σφάλμα (κωδικός ' . $res['code'] . ')');
            $reasons[] = "$label: $msg";
            $log("  ✗ $msg");
        }
        throw new DownloadException("Η λήψη απέτυχε.\n" . implode("\n", $reasons));
    }
}
