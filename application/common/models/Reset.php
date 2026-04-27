<?php

namespace common\models;

use common\helpers\Utils;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\web\BadRequestHttpException;
use yii\web\ServerErrorHttpException;
use yii\web\TooManyRequestsHttpException;

/**
 * Class Reset
 * @package common\models
 * @method Reset self::findOne([])
 */
class Reset extends ResetBase
{
    public const TYPE_PRIMARY = 'primary'; // Used for primary email address
    public const TYPE_METHOD = 'method';
    public const TYPE_SUPERVISOR = 'supervisor';

    public const TOPIC_RESET_EMAIL_SENT = 'Reset Email Sent';
    public const TOPIC_RESET_PHONE_SENT = 'Reset Phone Sent';

    /**
     * @return array
     */
    public function rules()
    {
        return ArrayHelper::merge(
            [
                [
                    ['uid'], 'default', 'value' => Utils::generateRandomString(),
                ],

                [
                    ['created'], 'default', 'value' => Utils::getDatetime(),
                ],

                [
                    ['expires'], 'default', 'value' => self::calculateExpireTime(),
                ],

                [
                    ['type'], 'default', 'value' => self::TYPE_PRIMARY,
                ],

                [
                    ['attempts'], 'default', 'value' => 0,
                ],

                [
                    ['type'], 'in', 'range' => [
                        self::TYPE_PRIMARY, self::TYPE_METHOD, self::TYPE_SUPERVISOR
                    ],
                    'message' => 'Reset type must be either ' . self::TYPE_PRIMARY . ' or ' . self::TYPE_METHOD .
                        ' or ' . self::TYPE_SUPERVISOR . ' .',
                ],

                [
                    ['email'], 'email'
                ],
            ],
            parent::rules()
        );
    }

    /**
     * @return array
     */
    public function fields()
    {
        return [
            'uid',
            'methods' => function ($model) {
                return $model->user->getMaskedMethods();
            },
        ];
    }

    /**
     * @param User $user
     * @return Reset
     * @throws \Exception
     */
    public static function findOrCreate($user)
    {
        /*
         * Clean up resets expired past the grace period
         */
        self::deleteResetsPastGracePeriod();

        $log = [
            'action' => 'findOrCreate reset',
            'user_id' => $user->id,
            'user' => $user->email,
            'type' => self::TYPE_PRIMARY,
            'ip_address' => 'initiated outside web request context',
        ];

        if (\Yii::$app->request) {
            $log['ip_address'] = Utils::getClientIp(\Yii::$app->request);
        }

        /*
         * Find existing or create new Reset
         */
        $reset = $user->reset;
        if ($reset === null) {
            $log['existing reset'] = 'no';
            /*
             * Create new reset
             */
            $reset = new Reset();
            $reset->user_id = $user->id;
            /*
             * Only set type/method if creating so that on subsequent restarts of process
             * it does not reset method to primary
             */
            $reset->type = self::TYPE_PRIMARY;

            /*
             * Save new Reset
             */
            $reset->saveOrError('create new reset', \Yii::t('app', 'Reset.CreateFailure'));

            $log['reset_id'] = $reset->id;
            $log['type'] = $reset->type;
        } else {
            $log['existing reset'] = 'yes';
            /*
             * change method back to primary if they are requesting to start reset again
             */
            $reset->setType(self::TYPE_PRIMARY);
        }

        $log['status'] = 'success';
        $log['attempts_count'] = $reset->attempts;
        $log['expires'] = $reset->expires;
        $log['disable_until'] = $reset->disable_until;
        \Yii::warning($log);

        return $reset;
    }

    /**
     * Make sure reset is not disabled, track attempt, and then send.
     * Send reset notification to appropriate method
     * @throws \Exception
     */
    public function send()
    {
        /*
         * Track attempt and throw error if disabled or limit is reached
         */
        $this->trackAttempt('send');

        if ($this->user->hide === 'yes') {
            try {
                $this->sendAll();
            } catch (\Throwable $t) {
                $log = [
                    'action' => 'reset send all',
                    'status' => 'error',
                    'error' => 'Exception during password reset for account with hide flag. ' . $t->getMessage()
                ];
                \Yii::error($log, __METHOD__);
            }
            return;
        }

        /*
         * Based on type/method send reset verification and update
         * model with reset code
         */
        switch ($this->type) {
            case self::TYPE_PRIMARY:
                $this->sendPrimary();
                break;
            case self::TYPE_SUPERVISOR:
                $this->sendSupervisor();
                break;
            case self::TYPE_METHOD:
                $this->sendMethod();
                break;
            default:
                throw new \Exception('Reset is configured with unknown type.', 1456784825);
        }
    }

