<?php

namespace App\Libraries;

use App\Controllers\Peserta;

/**
 * Akun pengawas (role proktor) otomatis: satu akun untuk setiap ruang.
 * Password disimpan juga apa adanya supaya bisa dibagikan/dicetak, sama seperti password peserta.
 */
class Pengawas
{
    /**
     * Buat akun untuk ruang yang belum punya pengawas.
     *
     * @return int jumlah akun yang dibuat
     */
    public static function sync(): int
    {
        $db      = db_connect();
        $covered = array_map('intval', array_column(
            $db->table('admins')->select('ruang_id')->where('role', 'proktor')->where('ruang_id IS NOT NULL')->get()->getResultArray(),
            'ruang_id'
        ));
        $created = 0;
        foreach ($db->table('ruang')->orderBy('id')->get()->getResultArray() as $ruang) {
            if (in_array((int) $ruang['id'], $covered, true)) {
                continue;
            }
            $password = Peserta::randomPassword();
            $db->table('admins')->insert([
                'username'       => self::usernameFor($ruang),
                'password_hash'  => password_hash($password, PASSWORD_DEFAULT),
                'password_plain' => $password,
                'nama'           => mb_substr(trim((string) $ruang['pengawas']) ?: 'Pengawas Ruang ' . $ruang['nama'], 0, 120),
                'role'           => 'proktor',
                'ruang_id'       => (int) $ruang['id'],
                'aktif'          => 1,
                'created_at'     => date('Y-m-d H:i:s'),
            ]);
            $created++;
        }

        return $created;
    }

    /** Username "pengawas" + nama ruang (mis. ruang 1 -> pengawas1), unik. */
    private static function usernameFor(array $ruang): string
    {
        $slug = preg_replace('/[^a-z0-9]+/', '', strtolower((string) $ruang['nama']));
        $base = 'pengawas' . ($slug !== '' ? substr($slug, 0, 40) : $ruang['id']);
        $name = $base;
        $db   = db_connect();
        for ($i = 2; $db->table('admins')->where('username', $name)->countAllResults() > 0; $i++) {
            $name = $base . '_' . $i;
        }

        return $name;
    }

    /** Password baru untuk akun pengawas tertentu (semua bila $ids kosong). */
    public static function resetPasswords(array $ids = []): int
    {
        $db      = db_connect();
        $builder = $db->table('admins')->select('id')->where('role', 'proktor')->where('ruang_id IS NOT NULL');
        if ($ids !== []) {
            $builder->whereIn('id', $ids);
        }
        $count = 0;
        foreach ($builder->get()->getResultArray() as $row) {
            $password = Peserta::randomPassword();
            $db->table('admins')->where('id', $row['id'])->update([
                'password_hash'  => password_hash($password, PASSWORD_DEFAULT),
                'password_plain' => $password,
            ]);
            $count++;
        }

        return $count;
    }

    /** Username peserta di ruang tertentu. */
    public static function usernamesInRuang(int $ruangId): array
    {
        return array_column(
            db_connect()->table('peserta')->select('username')->where('ruang_id', $ruangId)->get()->getResultArray(),
            'username'
        );
    }

    /** ID user Moodle untuk peserta di ruang tertentu. */
    public static function moodleIdsInRuang(int $ruangId): array
    {
        $usernames = self::usernamesInRuang($ruangId);
        if ($usernames === []) {
            return [];
        }

        return array_map('intval', array_column(
            Moodle::db()->table(Moodle::t('user'))->select('id')->where('deleted', 0)->whereIn('username', $usernames)->get()->getResultArray(),
            'id'
        ));
    }
}
