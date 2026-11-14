<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePanelTables extends Migration
{
    public function up()
    {
        $id = ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true];

        $this->forge->addField([
            'id'            => $id,
            'username'      => ['type' => 'VARCHAR', 'constraint' => 60],
            'password_hash' => ['type' => 'VARCHAR', 'constraint' => 255],
            'nama'          => ['type' => 'VARCHAR', 'constraint' => 120],
            'role'          => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'admin'],
            'aktif'         => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'last_login'    => ['type' => 'DATETIME', 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('username');
        $this->forge->createTable('admins', true);

        $this->forge->addField([
            'id'          => $id,
            'nama'        => ['type' => 'VARCHAR', 'constraint' => 60],
            'jam_mulai'   => ['type' => 'TIME', 'null' => true],
            'jam_selesai' => ['type' => 'TIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('sesi', true);

        $this->forge->addField([
            'id'        => $id,
            'nama'      => ['type' => 'VARCHAR', 'constraint' => 60],
            'kapasitas' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'pengawas'  => ['type' => 'VARCHAR', 'constraint' => 120, 'default' => ''],
            'sesi_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('ruang', true);

        $this->forge->addField([
            'id'             => $id,
            'nomor_peserta'  => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => ''],
            'nama'           => ['type' => 'VARCHAR', 'constraint' => 150],
            'kelas'          => ['type' => 'VARCHAR', 'constraint' => 60, 'default' => ''],
            'username'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'password_plain' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'moodle_userid'  => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'ruang_id'       => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'sesi_id'        => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('username');
        $this->forge->addKey('ruang_id');
        $this->forge->addKey('sesi_id');
        $this->forge->createTable('peserta', true);

        $this->forge->addField([
            'skey'   => ['type' => 'VARCHAR', 'constraint' => 60],
            'svalue' => ['type' => 'TEXT', 'null' => true],
        ]);
        $this->forge->addKey('skey', true);
        $this->forge->createTable('settings', true);

        $this->forge->addField([
            'id'         => $id,
            'admin_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'username'   => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => ''],
            'aksi'       => ['type' => 'VARCHAR', 'constraint' => 60],
            'detail'     => ['type' => 'TEXT', 'null' => true],
            'ip'         => ['type' => 'VARCHAR', 'constraint' => 45, 'default' => ''],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('created_at');
        $this->forge->createTable('activity_log', true);

        $this->forge->addField([
            'id'          => $id,
            'username'    => ['type' => 'VARCHAR', 'constraint' => 100],
            'perangkat'   => ['type' => 'VARCHAR', 'constraint' => 120, 'default' => ''],
            'alasan'      => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
            'status'      => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'terkunci'],
            'kode'        => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'locked_at'   => ['type' => 'DATETIME', 'null' => true],
            'unlocked_at' => ['type' => 'DATETIME', 'null' => true],
            'unlocked_by' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('username');
        $this->forge->createTable('broexam_lock', true);
    }

    public function down()
    {
        foreach (['broexam_lock', 'activity_log', 'settings', 'peserta', 'ruang', 'sesi', 'admins'] as $table) {
            $this->forge->dropTable($table, true);
        }
    }
}
