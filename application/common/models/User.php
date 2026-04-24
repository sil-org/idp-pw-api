<?php

namespace common\models;

use common\components\auth\User as AuthUser;
use common\components\passwordStore\PasswordStoreInterface;
use common\components\passwordStore\UserPasswordMeta;
use common\components\personnel\NotFoundException;
use common\components\personnel\PersonnelInterface;
use common\components\personnel\PersonnelUser;
use common\helpers\Utils;
use yii\helpers\ArrayHelper;
use yii\web\IdentityInterface;
use yii\web\ServerErrorHttpException;

/**
 * Class User
 * @package common\models
 * @method User self::findOne([])
 */
class User extends UserBase implements IdentityInterface
{
    public const AUTH_TYPE_LOGIN = 'login';
    public const AUTH_TYPE_RESET = 'reset';

    /**
     * Holds personnelUser
     * @var PersonnelUser
     */
    public $personnelUser;

    /**
     * @return PersonnelInterface
     */
    protected static function getPersonnelComponent(): PersonnelInterface
    {
        return \Yii::$app->personnel;
    }

    /**
     * Validation rules, applies User rules before UserBase rules
     * @return string[]
     */
    public function rules()
    {
        return ArrayHelper::merge(
            [
                [
                    ['created'], 'default', 'value' => Utils::getDatetime(),
                ],

                [
                    ['email'], 'email'
                ],
            ],
            parent::rules()
        );
    }

    /**
     * Limit what fields are returned from api calls
     * @return array
     */
    public function fields()
    {
        $fields = [
            'uuid',
            'first_name',
            'last_name',
            'display_name',
            'idp_username',
            'email',
            'auth_type',
            'hide',
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
     * Find or create local user. Fetch/update user data from personnel.
     * @param string|null $username [default=null]
     * @param string|null $email [default=null]
     * @param string|null $employeeId [default=null]
     * @return User
     * @throws \Exception
     * @throws NotFoundException
     */
    public static function findOrCreate($username = null, $email = null, $employeeId = null)
    {
        /*
         * Make sure at least one method was provided
         */
        if (is_null($username) && is_null($email) && is_null($employeeId)) {
            throw new \Exception(
                'You must provide a username, email address, or employee id to find or create a user',
                1459974492
            );
        }

        /*
         * Always call Personnel system in case employee is no longer employed
         */
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
            /*
             * If user was not found just re-throw exception
             */
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
                'There was a problem retrieving your information from the personnel system. Please wait a few ' .
                'minutes and try again.',
                1470164077
            );
        }


        $user = self::findOne(['employee_id' => $personnelUser->employeeId]);
        if (! $user) {
            $user = new User();
            $user->uuid = $personnelUser->uuid;
            $user->employee_id = (string)$personnelUser->employeeId;
            $user->first_name = $personnelUser->firstName;
            $user->last_name = $personnelUser->lastName;
            $user->display_name = $personnelUser->displayName;
            $user->idp_username = $personnelUser->username;
            $user->email = $personnelUser->email;
            $user->hide = $personnelUser->hide;
            $user->saveOrError('Unable to create new user', 1456760294);
        } else {
            $user->updateProfileIfNeeded($personnelUser);
        }

        return $user;
    }


    /**
     * Update local user record if given properties are different than currently stored
     * @param PersonnelUser $personnelUser
     * @return bool True if profile was updated, false if no updates were needed
     * @throws \Exception
     */
    public function updateProfileIfNeeded($personnelUser)
    {
        $dirty = false;
        $properties = [
            'first_name' => $personnelUser->firstName,
            'last_name' => $personnelUser->lastName,
            'display_name' => $personnelUser->displayName,
            'idp_username' => $personnelUser->username,
            'email' => $personnelUser->email,
            'hide' => $personnelUser->hide,
        ];

        foreach ($properties as $property => $value) {
            if ($this->$property != $value) {
                $dirty = true;
                $this->$property = $value;
            }
        }

        /*
         * Only allow uuid to be changed if blank. Allows for migration of existing records.
         */
        if (empty($this->uuid)) {
            $dirty = true;
            $this->uuid = $personnelUser->uuid;
        }

        if ($dirty) {

            /*
             * Check that email is not already in use by another user
             * If it is, refresh that user's profile from personnel in
             * case their email address has also changed
             */
            if ($this->isEmailInUseByOtherUser($personnelUser->email)) {
                self::refreshPersonnelDataForUserWithSpecificEmail($personnelUser->email);
            }

            /*
             * Save updated profile
             */
            $this->saveOrError('Unable to update profile', 1456760819);
            return true;
        }
        return false;
    }

