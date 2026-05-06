<?php

namespace tests\mock\personnel;

use common\components\personnel\NotFoundException;
use common\components\personnel\PersonnelInterface;
use common\components\personnel\PersonnelUser;
use yii\base\Component as YiiComponent;

class Component extends YiiComponent implements PersonnelInterface
{
    /**
     * @var string
     */
    public $baseUrl;

    /**
     * @var string
     */
    public $accessToken;

    /**
     * @var boolean
     */
    public $assertValidBrokerIp = true;

    /**
     * @var IPBlock[]
     */
    public $validIpRanges = [];

    public function findByEmployeeId($employeeId)
    {
        $data = include __DIR__ . '/data.php';
        foreach ($data as $user) {
            if ($user['employeeId'] == $employeeId) {
                return $this->createPersonnelUserFromData($user);
            }
        }
        throw new NotFoundException();
    }

    public function findByUsername($username)
    {
        $data = include __DIR__ . '/data.php';
        foreach ($data as $user) {
            if ($user['username'] == $username) {
                return $this->createPersonnelUserFromData($user);
            }
        }
        throw new NotFoundException();
    }

    public function findByEmail($email)
    {
        $data = include __DIR__ . '/data.php';
        foreach ($data as $user) {
            if ($user['email'] == $email) {
                return $this->createPersonnelUserFromData($user);
            }
        }
        throw new NotFoundException();
    }

    public function createPersonnelUserFromData($data)
    {
        $user = new PersonnelUser();
        $user->firstName = $data['firstName'];
        $user->lastName = $data['lastName'];
        $user->displayName = $data['displayName'];
        $user->email = $data['email'];
        $user->employeeId = $data['employeeId'];
        $user->username = $data['username'];
        $user->supervisorEmail = $data['supervisorEmail'];

        return $user;
    }

    /*
     * This method is not used, but added to satisfy the interface.
     * Also note that this class is not used at present, as tests
     * are currently run using an actual broker container. Need
     * to decide whether to eliminate this class or go back to using it.
     */
    public function updateUser($properties)
    {
        throw new \Exception(__METHOD__ . ' has not been implemented in this class.');
    }

    public function findByInvite($invite)
    {
        // The tests choke on a normal exception, but the code in the User model looks out for a NotFoundException
        throw new NotFoundException(__METHOD__ . ' has not been implemented in this class.');
    }
}
