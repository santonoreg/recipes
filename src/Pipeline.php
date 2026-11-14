<?php
declare(strict_types=1);

namespace App;

/** Ροή: (λήψη | ανεβασμένο βίντεο) → ήχος/καρέ → Whisper → Claude → συνταγή στη βάση. */
final class Pipeline
{
    public static function createJob(string $kind, string $url, string $uploadFile, string $text): int
    {
        $now = Db::now();
        return Db::insert(
            'INSERT INTO jobs (kind, source_url, upload_file, extra_text, created_at, updated_at) VALUES (?,?,?,?,?,?)',
            [$kind, $url, $uploadFile, $text, $now, $now],
        );
    }

    /** Ξεκινά τον worker στο παρασκήνιο, ώστε η σελίδα να μην περιμένει. */
    public static function start(int $jobId): void
    {
        $logs = Config::ensureDir(Config::dataDir() . '/logs');
        Proc::spawn([Tools::php(), Config::root() . '/bin/worker.php', (string) $jobId], "$logs/job-$jobId.log");
    }

    public static function newWorkdir(): string
    {
        $base = Config::outputDir() . '/' . date('Ymd-His');
        $dir = $base;
        for ($i = 2; is_dir($dir); $i++) {
            $dir = "$base-$i";
        }
        mkdir($dir, 0775, true);
        return $dir;
    }

    public static function run(int $jobId): void
    {
        $job = Db::one('SELECT * FROM jobs WHERE id = ?', [$jobId]);
        if (!$job || $job['status'] !== 'queued') {
            return;
        }
        Db::run("UPDATE jobs SET status = 'running', updated_at = ? WHERE id = ?", [Db::now(), $jobId]);
        $log = static function (string $line) use ($jobId): void {
            Db::run('UPDATE jobs SET log = log || ?, updated_at = ? WHERE id = ?', [$line . "\n", Db::now(), $jobId]);
        };

        $dir = self::newWorkdir();
        try {
            $recipeId = self::process($job, $dir, $log);
            Db::run(
                "UPDATE jobs SET status = 'done', recipe_id = ?, updated_at = ? WHERE id = ?",
                [$recipeId, Db::now(), $jobId],
            );
        } catch (\Throwable $e) {
            Media::rmTree($dir);
            $log('✗ ' . $e->getMessage());
            Db::run(
                "UPDATE jobs SET status = 'error', error = ?, updated_at = ? WHERE id = ?",
                [$e->getMessage(), Db::now(), $jobId],
            );
        } finally {
            if ($job['upload_file'] !== '') {
                @unlink(Config::dataDir() . '/uploads/' . basename((string) $job['upload_file']));
            }
        }
    }

    /**
     * @param array<string,mixed> $job
     * @param callable(string):void $log
     */
    private static function process(array $job, string $dir, callable $log): int
    {
        $extra = trim((string) $job['extra_text']);
        $meta = ['url' => (string) $job['source_url'], 'title' => '', 'uploader' => '', 'duration' => null];
        $postText = $extra;

        if ($job['kind'] === 'url') {
            $dl = Downloader::download((string) $job['source_url'], $dir, $log);
            $video = $dl['video'];
            $log('  ✓ κατέβηκε: ' . basename($video));
            $meta = ['url' => $dl['url'], 'title' => $dl['title'], 'uploader' => $dl['uploader'], 'duration' => $dl['duration']];
            $postText = self::postText($dl['title'], $dl['description'], $extra);
        } else {
            $src = Config::dataDir() . '/uploads/' . basename((string) $job['upload_file']);
            if (!is_file($src)) {
                throw new \RuntimeException('Το ανεβασμένο βίντεο δεν βρέθηκε.');
            }
            $ext = strtolower(pathinfo($src, PATHINFO_EXTENSION)) ?: 'mp4';
            $video = "$dir/video.$ext";
            rename($src, $video);
        }

        $log('Εξαγωγή ήχου και καρέ…');
        $video = Media::normalizeVideo($video, $dir);
        $audio = Media::extractAudio($video, "$dir/audio.wav");
        $frames = Media::extractFrames($video, "$dir/frames", Config::float('FRAME_INTERVAL', 3.0));
        $log(sprintf('  ✓ %d καρέ, ήχος: %s', count($frames), $audio ? 'ναι' : 'όχι'));

        $transcript = '';
        if ($audio !== null && !Media::audioIsSilent($audio)) {
            $transcript = Transcriber::transcribe($audio, $log);
        } else {
            $log('Δεν υπάρχει ομιλία — συνεχίζω με κείμενο και εικόνα.');
        }

        $selected = Media::selectFrames($frames, Config::int('MAX_FRAMES', 30));
        if (trim($postText) === '' && trim($transcript) === '' && !$selected) {
            throw new \RuntimeException('Δεν βρέθηκε τίποτα να αναλυθεί (ούτε κείμενο, ούτε ήχος, ούτε εικόνα).');
        }

        $recipe = Extractor::extract($postText, $transcript, $selected, $log);
        if (empty($recipe['is_recipe'])) {
            throw new \RuntimeException('Το μοντέλο δεν βρήκε συνταγή σε αυτό το περιεχόμενο.');
        }

        // φωτογραφία συνταγής: το καρέ που διάλεξε το μοντέλο, αλλιώς το τελευταίο
        $poster = '';
        if ($selected) {
            $cover = (int) ($recipe['cover_frame'] ?? -1);
            $pick = $selected[$cover] ?? $selected[count($selected) - 1];
            if (copy($pick[1], "$dir/poster.jpg")) {
                $poster = 'poster.jpg';
            }
        }
        unset($recipe['cover_frame']);

        // κρατάμε μόνο ό,τι χρειάζεται το site
        Media::rmTree("$dir/frames");
        @unlink("$dir/audio.wav");

        $id = Recipes::create($recipe, [
            'dir' => basename($dir),
            'video_file' => basename($video),
            'poster_file' => $poster,
            'source_url' => $meta['url'],
            'source_title' => $meta['title'],
            'uploader' => $meta['uploader'],
            'duration' => $meta['duration'],
            'post_text' => $postText,
            'transcript' => $transcript,
        ]);
        $log('✓ Έτοιμη: ' . $recipe['title']);
        return $id;
    }

    private static function postText(string $title, string $description, string $extra): string
    {
        $parts = [];
        $title = trim($title);
        $description = trim($description);
        // στο Facebook ο τίτλος είναι συνήθως η αρχή της περιγραφής
        if ($title !== '' && !str_starts_with($description, mb_substr($title, 0, 40))) {
            $parts[] = $title;
        }
        if ($description !== '') {
            $parts[] = $description;
        }
        if ($extra !== '') {
            $parts[] = $extra;
        }
        return implode("\n\n", $parts);
    }
}