    /**
     * In case where email address has been reassigned to $this user, refresh profile for user
     * with email address currently in case their email address has changed too
     * @param string $email
     * @return bool Whether or not the profile was updated in the database
     * @throws \Exception
     */
    public static function refreshPersonnelDataForUserWithSpecificEmail($email)
    {
        $user = self::findOne(['email' => $email]);
        if ($user === null) {
            throw new \Exception(
                sprintf('User with email %s not found', $email),
                1499817281
            );
        }

        try {
            $personnel = self::getPersonnelComponent();
            $personnelUser = $personnel->findByEmployeeId($user->employee_id);
        } catch (NotFoundException $e) {
            /*
             * User no longer exists in personnel system, so update their email to release for use by other users
             */
            $personnelUser = new PersonnelUser();
            $personnelUser->firstName = $user->first_name;
            $personnelUser->lastName = $user->last_name;
            $personnelUser->displayName = $user->display_name;
            $personnelUser->username = $user->idp_username;
            $personnelUser->email = sprintf('notfound-%s-%s', $user->email, time());
            $personnelUser->hide = $user->hide;

            \Yii::error([
                'action' => 'updateProfileForExistingUserWithEmailFromPersonnel',
                'message' => sprintf(
                    'When updating profile for existing user with email address % they could not be ' .
                    'found in personnel so their email was updated to %s',
                    $user->email,
                    $personnelUser->email
                )
            ]);
        }

        return $user->updateProfileIfNeeded($personnelUser);
    }

    /**
     * Check if email address is in use by a different user already
     * @param string $email
     * @return bool
     */
    public function isEmailInUseByOtherUser($email)
    {
        $user = self::findOne(['email' => $email]);

        return $user !== null && $user->id != $this->id;
    }

