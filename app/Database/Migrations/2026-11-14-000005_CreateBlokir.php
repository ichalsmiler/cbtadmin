<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Blokir siswa karena pelanggaran (menu Blokir Siswa); terpisah dari blokir absensi. */
class CreateBlokir extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'peserta_id'   => ['type' => 'INT', 'unsigned' => true],
            'ruang_id'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'alasan'       => ['type' => 'VARCHAR', 'constraint' => 200, 'default' => ''],
            'aktif'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'diblokir_oleh' => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => ''],
            'diblokir_at'  => ['type' => 'DATETIME', 'null' => true],
            'dibuka_oleh'  => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'dibuka_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['peserta_id', 'aktif']);
        $this->forge->addKey('ruang_id');
        $this->forge->createTable('blokir', true);
    }

    public function down()
    {
        $this->forge->dropTable('blokir', true);
    }
}
