<?php

namespace common\models;

use common\components\passwordStore\PasswordStoreException;
use common\helpers\ZxcvbnPasswordValidator;
use common\components\passwordStore\PasswordReuseException;
use GuzzleHttp\Exception\GuzzleException;
use Icawebdesign\Hibp\Password\PwnedPassword;
use Icawebdesign\Hibp\HibpHttp;
use Sil\Idp\IdBroker\Client\ServiceException;
use yii\base\Model;
use yii\web\BadRequestHttpException;
use yii\web\ConflictHttpException;
use yii\web\ServerErrorHttpException;

class Password extends Model
{
    /** @var string */
    public $password;

    /** @var \common\components\passwordStore\PasswordStoreInterface */
    public $passwordStore;

    /** @var array */
    public $config;

    /** @var User **/
    public $user;

    public function init()
    {
        $this->passwordStore = \Yii::$app->passwordStore;
        $this->config = \Yii::$app->params['passwordRules'];
    }

    public function rules()
    {
        return [
            [
                'password', 'string', 'min' => $this->config['minLength'],
                'skipOnError' => false,
                'tooShort' => \Yii::t(
                    'app',
                    'Password.TooShort',
                    ['minLength' => $this->config['minLength']]
                ),
            ],
            [
                'password', 'string', 'max' => $this->config['maxLength'],
                'skipOnError' => false,
                'tooLong' => \Yii::t(
                    'app',
                    'Password.TooLong',
                    ['maxLength' => $this->config['maxLength']]
                ),
            ],
            [
                'password', ZxcvbnPasswordValidator::class, 'minScore' => $this->config['minScore'],
                'skipOnError' => true,
                'message' => \Yii::t(
                    'app',
                    'Password.TooWeak',
                    ['minScore' => $this->config['minScore']]
                ),
            ],
            [
                'password', 'validateNotUserAttributes',
                'params' => ['first_name', 'last_name', 'idp_username', 'email'],
                'skipOnError' => false,
            ],
            [
                'password', 'validateNotBeenPwned',
                'skipOnError' => true,
                'when' => function () {
                    return $this->config['enableHIBP'];
                },
            ],
            [
                'password', 'validateNotPublicPassword',
                'skipOnError' => false,
            ],
            [
                'password', 'passwordStoreInterfaceAssess',
                'skipOnError' => true,
            ],
            [
                'password', 'validateNoBadBytes',
                'skipOnError' => false,
            ],
            [
                'password', 'validateAlphaAndNumeric',
                'skipOnError' => false,
                'when' => function () {
                    return $this->config['requireAlphaAndNumeric'];
                },
            ],
        ];
    }

    public function validateNotUserAttributes($attribute, $params = null)
    {
        /* Ensure the password instance has a user attribute */
        if (! isset($this->user)) {

            /* Log error */
            $log = [
                'status' => 'error',
                'error' => 'No User instance has been assigned to the new password. '
                    . "Cannot validate it against the user's attributes.",
            ];
            \Yii::error($log);

            /* Throw exception based on exception type */
            throw new ServerErrorHttpException(
                \Yii::t(
                    'app',
                    'Password.UpdateError'
                ),
                1511195430
            );
        }
        $userAttributeLabels = $this->user->attributeLabels();
        $labelList = [];
        foreach ($params as $disallowedAttribute) {
            $labelList[] = $userAttributeLabels[$disallowedAttribute];
        }

        foreach ($params as $disallowedAttribute) {
            // Don't apply this check to attributes with a very short value
            if (strlen($this->user->$disallowedAttribute) < 3) {
                continue;
            }
            if (mb_strpos(
                mb_strtolower($this->{$attribute}),
                mb_strtolower($this->user->$disallowedAttribute)
            ) !== false) {
                $this->addError($attribute, \Yii::t(
                    'app',
                    'Password.DisallowedContent',
                    ['labelList' => join(', ', $labelList)]
                ));
            }
        }
    }

    /**
     * Shortcut method to initialize a Password object
     * @param User $user
     * @param string $newPassword
     * @return Password
     */
    public static function create($user, $newPassword)
    {
        $password = new Password();
        $password->password = $newPassword;
        $password->user = $user;

        return $password;
    }

