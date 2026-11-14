<?php

namespace App\Controllers;

use App\Libraries\ActivityLog;
use App\Libraries\Moodle;
use App\Libraries\MoodleBridge;

class ResetLogin extends PanelController
{
    public function index(): string
    {
        $cari    = trim((string) $this->request->getGet('cari'));
        $ruangId = (string) $this->request->getGet('ruang');
        $rows    = [];
        $error   = false;

        try {
            $users    = $this->candidates($cari);
            $sessions = [];
            foreach (array_chunk(array_keys($users), 500) as $chunk) {
                foreach (MoodleBridge::call('list_sessions', ['userids' => $chunk]) as $s) {
                    $sessions[$s['userid']] = $s;
                }
            }

            $panel = array_column(db_connect()->table('peserta p')
                ->select('p.username, p.kelas, p.ruang_id, r.nama AS ruang_nama')
                ->join('ruang r', 'r.id = p.ruang_id', 'left')
                ->get()->getResultArray(), null, 'username');

            foreach ($users as $id => $u) {
                $s = $sessions[$id] ?? null;
                if (! $s && $cari === '') {
                    continue;
                }
                $info = $panel[$u['username']] ?? null;
                if ($ruangId === '0' ? ($info['ruang_id'] ?? null) !== null : ($ruangId !== '' && (string) ($info['ruang_id'] ?? '') !== $ruangId)) {
                    continue;
                }
                $rows[] = [
                    'userid'   => (int) $id,
                    'username' => $u['username'],
                    'nama'     => trim($u['firstname'] . ' ' . $u['lastname']),
                    'kelas'    => $info['kelas'] ?? '',
                    'ruang'    => $info['ruang_nama'] ?? null,
                    'sesi'     => (int) ($s['count'] ?? 0),
                    'ip'       => $s['lastip'] ?? '',
                    'login'    => (int) ($s['timecreated'] ?? 0),
                    'aktif'    => (int) ($s['timemodified'] ?? 0),
                ];
            }
        } catch (\Throwable $e) {
            $error = true;
            log_message('error', 'Reset Login: ' . $e->getMessage());
        }

        return $this->render('reset_login/index', [
            'title'     => 'Reset Login Peserta',
            'rows'      => $rows,
            'cari'      => $cari,
            'ruangId'   => $ruangId,
            'ruangList' => db_connect()->table('ruang')->orderBy('nama')->get()->getResultArray(),
            'error'     => $error,
        ]);
    }

    public function reset()
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $this->request->getPost('ids')))));
        $label = count($ids) . ' peserta terpilih';

        $ruangId = (int) $this->request->getPost('ruang_id');
        if ($ruangId > 0) {
            $ruang     = db_connect()->table('ruang')->where('id', $ruangId)->get()->getRowArray();
            $usernames = array_column(db_connect()->table('peserta')->select('username')->where('ruang_id', $ruangId)->get()->getResultArray(), 'username');
            $ids       = $usernames === [] ? [] : array_map('intval', array_column(
                Moodle::db()->table(Moodle::t('user'))->select('id')->where('deleted', 0)->whereIn('username', $usernames)->get()->getResultArray(),
                'id'
            ));
            $label = 'ruang ' . ($ruang['nama'] ?? $ruangId);
        }

        if ($ids === []) {
            return redirect()->back()->with('error', $ruangId > 0 ? 'Ruang ini belum punya peserta yang terdaftar di Moodle.' : 'Belum ada peserta yang dicentang.');
        }

        try {
            $items = MoodleBridge::call('kill_sessions', ['userids' => $ids]);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Reset login gagal: ' . $e->getMessage());
        }

        $users  = 0;
        $killed = 0;
        $errors = [];
        foreach ($items as $item) {
            if (isset($item['error'])) {
                $errors[] = $item['error'];
            } elseif ($item['count'] > 0) {
                $users++;
                $killed += $item['count'];
            }
        }
        ActivityLog::add('reset_login', "$label: $users peserta, $killed sesi");

        $message = "Login direset untuk $users peserta ($killed sesi) dari $label. Peserta bisa login lagi di perangkat lain; attempt yang sedang berjalan tetap tersimpan.";
        if ($errors !== []) {
            $message .= ' Dilewati: ' . implode('; ', array_unique($errors));
        }

        return redirect()->back()->with($errors === [] ? 'success' : 'error', $message);
    }

    /** User Moodle yang mungkin sedang login, atau yang cocok dengan pencarian. */
    private function candidates(string $cari): array
    {
        $builder = Moodle::db()->table(Moodle::t('user'))
            ->select('id, username, firstname, lastname')
            ->where('deleted', 0)
            ->whereNotIn('id', Moodle::nonParticipantIds());

        if ($cari !== '') {
            $builder->groupStart()
                ->like('username', $cari)->orLike('firstname', $cari)->orLike('lastname', $cari)
                ->groupEnd()->limit(200);
        } else {
            $row     = Moodle::db()->table(Moodle::t('config'))->select('value')->where('name', 'sessiontimeout')->get()->getRowArray();
            $timeout = max(600, (int) ($row['value'] ?? 28800));
            $since   = time() - $timeout;
            $builder->groupStart()->where('lastaccess >', $since)->orWhere('currentlogin >', $since)->groupEnd();
        }

        return array_column($builder->orderBy('username')->get()->getResultArray(), null, 'id');
    }
}
