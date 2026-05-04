<?php

namespace frontend\controllers;

use common\models\Password;
use common\models\User;
use frontend\components\BaseRestController;
use yii\filters\AccessControl;
use yii\helpers\ArrayHelper;
use yii\web\BadRequestHttpException;
use yii\web\ServerErrorHttpException;

class PasswordController extends BaseRestController
{
    /**
     * Access Control Filter
     * REMEMBER: NEEDS TO BE UPDATED FOR EVERY ACTION
     * @return array
     */
    public function behaviors()
    {
        return ArrayHelper::merge(parent::behaviors(), [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Return password metadata
     * @return array
     * @throws ServerErrorHttpException
     */
    public function actionView()
    {
        /** @var User $user */
        $user = \Yii::$app->user->identity;

        $pwMeta = $user->getPasswordMeta();

        if ($pwMeta === null) {
            throw new ServerErrorHttpException('A system error has occurred.', 1553606573);
        }

        return $pwMeta;
    }

    /**
     * Save new password
     * @return array<string,string>
     * @throws BadRequestHttpException
     * @throws ServerErrorHttpException
     */
    public function actionUpdate()
    {
        /** @var string $newPassword */
        $newPassword = $this->getPasswordFromRequestBody();

        /** @var User $user */
        $user = \Yii::$app->user->identity;
        $user->setPassword($newPassword);

        $pwMeta = $user->getPasswordMeta();

        if ($pwMeta === null) {
            throw new ServerErrorHttpException('A system error has occurred.', 1553606574);
        }

        return $pwMeta;
    }

    /**
     * Assess whether a password will pass validation checks without actually saving the password.
     * @throws BadRequestHttpException
     */
    public function actionAssess()
    {
        /** @var string $newPassword */
        $newPassword = $this->getPasswordFromRequestBody();

        /** @var User $user */
        $user = \Yii::$app->user->identity;

        $testPassword = Password::create($user, $newPassword);

        if (!$testPassword->validate('password')) {
            $errors = join(', ', $testPassword->getErrors('password'));
            \Yii::warning([
                'action' => 'password/assess',
                'status' => 'error',
                'email' => $user->email,
                'error' => $errors,
            ]);
            throw new BadRequestHttpException($errors, 1554151659);
        }

        \Yii::$app->response->statusCode = 204;
        return;
    }

    /**
     * @return string|null
     * @throws BadRequestHttpException
     */
    protected function getPasswordFromRequestBody()
    {
        $newPassword = trim(\Yii::$app->request->getBodyParam('password', ''));
        if ($newPassword === '') {
            throw new BadRequestHttpException(\Yii::t('app', 'Password.MissingPassword'));
        }
        return $newPassword;
    }
}