    /**
     * @throws BadRequestHttpException
     * @throws ServerErrorHttpException
     */
    public function save()
    {
        if (! $this->validate()) {
            $errors = join(', ', $this->getErrors('password'));
            \Yii::warning([
                'action' => 'save password',
                'status' => 'error',
                'employee_id' => $this->user->employee_id,
                'error' => $this->getErrors('password'),
            ]);
            throw new BadRequestHttpException($errors);
        }

        $log = [
            'action' => 'save password',
            'employee_id' => $this->user->employee_id,
        ];

        /*
         * Update password
         */
        try {
            $this->passwordStore->set($this->user->employee_id, $this->password);
            $log['status'] = 'success';
            \Yii::warning($log);
        } catch (\Exception $e) {
            /*
             * Log error
             */
            $log['status'] = 'error';
            $log['error'] = $e->getMessage();
            $previous = $e->getPrevious();
            if ($previous instanceof \Exception) {
                $log['previous'] = [
                    'code' => $previous->getCode(),
                    'message' => $previous->getMessage(),
                ];
            }

            /*
             * Throw exception based on exception type
             */
            if ($e instanceof PasswordReuseException) {
                \Yii::warning($log);
                throw new ConflictHttpException(\Yii::t('app', 'Password.PasswordReuse'), 1469194882);
            } elseif ($e instanceof PasswordStoreException) {
                \Yii::warning($log);
                throw new BadRequestHttpException($e->getMessage());
            } else {
                \Yii::error($log);
                throw new ServerErrorHttpException(
                    \Yii::t('app', 'Password.UpdateFailure'),
                    1463165209
                );
            }

        }
    }

    public function validateNotPublicPassword($attribute)
    {
        /*
         * block passwords provided in https://youtu.be/WTMZYuoztoM?list=PLu5OsENIeX656zXJ96FCL169WNmvnPveo
         */
        $publicPasswords = [
            'one4amzn',
            'one4ggle',
            'one4ebay',
            '$$Ymh7Hp3dfgQr9L#!>s;',
            '7startpenguins!snap',
            'deserty.domes.slide2',
            'about-slithers-quakely.',
        ];

        foreach ($publicPasswords as $publicPassword) {
            if ($this->$attribute == $publicPassword) {
                $this->addError($attribute, \Yii::t('app', 'Password.PublicPasswordUsed'));
            }
        }
    }

    /**
     * @param string $attribute The name of the attribute being validated, typically
     * 'password'.
     */
    public function validateNotBeenPwned($attribute)
    {
        $hash = sha1($this->$attribute);

        $pwnedPassword = new PwnedPassword(new HibpHttp());

        try {
            $count = $pwnedPassword->rangeFromHash($hash);
        } catch (GuzzleException $e) {
            \Yii::error('HaveIBeenPwned API error: ' . $e->getMessage());
            return;
        }

        if ($count > 0) {
            throw new BadRequestHttpException(\Yii::t('app', 'Password.Breached'), 1554734183);
        }
    }

    /**
     * Request an assesment of the password from the PasswordStore Interface, to check against
     * previously-used passwords, for instance.
     *
     * Called by Yii validation upon record save or explicit call to validate()
     *
     * @param string $attribute The name of the attribute being validated, typically
     * 'password'.
     * @throws ConflictHttpException
     */
    public function passwordStoreInterfaceAssess($attribute)
    {
        try {
            $this->passwordStore->assess($this->user->employee_id, $this->$attribute);
        } catch (ServiceException $e) {
            if ($e->httpStatusCode === 409) {
                throw new ConflictHttpException(\Yii::t('app', 'Password.PasswordReuse'));
            } else {
                throw new \Exception('Password.UnknownProblem');
            }
        }
    }

    public function validateNoBadBytes($attribute)
    {
        if (str_contains($this->$attribute, "\0")) {
            $this->addError($attribute, \Yii::t('app', 'Password.ContainsBadByte'));
        }
    }

    public function validateAlphaAndNumeric(string $attribute): void
    {
        $letter = preg_match('/\pL/', $this->$attribute);
        $number = preg_match('/\pN/', $this->$attribute);

        if ($letter === false || $number === false) {
            throw new \Exception('Password.UnknownProblem');
        }

        if ($letter === 0 || $number === 0) {
            $this->addError($attribute, \Yii::t('app', 'Password.AlphaAndNumericRequired'));
        }
    }
}
