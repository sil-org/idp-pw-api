<?php

namespace tests\unit\common\models;

use common\models\Reset;
use common\models\User;
use Sil\Codeception\TestCase\Test;
use tests\helpers\BrokerUtils;
use tests\helpers\EmailUtils;
use tests\unit\fixtures\common\models\ResetFixture;
use tests\unit\fixtures\common\models\UserFixture;
use yii\web\TooManyRequestsHttpException;

/**
 * Class ResetTest
 * @package tests\unit\common\models
 * @method User users($key)
 * @method Reset resets($key)
 * @property \Codeception\Module\Yii2 tester
 */
class ResetTest extends Test
{
    public function _before()
    {
        BrokerUtils::insertFakeUsers();
        parent::_before();
    }

    public function _fixtures()
    {
        return [
            'users' => UserFixture::class,
            'resets' => ResetFixture::class,
        ];
    }

    public function testDefaultValues()
    {
        Reset::deleteAll();
        $user1 = $this->users('user1');
        $reset = new Reset();
        $reset->user_id = $user1->id;
        if (! $reset->save()) {
            $this->fail('Failed to create Reset: ' . print_r($reset->getFirstErrors(), true));
        }

        $this->assertEquals(32, strlen($reset->uid));
        $this->assertEquals(Reset::TYPE_PRIMARY, $reset->type);
        $this->assertNull($reset->code);
        $this->assertEquals(0, $reset->attempts);
        $this->assertNotNull($reset->expires);
        $this->assertNull($reset->disable_until);
        $this->assertNotNull($reset->created);
    }

    public function testCalculateExpireTime()
    {
        // Set config to consistent value
        \Yii::$app->params['reset']['lifetimeSeconds'] = 100;
        $reset = $this->resets('reset1');
        $time = time();

        $expireTimestamp = strtotime($reset->calculateExpireTime());

        $this->assertEqualsWithDelta($time + 100, $expireTimestamp, 2);
    }

    public function testCannotCreateSecondResetForUser()
    {
        $existing = $this->resets('reset1');

        $second = new Reset();
        $second->user_id = $existing->user_id;
        $this->assertFalse($second->save());
    }

    public function testFindOrCreateNew()
    {
        Reset::deleteAll();
        $user = $this->users('user1');

        $reset = Reset::findOrCreate($user);

        $this->assertEquals(32, strlen($reset->uid));
        $this->assertEquals(Reset::TYPE_PRIMARY, $reset->type);
        $this->assertNull($reset->code);
        $this->assertEquals(0, $reset->attempts);
        $this->assertNotNull($reset->expires);
        $this->assertNull($reset->disable_until);
        $this->assertNotNull($reset->created);
    }

    public function testFindOrCreateExisting()
    {
        $existing = $this->resets('reset1');
        $new = Reset::findOrCreate($existing->user);

        $this->assertEquals($existing->id, $new->id);
    }

    public function testFindOrCreateExistingResetTypeToPrimary()
    {
        $existing = $this->resets('reset1');
        $existing->setType(Reset::TYPE_SUPERVISOR);

        $new = Reset::findOrCreate($existing->user);
        $this->assertEquals($existing->id, $new->id);
        $this->assertEquals(Reset::TYPE_PRIMARY, $new->type);
    }

    public function testIsUserProvidedCodeCorrect()
    {
        $reset = $this->resets('reset3');
        $reset->send();
        $this->assertTrue($reset->isUserProvidedCodeCorrect('1234'));

        $this->assertFalse($reset->isUserProvidedCodeCorrect('1111'));
    }

    public function testSendPrimaryNoMethods()
    {
        $reset = $this->resets('reset1');
        $attempts = $reset->attempts;

        $this->assertEquals(0, EmailUtils::getEmailFilesCount());

        $reset->send();

        $this->assertEquals(2, EmailUtils::getEmailFilesCount());
        $this->assertTrue(EmailUtils::hasEmailFileBeenCreated($reset->code));
        $this->assertTrue(EmailUtils::hasEmailFileBeenCreated($reset->user->email));
        $this->assertTrue(EmailUtils::hasEmailFileBeenCreated('supervisor@domain.org'));
        $this->assertTrue(EmailUtils::hasEmailFileBeenCreated('password change for your'));
        $this->assertEquals($attempts + 1, $reset->attempts);

        $reset->send();

        $this->assertEquals(4, EmailUtils::getEmailFilesCount());
        $this->assertTrue(EmailUtils::hasEmailFileBeenCreated($reset->code));
        $this->assertEquals($attempts + 2, $reset->attempts);
    }

