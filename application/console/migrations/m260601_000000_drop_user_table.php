<?php

use yii\db\Migration;

/**
 * Drop the local `user` table now that all user data and access token
 * state are fully managed by IdBroker.
 *
 * This migration completes the elimination of the local database: all
 * previously remaining tables (session, reset, event_log) have already
 * been dropped in earlier migrations.  Once this migration has run on
 * every environment, the `db` component and the database service
 * (mariadb / testdb) can be removed from configuration.
 */
class m260601_000000_drop_user_table extends Migration
{
    public function safeUp()
    {
        $this->dropTable('{{user}}');
    }

    public function safeDown()
    {
        $this->createTable(
            '{{user}}',
            [
                'id'                      => 'pk',
                'employee_id'             => 'varchar(255) not null',
                'first_name'              => 'varchar(255) not null',
                'last_name'               => 'varchar(255) not null',
                'idp_username'            => 'varchar(255) not null',
                'email'                   => 'varchar(255) not null',
                'created'                 => 'datetime not null',
                'access_token'            => 'varchar(64) null',
                'access_token_expiration' => 'datetime null',
                'auth_type'               => "enum('login','reset') null",
                'uuid'                    => 'varchar(64) null',
                'display_name'            => 'varchar(255) null',
            ],
            'ENGINE=InnoDB DEFAULT CHARSET=utf8'
        );
        $this->createIndex('uq_user_employee_id', '{{user}}', 'employee_id', true);
        $this->createIndex('uq_user_email', '{{user}}', 'email', true);
        $this->createIndex('uq_user_access_token', '{{user}}', 'access_token', true);
    }
}
