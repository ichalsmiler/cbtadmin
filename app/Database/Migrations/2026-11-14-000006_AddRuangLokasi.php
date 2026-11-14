<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Nama ruang / lab fisik (mis. "Lab Komputer 1") di samping nomor ruang ujian. */
class AddRuangLokasi extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('lokasi', 'ruang')) {
            $this->forge->addColumn('ruang', [
                'lokasi' => ['type' => 'VARCHAR', 'constraint' => 120, 'default' => '', 'after' => 'nama'],
            ]);
        }
    }

    public function down()
    {
        $this->forge->dropColumn('ruang', 'lokasi');
    }
}
