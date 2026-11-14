<?php

namespace App\Libraries;

class ActivityLog
{
    public static function add(string $aksi, string $detail = '', ?string $username = null): void
    {
        try {
            $session = session();
            db_connect()->table('activity_log')->insert([
                'admin_id'   => $session->get('admin_id'),
                'username'   => $username ?? (string) $session->get('admin_user'),
                'aksi'       => $aksi,
                'detail'     => $detail,
                'ip'         => service('request')->getIPAddress(),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'ActivityLog gagal: ' . $e->getMessage());
        }
    }
}
