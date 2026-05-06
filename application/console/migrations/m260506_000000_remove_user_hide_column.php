<?php

use yii\db\Migration;

class m260506_000000_remove_user_hide_column extends Migration
{
    // Use safeUp/safeDown to run migration code within a transaction
    public function safeUp()
    {
        $this->dropColumn('{{user}}', '{{hide}}');
    }

    public function safeDown()
    {
        $this->addColumn('user', 'hide', "enum('no','yes') not null");
    }
}