    private function sendPrimary()
    {
        $subject = \Yii::t(
            'app',
            'Reset.PasswordResetSubject',
            [
                'idpDisplayName' => \Yii::$app->params['idpDisplayName'],
            ]
        );

        $this->sendEmail($this->user->email, $subject, 'self');
    }

    private function sendSupervisor()
    {
        if ($this->user->hasSupervisor()) {
            $supervisor = $this->user->getSupervisorEmail();
            $this->sendOnBehalf($supervisor);
        } else {
            throw new \Exception('User does not have supervisor on record', 1461173406);
        }
    }

    private function sendOnBehalf($toAddress)
    {
        $subject = \Yii::t(
            'app',
            'Reset.PasswordResetForSubject',
            [
                'idpDisplayName' => \Yii::$app->params['idpDisplayName'],
                'name' => $this->user->first_name,
            ]
        );

        $this->sendEmail($toAddress, $subject, 'on-behalf', $this->user->email);
    }

    /**
     * Send reset message
     * @param string|null $address Email address to receive the reset.
     * @throws \Exception
     */
    private function sendMethod(string $address = null)
    {
        $toAddress = $address ?? $this->email;

        if ($toAddress === null) {
            throw new \Exception('No email defined for reset', 1456608512);
        }

        $subject = \Yii::t(
            'app',
            'Reset.PasswordResetSubject',
            [
                'idpDisplayName' => \Yii::$app->params['idpDisplayName'],
            ]
        );
        $this->sendEmail($toAddress, $subject, 'on-behalf', $this->user->email);
    }

    /**
     * Send reset messages to primary email and all verified recovery methods.
     */
    private function sendAll()
    {
        $this->sendPrimary();

        $methods = Method::getVerifiedMethods($this->user->employee_id);

        foreach ($methods as $method) {
            $this->sendMethod($method['value']);
        }
    }

    /**
     * @param string $toAddress
     * @param string $subject
     * @param string $view
     * @param string|null $ccAddress
     * @throws \Exception
     */
    private function sendEmail($toAddress, $subject, $view, $ccAddress = null)
    {
        /*
         * Generate code if needed, update attempt counter, save record, and send email
         */
        if ($this->code === null) {
            $this->code = self::createCode();
            $this->saveOrError('send email', \Yii::t('app', 'Reset.UpdateFailure'));
        }

        $resetUrl = sprintf('%s/password/reset/%s/verify/%s', \Yii::$app->params['uiUrl'], $this->uid, $this->code);

        // Send email verification
        $friendlyExpiration = Utils::getFriendlyDate($this->expires);
        Verification::sendEmail(
            $toAddress,
            $subject,
            '@common/mail/reset/' . $view,
            $this->code,
            $friendlyExpiration,
            $this->user,
            $ccAddress,
            $this->user->id,
            self::TOPIC_RESET_EMAIL_SENT,
            'Password reset email for ' . $this->user->getDisplayName() .
            ' sent to ' . $toAddress,
            [
                'resetUrl' => $resetUrl,
                'displayName' => $this->user->getDisplayName(),
            ]
        );

        \Yii::warning([
            'action' => 'reset send email',
            'user' => $this->user->email,
            'to_address' => $toAddress,
            'subject' => $subject,
        ]);
    }

    /**
     * Check if user provided code is valid
     * @param string $userProvided code submitted by user
     * @return bool
     * @throws \Exception
     * @throws HttpException
     * @throws ServerErrorHttpException
     * @throws TooManyRequestsHttpException
     */
    public function isUserProvidedCodeCorrect($userProvided): bool
    {
        /*
         * Track attempt and throw error if disabled or limit is reached
         */
        $this->trackAttempt('verify');

        if ($this->isTypeEmail()) {
            return Verification::isEmailCodeValid($this->code, $userProvided);
        } else {
            throw new \Exception('Unable to verify code because method type is invalid', 1462543005);
        }
    }

    /**
     * @throws ServerErrorHttpException
     * @throws \Exception
     */
    public function restart()
    {
        $this->attempts = 0;
        $this->code = self::createCode();
        $this->expires = self::calculateExpireTime();
        $this->saveOrError('restart reset');
        $this->send();
    }

