<?php
declare(strict_types=1);

namespace App;

/** ffmpeg: ήχος, καρέ, αφαίρεση όμοιων καρέ· και αποστολή αρχείων με υποστήριξη Range. */
final class Media
{
    public const MAX_WIDTH = 1280; // αρκετό για να διαβάζονται ετικέτες, χωρίς να φουσκώνει το κόστος

    /** @return array{code:int, out:string, err:string} */
    private static function ffmpeg(string ...$args): array
    {
        return Proc::run([Tools::ffmpeg(), '-hide_banner', '-loglevel', 'error', '-y', ...$args]);
    }

    /** Mono 16 kHz WAV για το Whisper. Null αν το βίντεο δεν έχει ήχο. */
    public static function extractAudio(string $video, string $outWav): ?string
    {
        $res = self::ffmpeg('-i', $video, '-vn', '-ac', '1', '-ar', '16000', $outWav);
        if ($res['code'] !== 0 || !is_file($outWav) || filesize($outWav) < 1000) {
            return null;
        }
        return $outWav;
    }

    /** @return list<array{0:float,1:string}> (δευτερόλεπτο, αρχείο) */
    public static function extractFrames(string $video, string $outDir, float $interval): array
    {
        Config::ensureDir($outDir);
        $res = self::ffmpeg(
            '-i', $video,
            '-vf', sprintf("fps=1/%s,scale='min(%d,iw)':-2", $interval, self::MAX_WIDTH),
            '-q:v', '3',
            $outDir . '/frame_%04d.jpg',
        );
        if ($res['code'] !== 0) {
            throw new \RuntimeException('Το ffmpeg απέτυχε στα καρέ: ' . mb_substr(trim($res['err']), 0, 500));
        }
        $files = glob($outDir . '/frame_*.jpg') ?: [];
        sort($files);
        $frames = [];
        foreach ($files as $i => $f) {
            $frames[] = [$i * $interval + $interval / 2, $f]; // το φίλτρο fps βγάζει το πρώτο καρέ στο ~interval/2
        }
        return $frames;
    }

    /**
     * Κάνει το βίντεο κατάλληλο για αναπαραγωγή στον browser.
     * mp4: μένει ως έχει · webm (εγγραφές οθόνης): remux για σωστή διάρκεια · άλλα: μετατροπή σε mp4.
     */
    public static function normalizeVideo(string $video, string $dir): string
    {
        $ext = strtolower(pathinfo($video, PATHINFO_EXTENSION));
        if ($ext === 'mp4') {
            // το Facebook δίνει συχνά AV1, που δεν παίζει σε παλιότερα iPhone/Safari
            $probe = Proc::run([Tools::ffmpeg(), '-hide_banner', '-i', $video]);
            if (!preg_match('/Video: (\w+)/', $probe['err'], $m) || $m[1] === 'h264') {
                return $video;
            }
        }
        if ($ext === 'webm') {
            $out = $dir . '/video_fixed.webm';
            $res = self::ffmpeg('-i', $video, '-c', 'copy', $out);
        } else {
            $out = $dir . ($ext === 'mp4' ? '/video_h264.mp4' : '/video.mp4');
            $res = self::ffmpeg(
                '-i', $video, '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '23', '-pix_fmt', 'yuv420p',
                '-vf', "scale='min(1280,iw)':-2", '-c:a', 'aac', '-movflags', '+faststart', $out,
            );
        }
        if ($res['code'] !== 0 || !is_file($out) || filesize($out) < 1000) {
            @unlink($out);
            return $video; // κρατάμε το αρχικό· ίσως να μην παίζει σε όλους τους browsers
        }
        @unlink($video);
        return $out;
    }

    /** @return list<int>|null μικρογραφία 48×27 σε αποχρώσεις του γκρι */
    private static function thumb(string $file): ?array
    {
        $img = @imagecreatefromjpeg($file);
        if ($img === false) {
            return null;
        }
        $small = imagecreatetruecolor(48, 27);
        imagecopyresampled($small, $img, 0, 0, 0, 0, 48, 27, imagesx($img), imagesy($img));
        $out = [];
        for ($y = 0; $y < 27; $y++) {
            for ($x = 0; $x < 48; $x++) {
                $rgb = imagecolorat($small, $x, $y);
                $out[] = intdiv(299 * (($rgb >> 16) & 255) + 587 * (($rgb >> 8) & 255) + 114 * ($rgb & 255), 1000);
            }
        }
        return $out;
    }

