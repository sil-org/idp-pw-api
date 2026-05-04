<?php

namespace common\components\passwordStore;

use Exception;
use InvalidArgumentException;
use common\components\passwordStore\AccountLockedException;
use common\components\passwordStore\PasswordStoreInterface;
use common\components\passwordStore\PasswordReuseException;
use common\components\passwordStore\UserNotFoundException;
use common\components\passwordStore\UserPasswordMeta;
use common\components\passwordStore\PasswordStoreException;
use yii\base\Component;

class Multiple extends Component implements PasswordStoreInterface
{
    /** @var array */
    public $passwordStoresConfig;

    /** @var PasswordStoreInterface[] */
    protected $passwordStores = [];

    public $displayName = 'Multiple';


    /**
     * See if all the password store backends are available.
     *
     * @param string $employeeId The Employee ID to use to see if each password
     *     store is available.
     * @param string $taskDescription A short description of what is about to be
     *     attempted (e.g. 'set the password') if all backends are available.
     * @throws NotAttemptedException
     */
    protected function assertAllBackendsAreAvailable(
        $employeeId,
        $taskDescription
    ) {
        foreach ($this->passwordStores as $passwordStore) {
            try {
                $passwordStore->getMeta($employeeId);
            } catch (Exception $e) {
                throw new \Exception(sprintf(
                    'Did not attempt to %s because not all of the backends are '
                    . 'available. The %s password store gave this error when '
                    . 'asked for the specified user (%s): %s',
                    $taskDescription,
                    $passwordStore->getDisplayName(),
                    var_export($employeeId, true),
                    $e->getMessage()
                ), 1498163919, $e);
            }
        }
    }

    public function init()
    {
        parent::init();

        if (empty($this->passwordStoresConfig)) {
            throw new InvalidArgumentException(
                'You must provide config for at least one password store.',
                1498162679
            );
        }

        foreach ($this->passwordStoresConfig as $passwordStoreConfig) {
            $className = $passwordStoreConfig['class'];

            $configForClass = $passwordStoreConfig;
            unset($configForClass['class']);

            $this->passwordStores[] = new $className($configForClass);
        }
    }

    /**
     * Get metadata about user's password, including its expiration date and
     * when it was last changed.
     *
     * NOTE: This will simply pass along the request to the first password store
     *       defined in its list.
     *
     * @param string $employeeId The Employee ID of the user.
     * @return UserPasswordMeta
     * @throws UserNotFoundException
     * @throws AccountLockedException
     */
    public function getMeta($employeeId): UserPasswordMeta
    {
        return $this->passwordStores[0]->getMeta($employeeId);
    }

    /**
     * See if all of the password stores seem to be available/responding, and if
     * so set the user's password in all of the defined password stores. If any
     * of the password stores fail the "pre-check", this will not attempt to set
     * the user's password on any of them, instead throwing a PasswordStoreException
     * Thereafter, if any of the password stores fail, a PasswordStoreException will
     * be thrown with a message detailing which ones succeeded and which ones
     * failed.
     *
     * NOTE: If successful, this will return the UserPasswordMeta returned by
     *       the first password store defined in its list.
     *
     * @param string $employeeId The Employee ID of the user.
     * @param string $password The new password.
     * @return UserPasswordMeta
     * @throws PasswordStoreException
     * @throws Exception
     * @throws UserNotFoundException
     * @throws AccountLockedException
     * @throws PasswordReuseException
     */
    public function set($employeeId, $password): UserPasswordMeta
    {
        $this->assertAllBackendsAreAvailable($employeeId, 'set the password');

        $responses = [];
        $successes = [];
        $errors = [];
        foreach ($this->passwordStores as $passwordStore) {
            try {
                $responses[] = $passwordStore->set($employeeId, $password);
                $successes[] = $passwordStore->getDisplayName();
            } catch (Exception $e) {
                if ($e instanceof PasswordReuseException) {
                    // Be aware that this does not include information about how many backends the password
                    // was successfully changed in
                    throw $e;
                }

                \Yii::error([
                    'action' => 'set password',
                    'status' => 'error',
                    'passwordStore' => $passwordStore->getDisplayName(),
                    'message' => $e->getMessage(),
                ]);
                $errors[] = $passwordStore->getDisplayName();
            }
        }

        if (count($errors) > 0) {
            $conjunction = ' ' . \Yii::t('app', 'and') . ' ';
            $errorMessage = \Yii::t(
                'app',
                count($successes) > 0 ? 'Multiple.SetPartialSuccess' : 'Multiple.SetFailed',
                [
                    'successes' => implode($conjunction, $successes),
                    'errors' => implode($conjunction, $errors),
                    'supportName' => \Yii::$app->params['support']['name'],
                    'supportEmail' => \Yii::$app->params['support']['email'],
                ]
            );
            throw new PasswordStoreException($errorMessage, 1498162884);
        }

        return $responses[0];
    }

    /**
     * @param string $employeeId
     * @return bool
     * @throws UserNotFoundException
     */
    public function isLocked(string $employeeId): bool
    {
        foreach ($this->passwordStores as $passwordStore) {
            if ($passwordStore->isLocked($employeeId)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Assess a potential new password for a user
     * @param string $employeeId
     * @param string $password
     * @return bool
     * @throws \Exception
     * @throws \common\components\passwordStore\UserNotFoundException
     */
    public function assess($employeeId, $password)
    {
        foreach ($this->passwordStores as $passwordStore) {
            if (! $passwordStore->assess($employeeId, $password)) {
                return false;
            }
        }
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getDisplayName(): string
    {
        return $this->displayName;
    }
}
