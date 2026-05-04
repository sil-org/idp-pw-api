<?php

namespace frontend\controllers;

use common\models\User;
use frontend\components\BaseRestController;
use yii\filters\AccessControl;
use yii\helpers\ArrayHelper;

class UserController extends BaseRestController
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
                        'actions' => ['update'],
                        'matchCallback' => function () {
                            $user = \Yii::$app->user->identity;
                            return $user->isAuthScopeFull();
                        },
                    ],
                    [
                        'allow' => true,
                        'actions' => ['me'],
                        'roles' => ['@'],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @return null|\yii\web\IdentityInterface
     */
    public function actionMe()
    {
        return \Yii::$app->user->identity;
    }

    /**
     * @return null|\yii\web\IdentityInterface
     */
    public function actionUpdate()
    {
        /**
         * @var User $user
         */
        $user = \Yii::$app->user->identity;

        $hide = \Yii::$app->request->getBodyParam('hide');

        if ($hide !== null) {
            $user->hide = $hide;
            $user->save();
        }

        return $user;
    }
}
