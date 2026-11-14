<?php
declare(strict_types=1);

namespace App;

/** Εκτέλεση εξωτερικών προγραμμάτων (yt-dlp, ffmpeg, python) χωρίς shell. */
final class Proc
{
    /**
     * @param list<string> $cmd
     * @return array{code:int, out:string, err:string}
     */
    public static function run(array $cmd, ?string $cwd = null): array
    {
        $errFile = tempnam(sys_get_temp_dir(), 'rg');
        $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        $proc = @proc_open(
            $cmd,
            [0 => ['file', $null, 'r'], 1 => ['pipe', 'w'], 2 => ['file', $errFile, 'w']],
            $pipes,
            $cwd,
            null,
            ['bypass_shell' => true],
        );
        if (!is_resource($proc)) {
            @unlink($errFile);
            return ['code' => 127, 'out' => '', 'err' => 'Δεν εκτελέστηκε: ' . $cmd[0]];
        }
        $out = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $code = proc_close($proc);
        $err = (string) @file_get_contents($errFile);
        @unlink($errFile);
        return ['code' => $code, 'out' => $out, 'err' => $err];
    }

    /**
     * Ξεκινά διεργασία στο παρασκήνιο και επιστρέφει αμέσως.
     * @param list<string> $cmd
     */
    public static function spawn(array $cmd, string $logFile): void
    {
        $line = implode(' ', array_map('escapeshellarg', $cmd));
        $log = escapeshellarg($logFile);
        if (PHP_OS_FAMILY === 'Windows') {
            pclose(popen("start \"\" /B $line > $log 2>&1", 'r'));
        } else {
            exec("nohup $line > $log 2>&1 &");
        }
    }
}
