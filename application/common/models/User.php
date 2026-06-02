<?php

namespace common\models;

use common\components\auth\User as AuthUser;
use common\components\passwordStore\PasswordStoreInterface;
use common\components\passwordStore\UserPasswordMeta;
use common\components\personnel\NotFoundException;
use common\components\personnel\PersonnelInterface;
use common\components\personnel\PersonnelUser;
use common\helpers\Utils;
use yii\web\IdentityInterface;
use yii\web\ServerErrorHttpException;

/**
 * Class User
 * @package common\models
 */
class User implements IdentityInterface
{
    public const AUTH_TYPE_LOGIN = 'login';
    public const AUTH_TYPE_RESET = 'reset';

    /** @var string */
    public string $uuid;

    /** @var string */
    public string $employee_id;

    /** @var string */
    public string $first_name;

    /** @var string */
    public string $last_name;

    /** @var string|null */
    public ?string $display_name = null;

    /** @var string */
    public string $idp_username;

    /** @var string */
    public string $email;

    /** @var string|null  One of AUTH_TYPE_LOGIN or AUTH_TYPE_RESET */
    public ?string $auth_type = null;

    /**
     * Holds the cached personnelUser
     * @var PersonnelUser
     */
    public ?PersonnelUser $personnelUser = null;

    /**
     * @return PersonnelInterface
     */
    protected static function getPersonnelComponent(): PersonnelInterface
    {
        return \Yii::$app->personnel;
    }

    /**
     * Create a User instance from a PersonnelUser.
     *
     * @param PersonnelUser $personnelUser
     * @return self
     */
    public static function createFromPersonnelUser(PersonnelUser $personnelUser): self
    {
        $user = new self();
        $user->uuid = $personnelUser->uuid;
        $user->employee_id = (string) $personnelUser->employeeId;
        $user->first_name = $personnelUser->firstName;
        $user->last_name = $personnelUser->lastName;
        $user->display_name = $personnelUser->displayName;
        $user->idp_username = $personnelUser->username;
        $user->email = $personnelUser->email;
        $user->auth_type = $personnelUser->authType;
        $user->personnelUser = $personnelUser;
        return $user;
    }

    /**
     * Return labels for key user attributes (used by Password validation).
     * @return array<string,string>
     */
    public function attributeLabels(): array
    {
        return [
            'uuid'         => \Yii::t('model', 'UUID'),
            'employee_id'  => \Yii::t('model', 'Employee ID'),
            'first_name'   => \Yii::t('model', 'First Name'),
            'last_name'    => \Yii::t('model', 'Last Name'),
            'display_name' => \Yii::t('model', 'Display Name'),
            'idp_username' => \Yii::t('model', 'IDP Username'),
            'email'        => \Yii::t('model', 'Email'),
            'auth_type'    => \Yii::t('model', 'Auth Type'),
        ];
    }

    /**
     * Limit what fields are returned from api calls
     * @return array
     */
    public function fields(): array
    {
        $fields = [
            'uuid',
            'first_name',
            'last_name',
            'display_name',
            'idp_username',
            'email',
            'auth_type',
            'last_login' => function () {
                try {
                    $lastLogin = $this->getPersonnelUser()->lastLogin;
                } catch (\Exception $e) {
                    $lastLogin = null;
                }
                return $lastLogin;
            },
        ];

        $pwMeta = $this->getPasswordMeta();
        if ($pwMeta !== null) {
            $fields['password_meta'] = function (self $model) use ($pwMeta) {
                return $pwMeta;
            };
        }

        $managerEmail = $this->getSupervisorEmail();
        if (! empty($managerEmail)) {
            $fields['manager_email'] = function (self $model) use ($managerEmail) {
                return $managerEmail;
            };
        }

        return $fields;
    }

    /**
     * Serialize this User to an array of its fields() for API responses.
     * @return array
     */
    public function toArray(): array
    {
        $result = [];
        foreach ($this->fields() as $key => $value) {
            if (is_int($key)) {
                $field = $value;
                $result[$field] = $this->$field;
            } else {
                $result[$key] = ($value)($this);
            }
        }
        return $result;
    }

