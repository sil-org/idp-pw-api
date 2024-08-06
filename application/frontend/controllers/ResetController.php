<?php

namespace frontend\controllers;

use common\components\passwordStore\AccountLockedException;
use common\components\personnel\NotFoundException;
use common\helpers\Utils;
use common\models\EventLog;
use common\models\Reset;
use common\models\User;
use frontend\components\BaseRestController;
use yii\filters\AccessControl;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use yii\web\ServerErrorHttpException;

class ResetController extends BaseRestController
{
    /**
     * Access Control Filter
     * NEEDS TO BE UPDATED FOR EVERY ACTION
     */
    public function behaviors()
    {
        return ArrayHelper::merge(parent::behaviors(), [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['?'],
                    ],
                ]
            ],
            'authenticator' => [
                'only' => [''], // Bypass authentication for all actions
            ],
        ]);
    }

    /**
     * @param String $uid
     * @return Reset
     * @throws NotFoundHttpException
     */
    public function actionView($uid)
    {
        $reset = Reset::findOne(['uid' => $uid]);
        if ($reset === null) {
            throw new NotFoundHttpException();
        }

        return $reset;
    }

    /**
     * Create new reset process
     * @return Reset|\stdClass
     * @throws BadRequestHttpException
     * @throws NotFoundHttpException
     * @throws ServerErrorHttpException
     */
    public function actionCreate()
    {
        $username = \Yii::$app->request->post('username');
        $verificationToken = \Yii::$app->request->post('verification_token');

        if (! $username) {
            throw new BadRequestHttpException(\Yii::t('app', 'Reset.MissingUsername'));
        }

        /*
         * Validate reCaptcha $verificationToken before proceeding.
         * This will throw an exception if not successful, checking response to
         * be double sure an exception is thrown.
         */
        if (\Yii::$app->params['recaptcha']['required']) {
            if (! $verificationToken) {
                throw new BadRequestHttpException(\Yii::t('app', 'Reset.MissingRecaptchaCode'));
            }

            $clientIp = Utils::getClientIp(\Yii::$app->request);
            if (! Utils::isRecaptchaResponseValid($verificationToken, $clientIp)) {
                throw new BadRequestHttpException(\Yii::t('app', 'Reset.RecaptchaFailedVerification'));
            }
        }

        /*
         * Check if $username looks like an email address
         */
        $usernameIsEmail = false;
        if (substr_count($username, '@')) {
            $usernameIsEmail = true;
        }

        /*
         * Find or create user, if user not found return empty object
         */
        try {
            if ($usernameIsEmail) {
                $user = User::findOrCreate(null, $username);
            } else {
                $user = User::findOrCreate($username);
            }
        } catch (NotFoundException $e) {
            \Yii::warning([
                'action' => 'create reset',
                'username' => $username,
                'status' => 'error',
                'error' => 'user not found',
            ]);
            throw new NotFoundHttpException(
                \Yii::t('app', 'Reset.UserNotFound'),
                1543338164
            );
        } catch (\Exception $e) {
            \Yii::error([
                'action' => 'create reset',
                'username' => $username,
                'status' => 'error',
                'error' => $e->getMessage(),
            ]);
            throw new ServerErrorHttpException(
                \Yii::t('app', 'Reset.CreateFailure'),
                1469036552
            );
        }

        if ($user->isLocked()) {
            \Yii::warning([
                'action' => 'create reset',
                'username' => $username,
                'status' => 'error',
                'error' => 'personnel account is locked',
            ]);

            if ($user->hide === 'yes') {
                throw new NotFoundHttpException(
                    \Yii::t('app', 'Reset.UserNotFound'),
                    1560272556
                );
            }

            throw new NotFoundHttpException(
                \Yii::t('app', 'Reset.AccountLocked'),
                1560272557
            );
        }

        /*
         * Find or create a reset
         */
        $reset = Reset::findOrCreate($user);

        if ($reset->isExpired()) {
            $reset->restart();
        } else {
            $reset->send();
        }

        if ($user->hide === 'yes') {
            throw new NotFoundHttpException(
                \Yii::t('app', 'Reset.UserNotFound'),
                1543338164
            );
        }

        return $reset;
    }

    /**
     * Update reset type/method and send verification
     * @param string $uid
     * @return Reset
     * @throws BadRequestHttpException
     * @throws NotFoundHttpException
     * @throws \Exception
     * @throws \yii\web\ServerErrorHttpException
     * @throws \yii\web\TooManyRequestsHttpException
     */
    public function actionUpdate($uid)
    {
        /** @var Reset $reset */
        $reset = Reset::findOne(['uid' => $uid]);
        if ($reset === null) {
            throw new NotFoundHttpException(
                \Yii::t('app', 'Reset.NotFound'),
                1462989590
            );
        }

        $type = \Yii::$app->request->getBodyParam('type', null);
        $methodId = \Yii::$app->request->getBodyParam('id', null);

        if ($type === null) {
            throw new BadRequestHttpException(
                \Yii::t('app', 'Reset.MissingResetType'),
                1462989664
            );
        }

        /*
         * Update type
         */
        $reset->setType($type, $methodId);

        /*
         * Send verification
         */
        $reset->send();

        return $reset;
    }

    /**
     * @param string $uid
     * @return Reset
     * @throws NotFoundHttpException
     */
    public function actionResend($uid)
    {
        /** @var Reset $reset */
        $reset = Reset::findOne(['uid' => $uid]);
        if ($reset === null) {
            throw new NotFoundHttpException();
        }

        /*
         * Resend verification
         */
        $reset->send();

        return $reset;
    }

    /**
     * Validate reset code. Logs user in if successful
     * @param string $uid
     * @return void
     * @throws BadRequestHttpException
     * @throws NotFoundHttpException
     * @throws ServerErrorHttpException
     * @throws \Exception
     * @throws \Throwable
     */
    public function actionValidate($uid)
    {
        /** @var Reset $reset */
        $reset = Reset::findOne(['uid' => $uid]);
        if ($reset === null) {
            throw new NotFoundHttpException();
        }

        $log = [
            'action' => 'Validate reset',
            'reset_id' => $reset->id,
            'user' => $reset->user->email,
        ];

        if ($reset->isUserProvidedCodeCorrect($this->getCodeFromRequestBody())) {
            if ($reset->isExpired()) {
                $reset->restart();
                throw new HttpException(410);
            }

            $ipAddress = Utils::getClientIp(\Yii::$app->request);

            /*
             * Log event with reset type/method details
             */
            EventLog::log(
                'ResetVerificationSuccessful',
                [
                    'Reset Type' => $reset->type,
                    'Attempts' => $reset->attempts,
                    'IP Address' => $ipAddress,
                    'Method value' => $reset->getMaskedValue(),
                ],
                $reset->user_id
            );

            /*
             * Reset verified successfully, create access token for user
             */
            try {
                $accessToken = $reset->user->createAccessToken(User::AUTH_TYPE_RESET);
                \Yii::$app->response->cookies->add(new \yii\web\Cookie([
                  'name' => 'access_token',
                  'value' => $accessToken,
                  'expire' => \Yii::$app->user->access_token_expiration,
                  'httpOnly' => true, // Ensures the cookie is not accessible via JavaScript
                  'secure' => true,   // Ensures the cookie is sent only over HTTPS
                  'sameSite' => 'Lax', // Adjust as needed
                ]));

                $log['status'] = 'success';
                \Yii::warning($log);

                /*
                 * Delete reset record, log errors, but let user proceed
                 */
                if (! $reset->delete()) {
                    \Yii::warning([
                        'action' => 'delete reset after validation',
                        'reset_id' => $reset->id,
                        'status' => 'error',
                        'error' => Json::encode($reset->getFirstErrors()),
                    ]);
                }

            } catch (\Exception $e) {
                $log['status'] = 'error';
                $log['error'] = 'Unable to log user in after successful reset verification';
                \Yii::error($log);
                throw $e;
            }
        }

        EventLog::log(
            'ResetVerificationFailed',
            [
                'reset_id' => $reset->id,
                'type' => $reset->type,
                'attempts' => $reset->attempts,
            ],
            $reset->user_id
        );

        $log['status'] = 'error';
        $log['error'] = 'Reset code verification failed';
        \Yii::warning($log);
        throw new BadRequestHttpException(
            \Yii::t('app', 'Reset.InvalidCode'),
            1462991098
        );
    }

    /**
     * @return string
     * @throws BadRequestHttpException
     */
    protected function getCodeFromRequestBody(): string
    {
        $code = \Yii::$app->request->getBodyParam('code', null);
        if ($code === null) {
            throw new BadRequestHttpException(\Yii::t('app', 'Reset.MissingCode'), 1462989866);
        }
        return $code;
    }
}
