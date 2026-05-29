<?php

use yii\db\Migration;

class m260529_000000_drop_session_table extends Migration
{
    public function safeUp()
    {
        $this->dropTable('{{session}}');
    }

    public function safeDown()
    {
        $this->createTable(
            '{{session}}',
            [
                'id' => 'char(64) primary key not null',
                'expire' => 'int(11) null',
                'data' => 'blob not null',
            ],
            "ENGINE=InnoDB DEFAULT CHARSET=utf8"
        );

        $this->createIndex('idx_expire', 'session', 'expire', false);
    }
}