    /**
     * Find a user in IdBroker by username, email, or employee ID.
     * At least one parameter must be provided.
     *
     * @param string|null $username
     * @param string|null $email
     * @param string|null $employeeId
     * @return self
     * @throws \Exception
     * @throws NotFoundException
     */
    public static function findOrCreate($username = null, $email = null, $employeeId = null): self
    {
        if (is_null($username) && is_null($email) && is_null($employeeId)) {
            throw new \Exception(
                'You must provide a username, email address, or employee id to find or create a user',
                1459974492
            );
        }

        try {
            $personnel = self::getPersonnelComponent();

            if (! is_null($employeeId)) {
                $personnelUser = $personnel->findByEmployeeId($employeeId);
            } elseif (! is_null($username)) {
                $personnelUser = $personnel->findByUsername($username);
            } else {
                $personnelUser = $personnel->findByEmail($email);
            }
        } catch (\Exception $e) {
            if ($e instanceof NotFoundException) {
                throw $e;
            }

            \Yii::error([
                'action' => 'personnel find user',
                'status' => 'error',
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
            throw new ServerErrorHttpException(
                'There was a problem retrieving your information from the personnel system. Please wait a few '
                . 'minutes and try again.',
                1470164077
            );
        }

        return self::createFromPersonnelUser($personnelUser);
    }

    /**
     * @return PersonnelUser
     * @throws \Exception
     */
    public function getPersonnelUser(): PersonnelUser
    {
        if (! empty($this->personnelUser)) {
            return $this->personnelUser;
        }

        try {
            $this->personnelUser = $this->getPersonnelUserFromInterface();
        } catch (\Exception $e) {
            \Yii::error([
                'action' => 'get personnel user',
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
            throw new \Exception('Unexpected error accessing personnel system.', 1553532344, $e);
        }

        return $this->personnelUser;
    }

    /**
     * @return bool
     */
    public function hasSupervisor(): bool
    {
        return $this->getSupervisorEmail() !== null;
    }

    /**
     * @return null|string
     * @throws \Exception
     */
    public function getSupervisorEmail(): ?string
    {
        $personnelUser = $this->getPersonnelUser();
        return $personnelUser->supervisorEmail;
    }

    /**
     * @return PersonnelUser
     * @throws \Exception
     */
    public function getPersonnelUserFromInterface(): PersonnelUser
    {
        $personnel = self::getPersonnelComponent();

        if ($this->employee_id) {
            return $personnel->findByEmployeeId($this->employee_id);
        } elseif ($this->idp_username) {
            return $personnel->findByUsername($this->idp_username);
        } elseif ($this->email) {
            return $personnel->findByEmail($this->email);
        } else {
            throw new \Exception('Not enough information to find personnel data', 1456690741);
        }
    }

    /**
     * Finds an identity by the given ID (employee_id).
     *
     * @param string $id the employee_id to be looked for
     * @return self|null the identity object that matches the given ID.
     */
    public static function findIdentity($id): ?self
    {
        try {
            $personnel = self::getPersonnelComponent();
            $personnelUser = $personnel->findByEmployeeId($id);
            return self::createFromPersonnelUser($personnelUser);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Finds an identity by the given access token.
     * Delegates to IdBroker to look up the user associated with this token hash.
     *
     * @param string $token the raw access token from the cookie
     * @param mixed $type
     * @return self|null the identity object that matches the given token.
     */
    public static function findIdentityByAccessToken($token, $type = null): ?self
    {
        $hash = Utils::getAccessTokenHash($token);
        try {
            $personnel = self::getPersonnelComponent();
            $personnelUser = $personnel->findByAccessToken($hash);
            return self::createFromPersonnelUser($personnelUser);
        } catch (NotFoundException $e) {
            return null;
        } catch (\Exception $e) {
            \Yii::error([
                'action' => 'findIdentityByAccessToken',
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Returns the ID of this user (employee_id).
     *
     * @return string current user ID (employee_id)
     */
    public function getId(): string
    {
        return $this->employee_id;
    }

    /**
     * Returning null to explicitly disable this
     * @return string|null current user auth key
     */
    public function getAuthKey(): ?string
    {
        return null;
    }

    /**
     * Returning false to explicitly disable this
     * @param string $authKey
     * @return bool if auth key is valid for current user
     */
    public function validateAuthKey($authKey): bool
    {
        return false;
    }

    /**
     * Get this user as an AuthUser object
     * @return AuthUser
     */
    public function getAuthUser(): AuthUser
    {
        $authUser = new AuthUser();
        $authUser->firstName = $this->first_name;
        $authUser->lastName = $this->last_name;
        $authUser->email = $this->email;
        $authUser->employeeId = $this->employee_id;
        $authUser->idpUsername = $this->idp_username;

        return $authUser;
    }

    /**
     * @return string
     */
    public function getDisplayName(): string
    {
        if (empty($this->display_name)) {
            return $this->first_name . ' ' . $this->last_name;
        } else {
            return $this->display_name;
        }
    }

    /**
     * @return array<array>
     */
    public function getMethodsAndPersonnelEmails(): array
    {
        $methods = Method::getMethods($this->employee_id);

        $numVerified = 0;
        foreach ($methods as $key => $method) {
            $methods[$key]['type'] = 'email';
            $numVerified += ($method['verified'] === true);
        }

        $methods[] = [
            'type' => Method::TYPE_PRIMARY,
            'value' => $this->email,
        ];

        /*
         * If alternate recovery methods exist, don't include the manager.
         */
        if ($numVerified > 0) {
            return $methods;
        }

        if ($this->hasSupervisor()) {
            $methods[] = [
                'type' => Method::TYPE_SUPERVISOR,
                'value' => $this->getSupervisorEmail(),
            ];
        }

        return $methods;
    }

    /**
     * Return array of arrays of masked out methods
     * @return array<array>
     */
    public function getMaskedMethods(): array
    {
        $methods = $this->getMethodsAndPersonnelEmails();
        foreach ($methods as $key => $method) {
            if ($method['verified'] ?? true) {
                $methods[$key]['value'] = Utils::maskEmail($method['value']);
            } else {
                unset($methods[$key]);
            }
        }
        return array_values($methods);
    }

    /**
     * Get password metadata from password store interface, and return in an array
     * for use in an API response.
     * @return array|null
     */
    public function getPasswordMeta(): ?array
    {
        /** @var PasswordStoreInterface $passwordStore */
        $passwordStore = \Yii::$app->passwordStore;

        try {
            /** @var UserPasswordMeta $pwMeta */
            $pwMeta = $passwordStore->getMeta($this->employee_id);
        } catch (\Exception $e) {
            \Yii::error([
                'action' => 'getPasswordMeta',
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
            return null;
        }

        return [
            'last_changed' => $pwMeta->passwordLastChangeDate,
            'expires' => $pwMeta->passwordExpireDate,
        ];
    }

    /**
     * Is user account locked?
     * @return bool
     */
    public function isLocked(): bool
    {
        /** @var PasswordStoreInterface $passwordStore */
        $passwordStore = \Yii::$app->passwordStore;

        try {
            $isLocked = $passwordStore->isLocked($this->employee_id);
        } catch (\Exception $e) {
            \Yii::error([
                'action' => 'isLocked',
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
            return true;
        }

        return $isLocked;
    }

    /**
     * @param string $newPassword
     * @throws \Exception
     * @throws \yii\web\BadRequestHttpException
     */
    public function setPassword(string $newPassword): void
    {
        $password = Password::create($this, $newPassword);
        $password->user = $this;
        $password->save();

        /*
         * Check for request to get user's IP address for logging
         */
        $ipAddress = Utils::getClientIp(\Yii::$app->request);
        if (empty($ipAddress)) {
            $ipAddress = 'Not web request';
        }

        \Yii::info([
            'action' => 'password changed',
            'auth_method' => $this->auth_type,
            'employee_id' => $this->employee_id,
            'ip_address' => $ipAddress,
            'status' => 'success',
        ]);

        if ($this->auth_type == self::AUTH_TYPE_RESET) {
            $this->destroyAccessToken();
        }
    }

    /**
     * Generate a new access token, store it in IdBroker, and set it as an HttpOnly cookie.
     *
     * @param string $authType  One of AUTH_TYPE_LOGIN or AUTH_TYPE_RESET
     * @return string  The raw (unhashed) access token value
     * @throws \Exception
     */
    public function createAccessToken(string $authType): string
    {
        $accessToken = Utils::generateRandomString(32);
        $accessTokenHash = Utils::getAccessTokenHash($accessToken);
        $expiration = time() + \Yii::$app->params['accessTokenLifetime'];

        $personnel = self::getPersonnelComponent();
        $personnel->setAccessToken($this->employee_id, $authType, $accessTokenHash, Utils::getDatetime($expiration));

        $this->auth_type = $authType;

        $secure = !in_array(YII_ENV, ['dev', 'test']);
        \Yii::$app->response->cookies->add(new \yii\web\Cookie([
            'name' => 'access_token',
            'value' => $accessToken,
            'expire' => $expiration,
            'httpOnly' => true,
            'secure' => $secure,
            'sameSite' => 'Lax',
        ]));

        return $accessToken;
    }

    /**
     * Clear the access token from IdBroker and update local auth_type.
     *
     * @throws \Exception
     */
    public function destroyAccessToken(): void
    {
        $personnel = self::getPersonnelComponent();
        $personnel->clearAccessToken($this->employee_id);
        $this->auth_type = null;
    }

    /**
     * Check auth level. Returns true if user is authenticated by a full login.
     *
     * @return bool
     */
    public function isAuthScopeFull(): bool
    {
        return $this->auth_type === self::AUTH_TYPE_LOGIN;
    }

    /**
     * @param string $inviteCode
     * @return self|null
     * @throws \Exception
     * @throws NotFoundException
     * @throws \Sil\Idp\IdBroker\Client\ServiceException
     */
    public static function getUserFromInviteCode(string $inviteCode): ?self
    {
        $personnel = self::getPersonnelComponent();
        try {
            $personnelUser = $personnel->findByInvite($inviteCode);
            return self::createFromPersonnelUser($personnelUser);
        } catch (NotFoundException $e) {
            return null;
        }
    }
}
