<?php

namespace tests\unit\common\models;

use common\components\personnel\NotFoundException;
use common\components\personnel\PersonnelUser;
use common\models\User;
use PHPUnit\Framework\TestCase;
use tests\helpers\BrokerUtils;

/**
 * Class UserTest
 * @package tests\unit\common\models
 */
class UserTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // Wire the real IdBroker so BrokerUtils can insert test users
        BrokerUtils::insertFakeUsers();
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function makePersonnelUser(array $overrides = []): PersonnelUser
    {
        $defaults = [
            'uuid'           => 'ccd9bb38-a656-4c62-80ba-2428468599b3',
            'employeeId'     => '111111',
            'firstName'      => 'User',
            'lastName'       => 'One',
            'displayName'    => 'User One',
            'username'       => 'first_last',
            'email'          => 'first_last@organization.org',
            'supervisorEmail'=> 'supervisor@domain.org',
            'lastLogin'      => '2024-01-01T00:00:00Z',
            'authType'       => null,
        ];

        $data = array_merge($defaults, $overrides);

        $pu = new PersonnelUser();
        $pu->uuid            = $data['uuid'];
        $pu->employeeId      = $data['employeeId'];
        $pu->firstName       = $data['firstName'];
        $pu->lastName        = $data['lastName'];
        $pu->displayName     = $data['displayName'];
        $pu->username        = $data['username'];
        $pu->email           = $data['email'];
        $pu->supervisorEmail = $data['supervisorEmail'];
        $pu->lastLogin       = $data['lastLogin'];
        $pu->authType        = $data['authType'];

        return $pu;
    }

    // ------------------------------------------------------------------
    // createFromPersonnelUser
    // ------------------------------------------------------------------

    public function testCreateFromPersonnelUser()
    {
        $pu   = $this->makePersonnelUser();
        $user = User::createFromPersonnelUser($pu);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals($pu->uuid, $user->uuid);
        $this->assertEquals($pu->employeeId, $user->employee_id);
        $this->assertEquals($pu->firstName, $user->first_name);
        $this->assertEquals($pu->lastName, $user->last_name);
        $this->assertEquals($pu->displayName, $user->display_name);
        $this->assertEquals($pu->username, $user->idp_username);
        $this->assertEquals($pu->email, $user->email);
        $this->assertNull($user->auth_type);
    }

    public function testCreateFromPersonnelUserSetsAuthType()
    {
        $pu   = $this->makePersonnelUser(['authType' => User::AUTH_TYPE_LOGIN]);
        $user = User::createFromPersonnelUser($pu);
        $this->assertEquals(User::AUTH_TYPE_LOGIN, $user->auth_type);
    }

    // ------------------------------------------------------------------
    // findOrCreate / findIdentity / findIdentityByAccessToken
    // (uses real IdBroker test instance seeded by BrokerUtils)
    // ------------------------------------------------------------------

    public function testFindOrCreateException()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(1459974492);
        User::findOrCreate();
    }

    public function testFindOrCreateByUsername()
    {
        $user = User::findOrCreate('first_last');
        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('111111', $user->employee_id);
    }

    public function testFindOrCreateByEmail()
    {
        $user = User::findOrCreate(null, 'first_last@organization.org');
        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('111111', $user->employee_id);
    }

    public function testFindOrCreateByEmployeeId()
    {
        $user = User::findOrCreate(null, null, '111111');
        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('111111', $user->employee_id);
    }

    public function testFindOrCreateDoesntExist()
    {
        $this->expectException(NotFoundException::class);
        User::findOrCreate('doesnt_exist');
    }

    public function testFindIdentityByEmployeeId()
    {
        $user = User::findIdentity('111111');
        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('111111', $user->employee_id);
    }

    public function testFindIdentityReturnsNullForMissing()
    {
        $result = User::findIdentity('nonexistent-employee-id');
        $this->assertNull($result);
    }

    // ------------------------------------------------------------------
    // getPersonnelUser / getPersonnelUserFromInterface
    // ------------------------------------------------------------------

    public function testGetPersonnelUser()
    {
        $user = User::findOrCreate('first_last');
        $personnelUser = $user->getPersonnelUser();
        $this->assertInstanceOf(PersonnelUser::class, $personnelUser);
    }

    public function testGetPersonnelUserFromInterface()
    {
        $user = User::findOrCreate('first_last');

        // test finding by employee_id
        $pu = $user->getPersonnelUserFromInterface();
        $this->assertInstanceOf(PersonnelUser::class, $pu);

        // test finding by username
        $user->employee_id = null;
        $pu = $user->getPersonnelUserFromInterface();
        $this->assertInstanceOf(PersonnelUser::class, $pu);

        // test finding by email
        $user->idp_username = null;
        $pu = $user->getPersonnelUserFromInterface();
        $this->assertInstanceOf(PersonnelUser::class, $pu);

        // test exception after unsetting all lookups
        $user->email = null;
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(1456690741);
        $user->getPersonnelUserFromInterface();
    }

    // ------------------------------------------------------------------
    // Supervisor
    // ------------------------------------------------------------------

    public function testSupervisor()
    {
        $user = User::findOrCreate('first_last'); // has manager_email
        $this->assertTrue($user->hasSupervisor());
        $this->assertNotNull($user->getSupervisorEmail());
    }

    // ------------------------------------------------------------------
    // IdentityInterface helpers
    // ------------------------------------------------------------------

    public function testGetId()
    {
        $user = User::findOrCreate('first_last');
        $this->assertEquals('111111', $user->getId());
    }

    public function testGetAuthKey()
    {
        $user = User::findOrCreate('first_last');
        $this->assertNull($user->getAuthKey());
    }

    public function testValidateAuthKey()
    {
        $user = User::findOrCreate('first_last');
        $this->assertFalse($user->validateAuthKey('anything'));
    }

    // ------------------------------------------------------------------
    // getAuthUser
    // ------------------------------------------------------------------

    public function testGetAuthUser()
    {
        $user     = User::findOrCreate('first_last');
        $authUser = $user->getAuthUser();
        $this->assertInstanceOf(\common\components\auth\User::class, $authUser);
        $this->assertEquals($user->first_name,    $authUser->firstName);
        $this->assertEquals($user->last_name,     $authUser->lastName);
        $this->assertEquals($user->email,         $authUser->email);
        $this->assertEquals($user->employee_id,   $authUser->employeeId);
        $this->assertEquals($user->idp_username,  $authUser->idpUsername);
    }

    // ------------------------------------------------------------------
    // isAuthScopeFull
    // ------------------------------------------------------------------

    public function testIsAuthScopeFullLogin()
    {
        $pu   = $this->makePersonnelUser(['authType' => User::AUTH_TYPE_LOGIN]);
        $user = User::createFromPersonnelUser($pu);
        $this->assertTrue($user->isAuthScopeFull());
    }

    public function testIsAuthScopeFullReset()
    {
        $pu   = $this->makePersonnelUser(['authType' => User::AUTH_TYPE_RESET]);
        $user = User::createFromPersonnelUser($pu);
        $this->assertFalse($user->isAuthScopeFull());
    }

    public function testIsAuthScopeFullNull()
    {
        $pu   = $this->makePersonnelUser(['authType' => null]);
        $user = User::createFromPersonnelUser($pu);
        $this->assertFalse($user->isAuthScopeFull());
    }

    // ------------------------------------------------------------------
    // getPasswordMeta
    // ------------------------------------------------------------------

    public function testGetPasswordMeta()
    {
        $user   = User::findOrCreate('first_last');
        $pwMeta = $user->getPasswordMeta();
        $this->assertNotNull($pwMeta);
        $this->assertArrayHasKey('last_changed', $pwMeta);
        $this->assertArrayHasKey('expires', $pwMeta);
    }

    // ------------------------------------------------------------------
    // fields / toArray
    // ------------------------------------------------------------------

    public function testFields()
    {
        $user = User::findOrCreate('first_last');
        $arr  = $user->toArray();

        $this->assertArrayHasKey('first_name', $arr);
        $this->assertArrayHasKey('last_name', $arr);
        $this->assertArrayHasKey('idp_username', $arr);
        $this->assertArrayHasKey('email', $arr);
        $this->assertArrayHasKey('auth_type', $arr);
    }

    // ------------------------------------------------------------------
    // getUserFromInviteCode
    // ------------------------------------------------------------------

    public function testGetUserFromInviteCodeNotFound()
    {
        // Passing an obviously invalid invite code should return null
        $user = User::getUserFromInviteCode('not-a-real-invite-code');
        $this->assertNull($user);
    }
}