    /**
     * Check if reset is using an email type of verification
     * @return bool
     */
    public function isTypeEmail()
    {
        return ($this->type === self::TYPE_PRIMARY
            || $this->type === self::TYPE_SUPERVISOR
            || $this->type === self::TYPE_METHOD);
    }

    /**
     * Calculate expiration time based on current time and configured reset lifetime
     * @return string
     * @throws ServerErrorHttpException
     */
    public static function calculateExpireTime(): string
    {
        $params = \Yii::$app->params;
        if (! isset($params['reset']) || ! isset($params['reset']['lifetimeSeconds']) ||
            ! is_integer($params['reset']['lifetimeSeconds'])) {
            throw new ServerErrorHttpException('Application configuration for reset lifetime is not set', 1458676224);
        }

        return Utils::getDatetime(time() + $params['reset']['lifetimeSeconds']);
    }

    /**
     * @return bool
     * @throws ServerErrorHttpException
     */
    public function isExpired(): bool
    {
        $expiresTimestamp = strtotime($this->expires);
        if ($expiresTimestamp === false) {
            throw new ServerErrorHttpException('Unable to check expiration', 1545341112);
        }

        return $expiresTimestamp < time();
    }

    /**
     * Check if this reset is currently disabled
     * @return bool
     */
    public function isDisabled()
    {
        if ($this->disable_until !== null) {
            $disableUntilTime = strtotime($this->disable_until);
            // Intentionally loose comparison to catch zero
            if ($disableUntilTime == false) {
                return true;
            }
            return $disableUntilTime > time();
        }

        return false;
    }

    /**
     * Mark reset as disabled by setting disable_until date
     * @throws ServerErrorHttpException
     */
    public function disable()
    {
        $log = [
            'action' => 'disable reset',
            'reset_id' => $this->id,
            'attempts' => $this->attempts,
            'user' => $this->user->email,
            'type' => $this->type,
            'disable_until' => $this->disable_until,
            'status' => 'success',
        ];
        $this->disable_until = Utils::getDatetime(time() + \Yii::$app->params['reset']['disableDuration']);
        $this->saveOrError($log['action'], \Yii::t('app', 'Reset.SetDisableTimeError'));

        \Yii::warning($log);
    }

    /**
     * Re-enable reset
     * @throws ServerErrorHttpException
     * @throws \Exception
     */
    public function enable()
    {
        $this->disable_until = null;
        $this->attempts = 0;
        $this->saveOrError('enable reset', \Yii::t('app', 'Reset.CannotEnable'));

        \Yii::warning([
            'action' => 'enable reset',
            'reset_id' => $this->id,
            'status' => 'success',
            'type' => $this->type,
            'user' => $this->user->email,
        ]);
    }

    /**
     * Enable reset if disable until time has past, or check attempts count and disable if it should be
     * @throws ServerErrorHttpException
     */
    public function enableOrDisableIfNeeded()
    {
        if ($this->disable_until !== null) {
            $disableUntilTime = strtotime($this->disable_until);
            if ($disableUntilTime == false) {
                throw new ServerErrorHttpException('Unable to check disable timeout', 1463146757);
            }

            /*
             * Disable until is in the past, so enable reset
             */
            if ($disableUntilTime < time()) {
                $this->enable();
            }
        } else {
            /*
             * If attempts has reached limit, disable reset
             */
            if ($this->attempts >= \Yii::$app->params['reset']['maxAttempts']) {
                $this->disable();
            }
        }
    }

    public function setType($type, $methodUid = null)
    {
        $previousType = $this->type;
        /*
         * If type is not method, update or throw error
         */
        if (in_array($type, [self::TYPE_SUPERVISOR, self::TYPE_PRIMARY])) {
            $this->type = $type;
            $this->email = null;
        } elseif ($type == self::TYPE_METHOD || $type == Method::TYPE_EMAIL) {
            /*
             * Method::TYPE_EMAIL is included because that type is identified by the UI
             */

            /*
             * If type is method but methodId is missing, throw error
             */
            if ($methodUid === null) {
                throw new BadRequestHttpException(
                    \Yii::t('app', 'Reset.MissingMethodUID'),
                    1462988984
                );
            }

            /*
             * Make sure user owns requested method and it is verified before update
             */
            $method = Method::getOneVerifiedMethod($methodUid, $this->user->employee_id);
            $this->type = self::TYPE_METHOD;
            $this->email = $method['value'];
        } else {
            throw new BadRequestHttpException(
                \Yii::t('app', 'Reset.UnknownType'),
                1462989489
            );
        }

        // NOTE: We stopped changing the code here so that, if someone requests
        // a subsequent reset while an existing one is not yet expired, the same
        // code will be used. That way, clicking the link in the first email
        // will still work.

        /*
         * Save changes
         */
        $this->saveOrError('Set type of reset', \Yii::t('app', 'Reset.UpdateTypeError'));

        \Yii::info([
            'action' => 'change reset',
            'attempts' => $this->attempts,
            'previous_type' => $previousType,
            'reset_id' => $this->id,
            'status' => 'success',
            'type' => $this->type,
            'user' => $this->user->email,
        ]);
    }

