<?php

namespace App\Libraries;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class Excel
{
    /**
     * Buat berkas .xlsx di memori. Semua sel ditulis sebagai teks supaya
     * nomor peserta dan username seperti "00123" tidak berubah menjadi angka.
     */
    public static function build(array $headers, array $rows): string
    {
        $book  = new Spreadsheet();
        $sheet = $book->getActiveSheet();

        foreach (array_merge([$headers], $rows) as $r => $row) {
            foreach (array_values($row) as $c => $value) {
                $cell = Coordinate::stringFromColumnIndex($c + 1) . ($r + 1);
                $sheet->setCellValueExplicit($cell, (string) ($value ?? ''), DataType::TYPE_STRING);
            }
        }
        $last = Coordinate::stringFromColumnIndex(max(1, count($headers)));
        $sheet->getStyle("A1:{$last}1")->getFont()->setBold(true);
        for ($c = 1; $c <= count($headers); $c++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }

        ob_start();
        (new Xlsx($book))->save('php://output');

        return (string) ob_get_clean();
    }

    /**
     * Baca lembar pertama menjadi daftar baris berkunci nama kolom (huruf kecil).
     *
     * @return list<array<string, string>>
     */
    public static function read(string $path): array
    {
        $raw = IOFactory::load($path)->getActiveSheet()->toArray(null, true, false, false);
        if ($raw === []) {
            return [];
        }

        $clean = static function ($value): string {
            if (is_float($value) && floor($value) === $value) {
                $value = (int) $value;
            }

            return trim((string) ($value ?? ''));
        };

        $headers = array_map(static fn ($h) => strtolower(str_replace(' ', '_', $clean($h))), array_shift($raw));
        $rows    = [];

        foreach ($raw as $line) {
            $row = [];
            foreach ($headers as $i => $name) {
                if ($name !== '') {
                    $row[$name] = $clean($line[$i] ?? '');
                }
            }
            if (implode('', $row) !== '') {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}
