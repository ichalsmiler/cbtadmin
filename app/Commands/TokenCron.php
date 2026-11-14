<?php

namespace App\Commands;

use App\Libraries\ActiveTest;
use App\Libraries\ActivityLog;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Dijalankan cron tiap menit (/etc/cron.d/cbtadmin):
 * release token bila sudah waktunya, dan pasang token di quiz baru pada tes aktif.
 */
class TokenCron extends BaseCommand
{
    protected $group       = 'CBT';
    protected $name        = 'cbt:token';
    protected $description = 'Release token otomatis dan samakan token di semua quiz tes aktif.';

    public function run(array $params)
    {
        try {
            if (ActiveTest::due()) {
                $token = ActiveTest::release();
                ActivityLog::add('release_token', 'otomatis: ' . $token);
                CLI::write('Token direlease: ' . $token);

                return EXIT_SUCCESS;
            }
            $count = ActiveTest::sync();
            if ($count > 0) {
                CLI::write($count . ' quiz dipasangi token yang berlaku.');
            }
        } catch (\Throwable $e) {
            log_message('error', 'cbt:token: ' . $e->getMessage());
            CLI::error($e->getMessage());

            return EXIT_ERROR;
        }

        return EXIT_SUCCESS;
    }
}
