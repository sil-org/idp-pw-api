<?php

use yii\db\Migration;

class m260424_081600_drop_event_log_table extends Migration
{
    // Use safeUp/safeDown to run migration code within a transaction
    public function safeUp()
    {
        $this->dropTable('{{event_log}}');
    }

    public function safeDown()
    {
        $this->createTable(
            '{{event_log}}',
            [
                'id' => 'pk',
                'user_id' => 'int null',
                'topic' => 'varchar(64) not null',
                'details' => 'varchar(1024) not null',
                'created' => 'datetime not null',
            ],
            "ENGINE=InnoDB DEFAULT CHARSET=utf8"
        );
        $this->addForeignKey(
            'fk_event_log_user_id',
            '{{event_log}}',
            'user_id',
            '{{user}}',
            'id',
            'CASCADE',
            'NO ACTION'
        );
    }
}
