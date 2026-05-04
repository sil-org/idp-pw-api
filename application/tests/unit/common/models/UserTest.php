<?php

namespace tests\unit\common\models;

use Sil\Codeception\TestCase\Test;
use common\components\personnel\PersonnelUser;
use common\models\Reset;
use common\models\User;
use tests\helpers\BrokerUtils;
use tests\unit\fixtures\common\models\ResetFixture;
use tests\unit\fixtures\common\models\UserFixture;

/**
 * Class UserTest
 * @package tests\unit\common\models
 * @method User users($key)
 * @method Reset resets($key)
 */
class UserTest extends Test
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
        User::deleteAll();
        $user = new User();
        $user->uuid = 'ccd9bb38-a656-4c62-80ba-2428468599b3';
        $user->employee_id = '1456771651';
        $user->first_name = 'User';
        $user->last_name = 'One';
        $user->idp_username = 'user_1456771651';
        $user->email = 'user-1456771651@domain.org';
        $user->hide = 'no';
        if (! $user->save()) {
            $this->fail('Failed to create User: ' . print_r($user->getFirstErrors(), true));
        }

        $this->assertEquals(36, strlen($user->uuid));
        $this->assertNotNull($user->created);
    }

    public function testFields()
    {
        $expected = [
            'first_name' => 'User',
            'last_name' => 'One',
            'idp_username' => 'first_last',
            'email' => 'first_last@organization.org',
            'password_meta' => [
                'last_changed' => '2016-06-15T19:00:32+00:00',
                'expires' => '2016-06-15T19:00:32+00:00',
            ],
            'auth_type' => 'login',
            'hide' => 'no',
        ];

        $user = $this->users('user1');
        $fields = $user->toArray();
        $this->assertEquals($expected['first_name'], $fields['first_name']);
        $this->assertEquals($expected['last_name'], $fields['last_name']);
        $this->assertEquals($expected['idp_username'], $fields['idp_username']);
        $this->assertEquals($expected['email'], $fields['email']);
        $this->assertEquals($expected['auth_type'], $fields['auth_type']);
        $this->assertEquals($expected['hide'], $fields['hide']);
    }

    public function testFindOrCreateException()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(1459974492);
        User::findOrCreate();
    }

    public function testFindOrCreateNew()
    {
        User::deleteAll();
        $user = User::findOrCreate('first_last');

        $this->assertEquals(36, strlen($user->uuid));
        $this->assertNotNull($user->created);
    }

    public function testFindOrCreateExisting()
    {
        $existing = $this->users('user1');
        $byUsername = User::findOrCreate($existing->idp_username);
        $this->assertEquals($existing->id, $byUsername->id);

        $byEmail = User::findOrCreate(null, $existing->email);
        $this->assertEquals($existing->id, $byEmail->id);

        $byEmployeeId = User::findOrCreate(null, null, $existing->employee_id);
        $this->assertEquals($existing->id, $byEmployeeId->id);
    }

    public function testFindOrCreateDoesntExist()
    {
        $this->expectException(\common\components\personnel\NotFoundException::class);
        User::findOrCreate('doesnt_exist');
    }

    public function testUpdateProfileIfNeeded()
    {
        $user = $this->users('user1');

        $personnelData = new PersonnelUser();
        $personnelData->uuid = $user->uuid;
        $personnelData->firstName = $user->first_name;
        $personnelData->lastName = $user->last_name;
        $personnelData->displayName = $user->display_name;
        $personnelData->username = $user->idp_username;
        $personnelData->email = $user->email;
        $personnelData->hide = $user->hide;

        /*
         * Make no changes and ensure it is not updated
         */
        $changed = $user->updateProfileIfNeeded($personnelData);
        $this->assertFalse($changed);

        /*
         * Test changed for each property. uuid cannot be changed
         */
        $personnelData->uuid = 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx';
        $changed = $user->updateProfileIfNeeded($personnelData);
        $this->assertFalse($changed);

        $personnelData->firstName .= 'a';
        $changed = $user->updateProfileIfNeeded($personnelData);
        $this->assertTrue($changed);

        $personnelData->lastName .= 'a';
        $changed = $user->updateProfileIfNeeded($personnelData);
        $this->assertTrue($changed);

        $personnelData->displayName .= 'a';
        $changed = $user->updateProfileIfNeeded($personnelData);
        $this->assertTrue($changed);

        $personnelData->username .= 'a';
        $changed = $user->updateProfileIfNeeded($personnelData);
        $this->assertTrue($changed);

        $personnelData->email = 'a' . $personnelData->email;
        $changed = $user->updateProfileIfNeeded($personnelData);
        $this->assertTrue($changed);

        $personnelData->hide = ($personnelData->hide === 'no') ? 'yes' : 'no';
        $changed = $user->updateProfileIfNeeded($personnelData);
        $this->assertTrue($changed);
    }

    public function testGetPersonnelUser()
    {
        $user = $this->users('user1');
        $personnelData = $user->getPersonnelUser();
        $this->assertInstanceOf('common\components\personnel\PersonnelUser', $personnelData);
    }

    public function testSupervisor()
    {
        $user = $this->users('user1');
        $this->assertTrue($user->hasSupervisor());
        $this->assertEquals('supervisor@domain.org', $user->getSupervisorEmail());
    }

    public function testGetMaskedMethods()
    {
        $this->markTestSkipped('test is broken because methods were moved to broker');
        $user = $this->users('user1');
        $methods = $user->getMaskedMethods();
        $this->assertTrue(is_array($methods));
        $this->assertEquals(4, count($methods)); // phone method is not returned

        foreach ($methods as $method) {
            if ($method['type'] == 'primary') {
                $this->assertEquals('f****_l**t@o***********.o**', $method['value']);
            } elseif ($method['type'] == 'supervisor') {
                $this->assertEquals('s********r@d*****.o**', $method['value']);
            } elseif ($method['type'] == 'email' && $method['uid'] == '22222222222222222222222222222222') {
                $this->assertEquals('e**************9@d*****.o**', $method['value']);
            } elseif ($method['type'] == 'email' && $method['uid'] == '33333333333333333333333333333333') {
                $this->fail('Unverified method present in getMaskedMethods call');
            }

        }

    }

    public function testGetPersonnelUserFromInterface()
    {
        $user = $this->users('user1');
        // test finding by employee_id
        $personnelUser = $user->getPersonnelUserFromInterface();
        $this->assertInstanceOf(PersonnelUser::class, $personnelUser);

        $user->employee_id = null;
        // test finding by username
        $personnelUser = $user->getPersonnelUserFromInterface();
        $this->assertInstanceOf(PersonnelUser::class, $personnelUser);

        $user->idp_username = null;
        // test finding by email
        $personnelUser = $user->getPersonnelUserFromInterface();
        $this->assertInstanceOf(PersonnelUser::class, $personnelUser);

        $user->email = null;
        // test exception after unsetting email
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(1456690741);
        $user->getPersonnelUserFromInterface();
    }

    public function testFindIdentity()
    {
        $expected = $this->users('user1');
        $user = User::findIdentity($expected->id);
        $this->assertInstanceOf(User::class, $user);
    }

    public function testFindIdentityByAccessToken()
    {
        $expected = $this->users('user1');
        $user = User::findIdentityByAccessToken('user1');
        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals($expected->uuid, $user->uuid);
    }

    public function testGetAuthKey()
    {
        $user = $this->users('user1');
        $this->assertNull($user->getAuthKey());
    }

    public function testGetAuthUser()
    {
        $user = $this->users('user1');
        $authUser = $user->getAuthUser();
        $this->assertInstanceOf(\common\components\auth\User::class, $authUser);
        $this->assertEquals($user->first_name, $authUser->firstName);
        $this->assertEquals($user->last_name, $authUser->lastName);
        $this->assertEquals($user->email, $authUser->email);
        $this->assertEquals($user->employee_id, $authUser->employeeId);
        $this->assertEquals($user->idp_username, $authUser->idpUsername);
    }

    public function testGetVerifiedMethods()
    {
        $this->markTestSkipped('test is broken because methods were moved to broker');

        $user = $this->users('user1');
        $methods = $user->getVerifiedMethods();

        $verifiedCount = 0;
        foreach ($user->methods as $method) {
            if ($method['verified'] === 1) {
                $verifiedCount++;
            }
        }
        $this->assertEquals($verifiedCount, count($methods));
    }

    public function testGetPasswordMeta()
    {
        $user = $this->users('user1');
        $pwMeta = $user->getPasswordMeta();
        $this->assertNotNull($pwMeta);
        $this->assertArrayHasKey('last_changed', $pwMeta);
        $this->assertArrayHasKey('expires', $pwMeta);
    }

    public function testIsEmailInUseByOtherUser()
    {
        $user1 = $this->users('user1');
        $user2 = $this->users('user2');

        $this->assertFalse($user1->isEmailInUseByOtherUser($user1->email));
        $this->assertTrue($user1->isEmailInUseByOtherUser($user2->email));
        $this->assertFalse($user1->isEmailInUseByOtherUser('made-up-email@domain.com'));

    }

    public function testRefreshPersonnelDataForUserWithSpecificEmail()
    {
        /*
         * Test user who does not have updated information in personnel
         */
        $user1 = $this->users('user1');
        User::refreshPersonnelDataForUserWithSpecificEmail($user1->email);
        $notupdated = User::findOne(['id' => $user1->id]);
        $this->assertEquals($user1->email, $notupdated->email);

        /*
         * Test refreshing user who has updated info in personnel
         */
        $user3 = $this->users('user3');
        User::refreshPersonnelDataForUserWithSpecificEmail($user3->email);
        $updated = User::findOne(['id' => $user3->id]);
        $this->assertNotEquals($user3->email, $updated->email);
    }
}