    /**
     * Return array of arrays of masked out methods
     * @return array<array>
     */
    public function getMaskedMethods()
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
     * @return PersonnelUser
     * @throws \Exception
     */
    public function getPersonnelUser()
    {
        if (! empty($this->personnelUser)) {
            return $this->personnelUser;
        }

        $sessionAvailable = Utils::isSessionAvailable();

        if ($sessionAvailable && is_array(\Yii::$app->session->get('personnelUser'))) {
            $this->personnelUser = \Yii::$app->session->get('personnelUser');
            return $this->personnelUser;
        }

        try {
            /*
             * Fetch data from Personnel system and cache it
             */
            $this->personnelUser = $this->getPersonnelUserFromInterface();
        } catch (\Exception $e) {
            \Yii::error([
                'action' => 'get personnel user',
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
            throw new \Exception('Unexpected error accessing personnel system.', 1553532344, $e);
        }
        \Yii::$app->session->set('personnelUser', $this->personnelUser);

        return $this->personnelUser;
    }

    /**
     * @return bool
     */
    public function hasSupervisor()
    {
        return $this->getSupervisorEmail() !== null;
    }

    /**
     * @return null|string
     * @throws \Exception
     */
    public function getSupervisorEmail()
    {
        $personnelUser = $this->getPersonnelUser();
        return $personnelUser->supervisorEmail;
    }

    /**
     * @return PersonnelUser
     * @throws \Exception
     */
    public function getPersonnelUserFromInterface()
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
     * Finds an identity by the given ID.
     *
     * @param string|integer $id the ID to be looked for
     * @return User|null the identity object that matches the given ID.
     */
    public static function findIdentity($id)
    {
        return static::findOne($id);
    }

    /**
     * Finds an identity by the given token.
     *
     * @param string $token the token to be looked for
     * @return User|null the identity object that matches the given token.
     */
    public static function findIdentityByAccessToken($token, $type = null)
    {
        $accessTokenHash = Utils::getAccessTokenHash($token);
        return static::find()->where(['access_token' => $accessTokenHash])
            ->andWhere(['>', 'access_token_expiration', Utils::getDatetime()])
            ->one();
    }

    /**
     * @return int|string current user ID
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Returning null to explicitly disable this
     * @return string|null current user auth key
     */
    public function getAuthKey()
    {
        return null;
    }

    /**
     * Returning false to explicitly disable this
     * @param string $authKey
     * @return boolean if auth key is valid for current user
     */
    public function validateAuthKey($authKey)
    {
        return false;
    }

    /**
     * Get this user as an AuthUser object
     * @return \common\components\auth\User
     */
    public function getAuthUser()
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
    public function getDisplayName()
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
    public function getMethodsAndPersonnelEmails()
    {
        $methods = Method::getMethods($this->employee_id);

        $numVerified = 0;
        foreach ($methods as $key => $method) {
            $methods[$key]['type'] = 'email';
            $numVerified += ($method['verified'] === true);
        }

        $methods[] = [
            'type' => Reset::TYPE_PRIMARY,
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
                'type' => Reset::TYPE_SUPERVISOR,
                'value' => $this->getSupervisorEmail(),
            ];
        }

        return $methods;
    }

    /**
     * Get password metadata from password store interface, and return in an array
     * for use in an API response.
     * @return array|null
     */
    public function getPasswordMeta()
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
                'action' => 'getPasswordMeta',
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
    public function setPassword($newPassword)
    {
        $password = Password::create($this, $newPassword);
        $password->user = $this;
        $password->save();

        $this->saveOrError('Unable to save user profile after password change', 1466104537);

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
     * @param string $authType
     * @return string
     * @throws \Exception
     */
    public function createAccessToken($authType)
    {
        /*
         * Create access_token and update user
         */
        $accessToken = Utils::generateRandomString(32);
        /*
         * Store accessToken for auth
         */
        $this->auth_type = $authType;
        $this->access_token = Utils::getAccessTokenHash($accessToken);
        $this->access_token_expiration = Utils::getDatetime(
            time() + \Yii::$app->params['accessTokenLifetime']
        );
        $this->saveOrError('Unable to create access token', 1465833228);

        $secure = !in_array(YII_ENV, ['dev', 'test']);
        \Yii::$app->response->cookies->add(new \yii\web\Cookie([
          'name' => 'access_token',
          'value' => $accessToken,
          'expire' => $this->access_token_expiration,
          'httpOnly' => true, // Ensures the cookie is not accessible via JavaScript
          'secure' => $secure,   // Ensures the cookie is sent only over HTTPS
          'sameSite' => 'Lax', // Adjust as needed
        ]));
        return $accessToken;
    }

    /**
     *
     */
    public function destroyAccessToken(): void
    {
        $this->access_token = null;
        $this->access_token_expiration = null;
        $this->auth_type = null;

        $this->saveOrError('destroy access token');
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
     * Called by Yii before an insert or update
     *
     * @param bool $insert
     * @return bool
     * @throws ServerErrorHttpException
     */
    public function beforeSave($insert): bool
    {
        if (! parent::beforeSave($insert)) {
            return false;
        }

        if ($this->getOldAttribute('hide') != $this->getAttribute('hide')) {
            try {
                $personnel = self::getPersonnelComponent();
                $personnel->updateUser([
                    'employee_id' => $this->employee_id,
                    'hide' => $this->hide,
                ]);
            } catch (\Exception $e) {
                \Yii::error([
                    'action' => 'personnel update',
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ], __METHOD__);
            }
        }

        return true;
    }

    /**
     * @param string $inviteCode
     * @return User|null
     * @throws \Exception
     * @throws NotFoundException
     * @throws \Sil\Idp\IdBroker\Client\ServiceException
     */
    public static function getUserFromInviteCode(string $inviteCode)
    {
        $personnel = self::getPersonnelComponent();
        try {
            $personnelUser = $personnel->findByInvite($inviteCode);
            return self::findOrCreate(null, null, $personnelUser->employeeId);
        } catch (NotFoundException $e) {
            return null;
        }
    }

    /**
     * Save attributes to database. In case of error, log an error message and optionally throw
     * an exception.
     * @param string $msg error message
     * @param int $code exception code; if not provided, no exception will be thrown
     * @throws \Exception if the save failed for any reason
     */
    private function saveOrError(string $msg, int $code = null): void
    {
        if (! $this->save()) {
            \Yii::error([
                'action' => $msg,
                'status' => 'error',
                'error' => json_encode($this->getFirstErrors()),
            ]);
            if ($code !== null) {
                throw new \Exception($msg, $code);
            }
        }
    }
}
