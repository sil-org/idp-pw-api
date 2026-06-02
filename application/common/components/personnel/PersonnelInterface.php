<?php

namespace common\components\personnel;

/**
 * Interface PersonnelInterface
 * @package common\components\personnel
 */
interface PersonnelInterface
{
    /**
     * @param mixed $employeeId
     * @return PersonnelUser
     * @throws NotFoundException
     * @throws \Exception
     */
    public function findByEmployeeId($employeeId);

    /**
     * @param mixed $username
     * @return PersonnelUser
     * @throws NotFoundException
     * @throws \Exception
     */
    public function findByUsername($username);

    /**
     * @param mixed $email
     * @return PersonnelUser
     * @throws NotFoundException
     * @throws \Exception
     */
    public function findByEmail($email);

    /**
     * @param mixed $invite
     * @return PersonnelUser
     * @throws NotFoundException
     * @throws \Exception
     */
    public function findByInvite($invite);

    /**
     * @param array $properties
     * @throws NotFoundException
     * @throws \Exception
     * @return void
     */
    public function updateUser($properties);

    /**
     * Store an access token for the given user in the personnel system.
     *
     * @param string $employeeId
     * @param string $authType  One of 'login' or 'reset'
     * @param string $accessTokenHash  HMAC hash of the raw access token
     * @param string $expiration  Datetime string (e.g. '2026-06-01 12:00:00')
     * @throws NotFoundException
     * @throws \Exception
     * @return void
     */
    public function setAccessToken(string $employeeId, string $authType, string $accessTokenHash, string $expiration): void;

    /**
     * Clear the access token for the given user in IdBroker.
     *
     * @param string $employeeId
     * @throws \Exception
     * @return void
     */
    public function clearAccessToken(string $employeeId): void;

    /**
     * Find a user by their access token hash.
     *
     * @param string $accessTokenHash  HMAC hash of the raw access token
     * @return PersonnelUser
     * @throws NotFoundException if no active token matches
     * @throws \Exception
     */
    public function findByAccessToken(string $accessTokenHash): PersonnelUser;
}