    public function testSendPrimaryWithMethods()
    {
        $reset = $this->resets('reset3');
        $attempts = $reset->attempts;

        $this->assertEquals(0, EmailUtils::getEmailFilesCount());

        $reset->send();

        $this->assertEquals(2, EmailUtils::getEmailFilesCount());
        $this->assertTrue(EmailUtils::hasEmailFileBeenCreated($reset->code));
        $this->assertTrue(EmailUtils::hasEmailFileBeenCreated($reset->user->email));
        $this->assertTrue(EmailUtils::hasEmailFileBeenCreated('email-777@example.org'));
        $this->assertTrue(EmailUtils::hasEmailFileBeenCreated('password change for your'));
        $this->assertEquals($attempts + 1, $reset->attempts);

        $reset->send();

        $this->assertEquals(4, EmailUtils::getEmailFilesCount());
        $this->assertTrue(EmailUtils::hasEmailFileBeenCreated($reset->code));
        $this->assertEquals($attempts + 2, $reset->attempts);
    }

    public function testSendSupervisorHasSupervisor()
    {
        $reset = $this->resets('reset1');
        $reset->type = Reset::TYPE_SUPERVISOR;
        $attempts = $reset->attempts;

        $this->assertEquals(0, EmailUtils::getEmailFilesCount());

        $reset->send();

        $this->assertEquals(1, EmailUtils::getEmailFilesCount());
        $this->assertTrue(EmailUtils::hasEmailFileBeenCreated($reset->code));
        $this->assertTrue(EmailUtils::hasEmailFileBeenCreated($reset->user->email));
        $this->assertTrue(EmailUtils::hasEmailFileBeenCreated('supervisor@domain.org'));
        $this->assertTrue(EmailUtils::hasEmailFileBeenCreated('requested a password change for their'));
        $this->assertEquals($attempts + 1, $reset->attempts);
    }

    public function testSendSupervisorNoSupervisor()
    {
        $reset = $this->resets('reset2');
        $reset->type = Reset::TYPE_SUPERVISOR;

        $this->assertEquals(0, EmailUtils::getEmailFilesCount());

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(1461173406);
        $reset->send();
        $this->assertEquals(0, EmailUtils::getEmailFilesCount());
    }

    public function testSendMethodEmail()
    {
        $reset = $this->resets('reset3');
        $attempts = $reset->attempts;

        $this->assertEquals(0, EmailUtils::getEmailFilesCount());

        $reset->send();

        $this->assertEquals(2, EmailUtils::getEmailFilesCount());
        $this->assertTrue(EmailUtils::hasEmailFileBeenCreated($reset->code));
        $this->assertTrue(EmailUtils::hasEmailFileBeenCreated($reset->user->email));
        $this->assertTrue(EmailUtils::hasEmailFileBeenCreated('email-777@example.org'));
        $this->assertTrue(EmailUtils::hasEmailFileBeenCreated('requested a password change for your'));
        $this->assertEquals($attempts + 1, $reset->attempts);
    }

    public function testDisableIsDisabled()
    {
        $reset = $this->resets('reset1');
        $this->assertFalse($reset->isDisabled());

        $expireDate = time() + \Yii::$app->params['reset']['disableDuration'];
        $reset->disable();
        $this->assertTrue($reset->isDisabled());
        $this->assertEqualsWithDelta($expireDate, strtotime($reset->disable_until), 2, '');
    }

    public function testSetType()
    {
        $reset = $this->resets('reset1');
        $this->assertEquals(Reset::TYPE_PRIMARY, $reset->type);

        $reset->setType(Reset::TYPE_SUPERVISOR);
        $this->assertEquals(Reset::TYPE_SUPERVISOR, $reset->type);

        $reset->setType(Reset::TYPE_METHOD, '22222222222222222222222222222222');
        $this->assertEquals(Reset::TYPE_METHOD, $reset->type);

        $reset->setType(Reset::TYPE_PRIMARY);
        $this->assertEquals(Reset::TYPE_PRIMARY, $reset->type);
    }

    public function testTrackAttempt()
    {
        $reset = $this->resets('reset1');
        $this->assertEquals(0, $reset->attempts);

        $reset->trackAttempt('test');
        $this->assertEquals(1, $reset->attempts);

        $reset->trackAttempt('test');
        $this->assertEquals(2, $reset->attempts);

        try {
            for ($i = 0; $i <= \Yii::$app->params['reset']['maxAttempts']; $i++) {
                $reset->trackAttempt('test');
            }
            $this->fail('TooManyRequestsHttpException should have been thrown');
        } catch (TooManyRequestsHttpException $e) {
            // This is the expected behavior
        }

        $this->assertTrue($reset->isDisabled());
    }

    public function testGetMaskedValue()
    {
        $reset = $this->resets('reset1');
        $this->assertEquals('f****_l**t@o***********.o**', $reset->getMaskedValue());

        $reset->setType(Reset::TYPE_SUPERVISOR);
        $this->assertEquals('s********r@d*****.o**', $reset->getMaskedValue());

        $reset->setType(Reset::TYPE_METHOD, '22222222222222222222222222222222');

        $reset = Reset::findOne(['id' => $reset->id]);

        $this->assertEquals('e**************9@d*****.o**', $reset->getMaskedValue());
    }

}
