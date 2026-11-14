<?php

namespace App\Libraries;

class ActivityLog
{
    public static function add(string $aksi, string $detail = '', ?string $username = null): void
    {
        try {
            if (is_cli()) {
                // Dari cron (mis. release token otomatis): tidak ada sesi maupun alamat IP.
                $row = ['admin_id' => null, 'username' => $username ?? 'sistem', 'ip' => 'cli'];
            } else {
                $session = session();
                $row     = [
                    'admin_id' => $session->get('admin_id'),
                    'username' => $username ?? (string) $session->get('admin_user'),
                    'ip'       => service('request')->getIPAddress(),
                ];
            }
            db_connect()->table('activity_log')->insert($row + [
                'aksi'       => $aksi,
                'detail'     => $detail,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'ActivityLog gagal: ' . $e->getMessage());
        }
    }
}
