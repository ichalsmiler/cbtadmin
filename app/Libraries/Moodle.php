<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use Config\Database;

/** Akses ke database Moodle (koneksi kedua, diatur lewat .env `moodle.*`). */
class Moodle
{
    public static function db(): BaseConnection
    {
        return Database::connect([
            'DSN'      => '',
            'hostname' => (string) env('moodle.hostname', 'localhost'),
            'username' => (string) env('moodle.username', ''),
            'password' => (string) env('moodle.password', ''),
            'database' => (string) env('moodle.database', 'moodle'),
            'DBDriver' => 'MySQLi',
            'DBPrefix' => '',
            'pConnect' => false,
            'DBDebug'  => true,
            'charset'  => 'utf8mb4',
            'DBCollat' => 'utf8mb4_unicode_ci',
            'swapPre'  => '',
            'encrypt'  => false,
            'compress' => false,
            'strictOn' => false,
            'failover' => [],
            'port'     => (int) env('moodle.port', 3306),
        ]);
    }

    /** Nama tabel Moodle lengkap dengan prefix, mis. t('user') = mdl_user. */
    public static function t(string $name): string
    {
        return preg_replace('/[^a-z0-9_]/i', '', (string) env('moodle.prefix', 'mdl_') . $name);
    }

    /** Alamat Moodle, mis. url('/mod/quiz/view.php?id=5'). */
    public static function url(string $path = ''): string
    {
        return rtrim((string) env('moodle.url', ''), '/') . $path;
    }

    /** Daftar quiz beserta kursus dan modul kursusnya. */
    public static function quizzes(): array
    {
        $q = self::t('quiz');
        $c = self::t('course');
        $cm = self::t('course_modules');
        $m = self::t('modules');

        return self::db()->query(
            "SELECT q.id, q.name, q.course, q.timeopen, q.timeclose, q.timelimit, q.password, q.grade, q.sumgrades,
                    c.fullname AS course_name, c.shortname AS course_short, cm.id AS cmid, cm.visible
               FROM {$q} q
               JOIN {$c} c ON c.id = q.course
               JOIN {$m} m ON m.name = 'quiz'
               JOIN {$cm} cm ON cm.module = m.id AND cm.instance = q.id AND cm.deletioninprogress = 0
              ORDER BY q.timeopen DESC, c.shortname, q.name"
        )->getResultArray();
    }

    /** ID user yang bukan peserta: site admin dan guest. */
    public static function nonParticipantIds(): array
    {
        $db  = self::db();
        $ids = [];
        $row = $db->table(self::t('config'))->select('value')->where('name', 'siteadmins')->get()->getRowArray();
        if ($row) {
            $ids = array_map('intval', array_filter(explode(',', (string) $row['value'])));
        }
        $guest = $db->table(self::t('user'))->select('id')->where('username', 'guest')->get()->getRowArray();
        if ($guest) {
            $ids[] = (int) $guest['id'];
        }

        return $ids ?: [0];
    }

    public static function totalUsers(): int
    {
        return self::db()->table(self::t('user'))
            ->where('deleted', 0)
            ->whereNotIn('id', self::nonParticipantIds())
            ->countAllResults();
    }

    /**
     * Peserta yang aktif dalam N menit terakhir, dihitung dari mdl_user.lastaccess.
     * Sengaja tidak memakai mdl_sessions: di server ini sesi disimpan di Redis.
     */
    public static function activeUsers(int $minutes): int
    {
        return self::db()->table(self::t('user'))
            ->where('deleted', 0)
            ->where('lastaccess >', time() - $minutes * 60)
            ->whereNotIn('id', self::nonParticipantIds())
            ->countAllResults();
    }

    public static function unsubmittedAttempts(): int
    {
        return self::db()->table(self::t('quiz_attempts'))
            ->where('state', 'inprogress')
            ->where('preview', 0)
            ->countAllResults();
    }
}
