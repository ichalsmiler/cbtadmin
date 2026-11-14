<?php

namespace App\Libraries;

use CodeIgniter\HTTP\ResponseInterface;
use Dompdf\Dompdf;
use Dompdf\Options;

class Pdf
{
    /** Render view menjadi PDF A4 dan kirim ke browser (dibuka di tab, bisa dicetak/diunduh). */
    public static function respond(string $view, array $data, string $filename, string $orientation = 'portrait'): ResponseInterface
    {
        @set_time_limit(300);
        @ini_set('memory_limit', '384M');

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('tempDir', WRITEPATH . 'cache');
        $options->set('fontCache', WRITEPATH . 'cache');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view($view, $data + ['school' => School::info()]));
        $dompdf->setPaper('A4', $orientation);
        $dompdf->render();

        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename);

        return service('response')
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'inline; filename="' . $safe . '"')
            ->setBody($dompdf->output());
    }
}
