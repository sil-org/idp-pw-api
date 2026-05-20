<?php

use common\models\EmailLog;
use common\models\Invite;
use common\models\Method;
use common\models\Mfa;
use common\models\MfaBackupcode;
use common\models\MfaFailedAttempt;
use common\models\Password;
use common\models\User;
use yii\db\Migration;

/**
 * Class m381901_235959_insert_test_data
 */
class m381901_235959_insert_test_data extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        Method::deleteAll();
        EmailLog::deleteAll();
        Invite::deleteAll();
        MfaBackupcode::deleteAll();
        MfaFailedAttempt::deleteAll();
        Mfa::deleteAll();
        User::deleteAll();
        Password::deleteAll();

        $userData =  require(__DIR__ . '/User.php');

        $this->batchInsert(
            '{{user}}',
            array_keys($userData['user1']),
            $userData
        );

        $methodData = require(__DIR__ . '/Method.php');

        $this->batchInsert(
            '{{method}}',
            array_keys($methodData['method2']),
            $methodData
        );

        $resetData = require(__DIR__ . '/Reset.php');

        $this->batchInsert(
            '{{reset}}',
            array_keys($resetData['reset2']),
            $resetData
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        Method::deleteAll();
        EmailLog::deleteAll();
        Invite::deleteAll();
        MfaBackupcode::deleteAll();
        MfaFailedAttempt::deleteAll();
        Mfa::deleteAll();
        User::deleteAll();
        Password::deleteAll();
    }
}
