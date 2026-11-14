<?php

namespace App\Libraries;

use RuntimeException;

/** Menjalankan app/Bridge/moodle_bridge.php (CLI) dan mengembalikan hasilnya. */
class MoodleBridge
{
    private const MARKER = '@@CBTJSON@@';

    /**
     * @return list<array<string, mixed>> hasil per item
     */
    public static function call(string $action, array $payload): array
    {
        $command = [(string) env('cbt.phpBin', '/usr/bin/php'), APPPATH . 'Bridge/moodle_bridge.php'];
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (! is_resource($process)) {
            throw new RuntimeException('Jembatan Moodle tidak bisa dijalankan.');
        }

        fwrite($pipes[0], json_encode($payload + [
            'action' => $action,
            'config' => (string) env('moodle.configPath', '/var/www/moodle/config.php'),
        ]));
        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        $pos    = strrpos($stdout, self::MARKER);
        $result = $pos === false ? null : json_decode(substr($stdout, $pos + strlen(self::MARKER)), true);
        if (! is_array($result)) {
            log_message('error', 'MoodleBridge: ' . substr($stdout . ' ' . $stderr, 0, 2000));

            throw new RuntimeException('Moodle tidak memberi jawaban yang bisa dibaca.');
        }
        if (empty($result['ok'])) {
            throw new RuntimeException((string) ($result['error'] ?? 'Aksi Moodle gagal.'));
        }

        return $result['items'] ?? [];
    }

    /**
     * Jalankan cron Moodle sekali (tanpa keep-alive) dan kembalikan [berhasil, baris terakhir keluaran].
     * Cron Moodle sendiri juga berjalan tiap menit; Moodle memakai kunci sehingga tidak bentrok.
     */
    public static function cron(): array
    {
        $dir     = dirname((string) env('moodle.configPath', '/var/www/moodle/config.php'));
        $command = [(string) env('cbt.phpBin', '/usr/bin/php'), $dir . '/admin/cli/cron.php', '--keep-alive=0'];
        @set_time_limit(600);
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (! is_resource($process)) {
            throw new RuntimeException('Cron Moodle tidak bisa dijalankan.');
        }
        fclose($pipes[0]);
        $out  = (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);
        $tail = array_slice(array_values(array_filter(array_map('trim', explode("\n", $out)))), -3);

        return [$code === 0, implode(' · ', $tail)];
    }
}