    /**
     * Increments attempts counter and disables account when limit is reached
     * @param string $action Used in logging, either 'send' or 'verify'
     * @throws ServerErrorHttpException
     * @throws TooManyRequestsHttpException
     */
    public function trackAttempt($action)
    {
        /*
         * Increment attempts count first thing
         */
        $this->attempts++;
        $this->saveOrError($action . ' reset', \Yii::t('app', 'Reset.IncrementAttemptsError'));

        /*
         * Enable / disable reset as needed
         */
        $this->enableOrDisableIfNeeded();

        /*
         * Check if reset is disabled and throw exception if it is
         */
        if ($this->isDisabled()) {
            \Yii::warning([
                'action' => $action . ' reset',
                'reset_id' => $this->id,
                'attempts' => $this->attempts,
                'status' => 'error',
                'error' => 'Reset is currently disabled until ' . $this->disable_until,
                'user' => $this->user->email,
            ]);
            throw new TooManyRequestsHttpException();
        }
    }

    /**
     * Save model or throw exception on error
     * @param string $action
     * @param string $errorPrefix This can be displayed to end user, so do not put anything sensitive in it
     * @throws ServerErrorHttpException
     */
    public function saveOrError($action, $errorPrefix = '')
    {
        if (! $this->save()) {
            \Yii::error([
                'action' => $action,
                'reset_id' => $this->id,
                'attempts' => $this->attempts,
                'type' => $this->type,
                'status' => 'error',
                'error' => $errorPrefix . ' Error: ' . Json::encode($this->getFirstErrors()),
                'user' => $this->user->email,
            ]);
            throw new ServerErrorHttpException($errorPrefix);
        }
    }

    /**
     * Return the masked value of whatever is used for this reset
     * @return string
     */
    public function getMaskedValue()
    {
        if ($this->type === self::TYPE_METHOD) {
            return Utils::maskEmail($this->email);
        } elseif ($this->type === self::TYPE_PRIMARY) {
            return Utils::maskEmail($this->user->email);
        } elseif ($this->type == self::TYPE_SUPERVISOR) {
            return Utils::maskEmail($this->user->getSupervisorEmail());
        } else {
            return 'Invalid reset type';
        }
    }

    /**
     * @return string
     * @throws \Exception
     */
    private static function createCode(): string
    {
        return Utils::getRandomDigits(\Yii::$app->params['reset']['codeLength']);
    }

    /**
     * Delete all Reset records with expiration date more than `gracePeriod` in the past
     */
    public static function deleteResetsPastGracePeriod()
    {
        /*
         * Replace '+' with '-' just to be sure it's correctly defined
         */
        $resetGracePeriod = str_replace('+', '-', \Yii::$app->params['reset']['gracePeriod']);

        /**
         * @var string $removeExpireBefore   All records that expired before this date
         * should be deleted. Calculated relative to now (time of execution).
         */
        $removeExpireBefore = Utils::getDatetime(strtotime($resetGracePeriod));
        $resets = self::find()->andWhere(['<', 'expires', $removeExpireBefore])->all();

        $numDeleted = 0;
        foreach ($resets as $reset) {
            try {
                if ($reset->delete() !== false) {
                    $numDeleted += 1;
                }
            } catch (\Exception $e) {
                \Yii::error([
                    'action' => 'delete old resets',
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                    'uuid' => $reset->uid,
                ]);
            }
        }

        if ($numDeleted > 0) {
            \Yii::warning([
                'action' => 'delete old resets',
                'status' => 'success',
                'deleted count' => $numDeleted,
            ]);
        }
    }
}
