<?php

use yii\db\Migration;

class m260518_000000_remove_reset_table extends Migration
{
    // Use safeUp/safeDown to run migration code within a transaction
    public function safeUp()
    {
        $this->dropTable('{{reset}}');
    }

    public function safeDown()
    {
        $this->createTable(
            '{{reset}}',
            [
                'id' => 'pk',
                'uid' => 'char(32) not null',
                'user_id' => 'int(11) not null',
                'type' => "enum('primary', 'method', 'supervisor', 'spouse') not null",
                'code' => 'varchar(64) null',
                'attempts' => 'smallint not null default 0',
                'expires' => 'datetime not null',
                'disable_until' => 'datetime null',
                'created' => 'datetime not null',
                'email' => 'varchar(255) null',
            ],
            "ENGINE=InnoDB DEFAULT CHARSET=utf8"
        );
        $this->addForeignKey('fk_reset_user_id', '{{reset}}', 'user_id', '{{user}}', 'id', 'CASCADE', 'NO ACTION');
        $this->createIndex('uq_reset_uid', '{{reset}}', 'uid', true);
        $this->createIndex('uq_reset_user_id', '{{reset}}', 'user_id', true);
        $this->createIndex('uq_reset_code', '{{reset}}', 'code', false);
    }
}
