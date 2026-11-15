<?php

namespace App\Libraries;

/**
 * Jawaban per nomor soal (benar/salah) dan analisis butir soal untuk satu quiz Moodle.
 *
 * Diambil dari attempt selesai terakhir tiap siswa: langkah terakhir setiap question_attempt
 * memberi state (gradedright/gradedwrong/gradedpartial/gaveup/needsgrading) dan fraction (0–1).
 * Nomor soal = urutan slot bernilai (> 0 poin); soal deskripsi (0 poin) tidak dihitung.
 */
class AnalisisSoal
{
    /**
     * @param list<int> $userIds
     *
     * @return array{soal: list<array{slot:int, no:int, maxmark:float, nama:string, teks:string}>,
     *               jawaban: array<int, array<int, array{f:?float, state:string, respon:string}>>}
     */
    public static function jawaban(int $quizId, array $userIds): array
    {
        $hasil = ['soal' => [], 'jawaban' => []];
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if ($userIds === []) {
            return $hasil;
        }
        $db  = Moodle::db();
        $qa  = Moodle::t('quiz_attempts');
        $qat = Moodle::t('question_attempts');
        $qas = Moodle::t('question_attempt_steps');

        // Attempt selesai terakhir tiap siswa (bukan pratinjau).
        $attempt = [];
        foreach ($db->table($qa)->select('id, userid')->where('quiz', $quizId)->where('preview', 0)
            ->where('state', 'finished')->whereIn('userid', $userIds)->orderBy('attempt', 'ASC')
            ->get()->getResultArray() as $a) {
            $attempt[(int) $a['userid']] = (int) $a['id'];
        }
        if ($attempt === []) {
            return $hasil;
        }

        $rows = $db->query(
            "SELECT a.userid, t.slot, t.maxmark, t.questionid, t.responsesummary, s.state, s.fraction
               FROM {$qa} a
               JOIN {$qat} t ON t.questionusageid = a.uniqueid
               JOIN {$qas} s ON s.questionattemptid = t.id
                AND s.sequencenumber = (SELECT MAX(s2.sequencenumber) FROM {$qas} s2 WHERE s2.questionattemptid = t.id)
              WHERE a.id IN (" . implode(',', array_values($attempt)) . ')'
        )->getResultArray();

        $slots = [];   // slot => [maxmark, [questionid => jumlah]]
        foreach ($rows as $r) {
            $slot = (int) $r['slot'];
            if ((float) $r['maxmark'] <= 0) {
                continue;  // deskripsi / bukan soal
            }
            $slots[$slot]['maxmark']                       = (float) $r['maxmark'];
            $slots[$slot]['q'][(int) $r['questionid']]     = ($slots[$slot]['q'][(int) $r['questionid']] ?? 0) + 1;
            $hasil['jawaban'][(int) $r['userid']][$slot] = [
                'f'      => $r['fraction'] === null ? null : (float) $r['fraction'],
                'state'  => (string) $r['state'],
                'respon' => (string) ($r['responsesummary'] ?? ''),
            ];
        }
        ksort($slots);

        // Nama/teks soal: soal yang paling sering muncul di slot itu (soal acak bisa berbeda per siswa).
        $qid = [];
        foreach ($slots as $slot => $s) {
            arsort($s['q']);
            $qid[$slot] = (int) array_key_first($s['q']);
        }
        $info = $qid === [] ? [] : array_column($db->table(Moodle::t('question'))->select('id, name, questiontext')
            ->whereIn('id', array_values($qid))->get()->getResultArray(), null, 'id');

        $no = 0;
        foreach ($slots as $slot => $s) {
            $q    = $info[$qid[$slot]] ?? [];
            $teks = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) ($q['questiontext'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            $hasil['soal'][] = [
                'slot'    => $slot,
                'no'      => ++$no,
                'maxmark' => $s['maxmark'],
                'nama'    => (string) ($q['name'] ?? ''),
                'teks'    => mb_strimwidth($teks, 0, 160, '…'),
            ];
        }

        return $hasil;
    }

    /** Kode sel untuk satu jawaban: B benar, S salah, – kosong, ? belum dinilai, angka untuk benar sebagian. */
    public static function kode(?array $j): string
    {
        if ($j === null) {
            return '';
        }
        if ($j['f'] === null) {
            return in_array($j['state'], ['gaveup', 'todo'], true) ? '–' : '?';
        }
        if ($j['f'] >= 0.9999) {
            return 'B';
        }
        if ($j['f'] <= 0) {
            return $j['state'] === 'gaveup' ? '–' : 'S';
        }

        return rtrim(rtrim(number_format($j['f'], 2, ',', ''), '0'), ',');
    }

    /** Nilai 0–1 sebuah jawaban untuk analisis; null = belum dinilai (esai), tidak dihitung. Kosong = 0. */
    private static function skor(?array $j): ?float
    {
        if ($j === null) {
            return null;
        }
        if ($j['f'] === null) {
            return in_array($j['state'], ['gaveup', 'todo'], true) ? 0.0 : null;
        }

        return max(0.0, min(1.0, $j['f']));
    }

    /**
     * Analisis butir: indeks kesukaran P (rata-rata skor 0–1) dan daya beda D = P kelompok atas − P kelompok bawah
     * (27% siswa dengan total skor tertinggi/terendah).
     *
     * @return array{butir: list<array>, ringkas: array<string,int>, n:int}
     */
    public static function analisis(array $data): array
    {
        $soal    = $data['soal'];
        $jawaban = $data['jawaban'];

        $total = [];
        foreach ($jawaban as $uid => $per) {
            $t = 0.0;
            foreach ($soal as $s) {
                $t += (self::skor($per[$s['slot']] ?? null) ?? 0.0) * $s['maxmark'];
            }
            $total[$uid] = $t;
        }
        arsort($total);
        $n     = count($total);
        $k     = $n >= 4 ? max(1, (int) round($n * 0.27)) : 0;
        $atas  = $k ? array_slice(array_keys($total), 0, $k) : [];
        $bawah = $k ? array_slice(array_keys($total), -$k) : [];

        $rata = static function (array $uids, int $slot) use ($jawaban): ?float {
            $v = [];
            foreach ($uids as $uid) {
                $x = self::skor($jawaban[$uid][$slot] ?? null);
                if ($x !== null) {
                    $v[] = $x;
                }
            }

            return $v ? array_sum($v) / count($v) : null;
        };

        $ringkas = ['Mudah' => 0, 'Sedang' => 0, 'Sukar' => 0];
        $butir   = [];
        foreach ($soal as $s) {
            $benar = $salah = $kosong = $dinilai = 0;
            foreach ($jawaban as $per) {
                $kode = self::kode($per[$s['slot']] ?? null);
                $benar  += $kode === 'B' ? 1 : 0;
                $salah  += $kode === 'S' ? 1 : 0;
                $kosong += $kode === '–' ? 1 : 0;
            }
            $p = $rata(array_keys($jawaban), $s['slot']);
            $d = $k ? (($rata($atas, $s['slot']) ?? 0) - ($rata($bawah, $s['slot']) ?? 0)) : null;
            $tingkat = $p === null ? '' : ($p <= 0.30 ? 'Sukar' : ($p <= 0.70 ? 'Sedang' : 'Mudah'));
            if ($tingkat !== '') {
                $ringkas[$tingkat]++;
            }
            $butir[] = $s + [
                'benar' => $benar, 'salah' => $salah, 'kosong' => $kosong,
                'p' => $p, 'tingkat' => $tingkat, 'd' => $d, 'kualitas' => self::kualitas($d),
            ];
        }

        return ['butir' => $butir, 'ringkas' => $ringkas, 'n' => $n];
    }

    /** Klasifikasi daya beda yang umum dipakai sekolah. */
    public static function kualitas(?float $d): string
    {
        return match (true) {
            $d === null => '',
            $d >= 0.40  => 'Sangat baik',
            $d >= 0.30  => 'Baik',
            $d >= 0.20  => 'Cukup, perlu perbaikan',
            $d >= 0     => 'Jelek, perlu direvisi',
            default     => 'Negatif, sebaiknya dibuang',
        };
    }
}
