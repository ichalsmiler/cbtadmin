<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Menu BroExam Unlock dibatalkan, jadi tabelnya tidak dipakai. */
class DropBroexamLock extends Migration
{
    public function up()
    {
        $this->forge->dropTable('broexam_lock', true);
    }

    public function down()
    {
    }
}