    /**
     * Πετάει σχεδόν ίδια διαδοχικά καρέ και κρατά έως $max, ομοιόμορφα κατανεμημένα.
     * @param list<array{0:float,1:string}> $frames
     * @return list<array{0:float,1:string}>
     */
    public static function selectFrames(array $frames, int $max, float $threshold = 6.0): array
    {
        $kept = [];
        $last = null;
        foreach ($frames as $frame) {
            $t = self::thumb($frame[1]);
            if ($t === null) {
                continue;
            }
            $differs = true;
            if ($last !== null) {
                $sum = 0;
                foreach ($t as $i => $v) {
                    $sum += abs($v - $last[$i]);
                }
                $differs = $sum / count($t) > $threshold;
            }
            if ($differs) {
                $kept[] = $frame;
                $last = $t;
            }
        }
        $n = count($kept);
        if ($n > $max && $max > 1) {
            $picked = [];
            for ($i = 0; $i < $max; $i++) {
                $picked[(int) round($i * ($n - 1) / ($max - 1))] = true;
            }
            ksort($picked);
            $kept = array_map(fn (int $i) => $kept[$i], array_keys($picked));
        }
        return $kept;
    }

    /** True αν το WAV (16-bit) είναι πρακτικά σιωπή, π.χ. βίντεο σε mute. */
    public static function audioIsSilent(string $wav, int $threshold = 200): bool
    {
        $fh = @fopen($wav, 'rb');
        if ($fh === false) {
            return true;
        }
        $head = (string) fread($fh, 4096);
        $pos = strpos($head, 'data');
        if ($pos === false) {
            fclose($fh);
            return true;
        }
        fseek($fh, $pos + 8);
        $silent = true;
        while (!feof($fh)) {
            $chunk = (string) fread($fh, 1 << 20);
            if (strlen($chunk) < 2) {
                break;
            }
            if (strlen($chunk) % 2) {
                $chunk = substr($chunk, 0, -1);
            }
            $samples = unpack('s*', $chunk) ?: [];
            if ($samples && max(max($samples), -min($samples)) >= $threshold) {
                $silent = false;
                break;
            }
        }
        fclose($fh);
        return $silent;
    }

    public static function fmtTs(float $sec): string
    {
        $s = (int) $sec;
        return sprintf('%02d:%02d', intdiv($s, 60), $s % 60);
    }

    public static function rmTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) && !is_link($path) ? self::rmTree($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    /** Στέλνει αρχείο με υποστήριξη Range, ώστε ο player να μπορεί να κάνει seek. */
    public static function stream(string $file): never
    {
        $mime = match (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
            'mp4', 'm4v' => 'video/mp4',
            'webm' => 'video/webm',
            'mov' => 'video/quicktime',
            'mkv' => 'video/x-matroska',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => 'application/octet-stream',
        };
        $size = filesize($file);
        $start = 0;
        $end = $size - 1;
        if (preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'] ?? '', $m) && ($m[1] !== '' || $m[2] !== '')) {
            if ($m[1] === '') {
                $start = max(0, $size - (int) $m[2]);
            } else {
                $start = (int) $m[1];
                $end = $m[2] !== '' ? min((int) $m[2], $size - 1) : $end;
            }
            if ($start > $end || $start >= $size) {
                http_response_code(416);
                header("Content-Range: bytes */$size");
                exit;
            }
            http_response_code(206);
            header("Content-Range: bytes $start-$end/$size");
        }
        header('Content-Type: ' . $mime);
        header('Accept-Ranges: bytes');
        header('Content-Length: ' . ($end - $start + 1));
        header('Cache-Control: public, max-age=86400');
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $fh = fopen($file, 'rb');
        fseek($fh, $start);
        $left = $end - $start + 1;
        while ($left > 0 && !feof($fh) && !connection_aborted()) {
            $chunk = (string) fread($fh, min(262144, $left));
            echo $chunk;
            flush();
            $left -= strlen($chunk);
        }
        fclose($fh);
        exit;
    }
}
