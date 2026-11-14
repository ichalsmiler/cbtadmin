<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Akun pengawas per ruang: ruang yang diawasi dan password yang bisa dicetak. */
class AddPengawasRuang extends Migration
{
    public function up()
    {
        $this->forge->addColumn('admins', [
            'ruang_id'       => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'role'],
            'password_plain' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'password_hash'],
        ]);
        $this->db->query('CREATE INDEX admins_ruang_id ON admins (ruang_id)');
    }

    public function down()
    {
        $this->forge->dropColumn('admins', ['ruang_id', 'password_plain']);
    }
}
