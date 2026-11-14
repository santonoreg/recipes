<?php
declare(strict_types=1);

// Εισαγωγή συνταγών από την παλιά εφαρμογή Python:  php bin/import-old.php [φάκελος output, προεπιλογή old/output]
if (PHP_SAPI !== 'cli') {
    exit(1);
}
require dirname(__DIR__) . '/src/bootstrap.php';

use App\Config;
use App\Db;
use App\Recipes;

$source = rtrim(str_replace('\\', '/',$argv[1] ?? Config::root() . '/old/output'), '/');
if (!is_dir($source)) {
    fwrite(STDERR, "Δεν βρέθηκε ο φάκελος: $source\n");
    exit(1);
}
$imported = 0;
foreach (glob($source . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
    $name = basename($dir);
    $recipe = is_file("$dir/recipe.json") ? json_decode((string) file_get_contents("$dir/recipe.json"), true) : null;
    if (!preg_match('~^(\d{4})(\d\d)(\d\d)-(\d\d)(\d\d)(\d\d)$~', $name, $m) || !is_array($recipe) || empty($recipe['is_recipe'])) {
        echo "– $name: παράλειψη (δεν έχει συνταγή)\n";
        continue;
    }
    if (Db::one('SELECT id FROM recipes WHERE dir = ?', [$name])) {
        echo "– $name: υπάρχει ήδη\n";
        continue;
    }
    $target = Config::outputDir() . '/' . $name;
    Config::ensureDir($target);
    $video = '';
    foreach (['video.mp4', 'video.webm'] as $candidate) {
        if (is_file("$dir/$candidate") && copy("$dir/$candidate", "$target/$candidate")) {
            $video = basename(App\Media::normalizeVideo("$target/$candidate", $target)); // AV1 → H.264
            break;
        }
    }
    // φωτογραφία: ένα καρέ κοντά στο τέλος, όπου συνήθως φαίνεται το έτοιμο πιάτο
    $poster = '';
    $frames = glob("$dir/frames/*.jpg") ?: [];
    sort($frames);
    if ($frames && copy($frames[(int) floor((count($frames) - 1) * 0.9)], "$target/poster.jpg")) {
        $poster = 'poster.jpg';
    }
    $info = is_file("$dir/source.json") ? (json_decode((string) file_get_contents("$dir/source.json"), true) ?: []) : [];
    Recipes::create($recipe, [
        'dir' => $name,
        'video_file' => $video,
        'poster_file' => $poster,
        'source_url' => (string) ($info['url'] ?? ''),
        'source_title' => (string) ($info['title'] ?? ''),
        'uploader' => (string) ($info['uploader'] ?? ''),
        'duration' => $info['duration'] ?? null,
        'post_text' => is_file("$dir/post.txt") ? (string) file_get_contents("$dir/post.txt") : '',
        'transcript' => is_file("$dir/transcript.txt") ? (string) file_get_contents("$dir/transcript.txt") : '',
        'created_at' => "$m[1]-$m[2]-$m[3] $m[4]:$m[5]:$m[6]",
    ]);
    $imported++;
    echo "✓ $name: {$recipe['title']}\n";
}
echo "Εισήχθησαν $imported συνταγές.\n";
