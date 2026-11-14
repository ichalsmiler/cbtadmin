<?php

namespace App\Libraries;

/** Informasi server untuk panel System Information (khusus Linux). */
class SysInfo
{
    public static function get(): array
    {
        $cores = 1;
        $model = '-';
        $cpuinfo = @file_get_contents('/proc/cpuinfo');
        if ($cpuinfo !== false) {
            $cores = max(1, preg_match_all('/^processor\s*:/m', $cpuinfo));
            if (preg_match('/^model name\s*:\s*(.+)$/m', $cpuinfo, $m)) {
                $model = trim($m[1]);
            }
        }

        $load       = function_exists('sys_getloadavg') ? (sys_getloadavg()[0] ?? 0) : 0;
        $cpuPercent = (int) min(100, round($load / $cores * 100));

        $memTotal = $memAvail = 0;
        $meminfo  = @file_get_contents('/proc/meminfo');
        if ($meminfo !== false) {
            if (preg_match('/^MemTotal:\s+(\d+)/m', $meminfo, $m)) {
                $memTotal = (int) $m[1] * 1024;
            }
            if (preg_match('/^MemAvailable:\s+(\d+)/m', $meminfo, $m)) {
                $memAvail = (int) $m[1] * 1024;
            }
        }
        $memUsed = max(0, $memTotal - $memAvail);

        $diskTotal = (float) @disk_total_space('/');
        $diskUsed  = max(0, $diskTotal - (float) @disk_free_space('/'));

        return [
            'cpu'  => ['percent' => $cpuPercent, 'cores' => $cores, 'model' => $model],
            'mem'  => self::usage($memUsed, $memTotal),
            'disk' => self::usage($diskUsed, $diskTotal),
        ];
    }

    private static function usage(float $used, float $total): array
    {
        return [
            'percent' => $total > 0 ? (int) round($used / $total * 100) : 0,
            'text'    => self::bytes($used) . ' / ' . self::bytes($total),
        ];
    }

    private static function bytes(float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i     = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1) . ' ' . $units[$i];
    }
}
