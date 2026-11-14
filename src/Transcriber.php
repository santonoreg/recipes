<?php
declare(strict_types=1);

namespace App;

/** Απομαγνητοφώνηση με το τοπικό Whisper (tools/transcribe.py). */
final class Transcriber
{
    /**
     * Επιστρέφει κείμενο με χρονοσημάνσεις [mm:ss], ή κενό αν δεν υπάρχει διαθέσιμο Whisper.
     * @param callable(string):void $log
     */
    public static function transcribe(string $wav, callable $log): string
    {
        $python = Tools::python();
        if ($python === null) {
            $log('⚠ Δεν βρέθηκε Python με Whisper (PYTHON_BIN) — συνεχίζω χωρίς απομαγνητοφώνηση.');
            return '';
        }
        $model = Config::get('WHISPER_MODEL', 'large-v3-turbo');
        $device = Config::get('WHISPER_DEVICE', 'auto');
        $log("Απομαγνητοφώνηση με Whisper ($model, $device)…");

        $out = dirname($wav) . '/transcript.txt';
        $res = Proc::run([
            $python, Config::root() . '/tools/transcribe.py', $wav, $out, '--model', $model, '--device', $device,
        ]);
        if ($res['code'] !== 0 || !is_file($out)) {
            $lines = array_values(array_filter(array_map('trim', explode("\n", $res['err']))));
            $log('⚠ Η απομαγνητοφώνηση απέτυχε: ' . mb_substr($lines ? end($lines) : 'άγνωστο σφάλμα', 0, 300));
            return '';
        }
        $text = trim((string) file_get_contents($out));
        @unlink($out);
        $info = json_decode(trim($res['out']), true) ?: [];
        $log(sprintf('  ✓ γλώσσα: %s, %d τμήματα (%s)', $info['language'] ?? '?', $info['segments'] ?? 0, $info['device'] ?? '?'));
        return $text;
    }
}
