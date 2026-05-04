<?php

use yii\db\Migration;

/**
 * Class m190627_154411_remove_method_table
 */
class m190627_154411_remove_method_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->dropTable('{{method}}');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->createTable(
            '{{method}}',
            [
                'id' => 'pk',
                'uid' => 'char(32) not null',
                'user_id' => 'int(11) not null',
                'type' => "enum('email','phone') not null",
                'value' => 'varchar(255) not null',
                'verified' => 'boolean not null default false',
                'verification_code' => 'varchar(64) null',
                'verification_attempts' => 'smallint null',
                'verification_expires' => 'datetime null',
                'created' => 'datetime not null',
                'deleted_at' => 'datetime null',
            ],
            "ENGINE=InnoDB DEFAULT CHARSET=utf8"
        );
        $this->addForeignKey('fk_method_user_id', '{{method}}', 'user_id', '{{user}}', 'id', 'CASCADE', 'NO ACTION');
        $this->createIndex('uq_method_uid', '{{method}}', 'uid', true);
        $this->createIndex('uq_method_user_type_value', '{{method}}', ['user_id','type','value'], true);
        $this->createIndex('uq_method_verification_code', '{{method}}', 'verification_code', true);
    }
}
