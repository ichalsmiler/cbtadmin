<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Absensi harian oleh pengawas ruang; siswa tidak hadir diblokir di Moodle hari itu. */
class CreateAbsensi extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tanggal'    => ['type' => 'DATE'],
            'peserta_id' => ['type' => 'INT', 'unsigned' => true],
            'ruang_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'status'     => ['type' => 'VARCHAR', 'constraint' => 20],               // hadir | tidak_hadir
            'diblokir'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0], // user Moodle sedang disuspend panel
            'dicatat_oleh' => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => ''],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['tanggal', 'peserta_id']);
        $this->forge->addKey('ruang_id');
        $this->forge->addKey('diblokir');
        $this->forge->createTable('absensi', true);
    }

    public function down()
    {
        $this->forge->dropTable('absensi', true);
    }
}
