<?php

namespace frontend\controllers;

use common\models\Method;
use common\models\User;
use frontend\components\BaseRestController;
use Sil\Idp\IdBroker\Client\IdBrokerClient;
use Sil\Idp\IdBroker\Client\ServiceException;
use yii\filters\AccessControl;
use yii\helpers\ArrayHelper;
use yii\web\BadRequestHttpException;
use yii\web\ConflictHttpException;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;

class MethodController extends BaseRestController
{
    /**
     * @var IdBrokerClient
     */
    public $idBrokerClient;

    public function init()
    {
        parent::init();
        $config = \Yii::$app->params['idBrokerConfig'];
        if (empty($config['baseUrl']) || empty($config['accessToken'])) {
            throw new \Exception('ID_BROKER_baseURL and ID_BROKER_accessToken are required', 1722423242);
        }
        $this->idBrokerClient = new IdBrokerClient(
            $config['baseUrl'],
            $config['accessToken'],
            [
                IdBrokerClient::TRUSTED_IPS_CONFIG => $config['validIpRanges'] ?? [],
                IdBrokerClient::ASSERT_VALID_BROKER_IP_CONFIG => $config['assertValidBrokerIp'] ?? true,
            ]
        );
    }

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
                        'actions' => ['verify', 'move'],
                        'roles' => ['?'],
                    ],
                    [
                        'allow' => true,
                        'matchCallback' => function () {
                            $user = \Yii::$app->user->identity;
                            return ($user->isAuthScopeFull());
                        },
                    ],
                ],
            ],
            'authenticator' => [
                'except' => ['verify', 'move'], // bypass authentication for /method/{id}/verify and /method/move
            ],
        ]);
    }

    /**
     * Return list of available reset methods for user.
     * @return array<array>
     */
    public function actionIndex()
    {
        /** @var User $user */
        $user = \Yii::$app->user->identity;

        return $user->getMethodsAndPersonnelEmails();
    }

    /**
     * View single method
     * @param string $uid
     * @return array<string,string>
     * @throws NotFoundHttpException
     * @throws \Exception
     */
    public function actionView($uid)
    {
        $employeeId = \Yii::$app->user->identity->employee_id;

        $method = Method::getOneVerifiedMethod($uid, $employeeId);

        $method['type'] = 'email';
        return $method;
    }

    /**
     * Create new unverified method and send verification
     * @return array<string,string>
     * @throws BadRequestHttpException
     * @throws \Exception
     */
    public function actionCreate()
    {
        $messages = [
            400 => \Yii::t('app', 'Method.MissingValue'),
            409 => \Yii::t('app', 'Method.AlreadyExists'),
            422 => \Yii::t('app', 'Method.InvalidEmail'),
        ];

        $request = \Yii::$app->request;

        $value = trim($request->getBodyParam('value', ''));
        if ($value === '') {
            throw new BadRequestHttpException(\Yii::t('app', 'Method.MissingValue'), 1542750428);
        }

        $employeeId = \Yii::$app->user->identity->employee_id;

        if (\Yii::$app->user->identity->email == $value) {
            throw new ConflictHttpException(
                \Yii::t('app', 'Method.EmailReuseError'),
                1550138424
            );
        }
        try {
            $method = $this->idBrokerClient->createMethod($employeeId, $value);
        } catch (ServiceException $e) {
            throw new HttpException(
                $e->httpStatusCode,
                $messages[$e->httpStatusCode] ?? '',
                1542750430
            );
        }
        $method['type'] = 'email';
        return $method;
    }

    /**
     * Validates user submitted code and marks method as verified if valid
     * @param string $uid
     * @return array<string,string>
     * @throws BadRequestHttpException
     * @throws HttpException
     * @throws \Exception
     */
    public function actionVerify($uid)
    {
        $messages = [
            400 => \Yii::t('app', 'Method.InvalidCode'),
            404 => \Yii::t('app', 'Method.NotFound'),
            410 => \Yii::t('app', 'Method.CodeExpired'),
            429 => \Yii::t('app', 'Method.TooManyFailures'),
        ];

        $code = trim(\Yii::$app->request->getBodyParam('code', ''));
        if ($code === '') {
            throw new BadRequestHttpException(\Yii::t('app', 'Method.CodeMissing'), 1542749426);
        }

        try {
            $method = $this->idBrokerClient->verifyMethod($uid, '', $code);
        } catch (ServiceException $e) {
            throw new HttpException(
                $e->httpStatusCode,
                $messages[$e->httpStatusCode] ?? '',
                1542749427
            );
        }

        $method['type'] = 'email';
        return $method;
    }

    /**
     * Delete method
     * @param string $uid
     * @return \stdClass
     * @throws NotFoundHttpException
     * @throws \Exception
     */
    public function actionDelete($uid)
    {
        $employeeId = \Yii::$app->user->identity->employee_id;

        try {
            $this->idBrokerClient->deleteMethod($uid, $employeeId);
        } catch (ServiceException $e) {
            if ($e->httpStatusCode === 404) {
                throw new NotFoundHttpException(\Yii::t('app', 'Method.NotFound'), 1542749425);
            } else {
                throw $e;
            }
        }

        \Yii::$app->response->statusCode = 204;

        /*
         * Return empty object
         */
        return new \stdClass();
    }

    /**
     * @param string $uid
     * @return \stdClass
     * @throws NotFoundHttpException
     * @throws \Exception
     */
    public function actionResend($uid)
    {
        $employeeId = \Yii::$app->user->identity->employee_id;

        try {
            $this->idBrokerClient->resendMethod($uid, $employeeId);
        } catch (ServiceException $e) {
            if ($e->httpStatusCode === 404) {
                throw new NotFoundHttpException(\Yii::t('app', 'Method.NotFound'), 1542749424);
            } else {
                throw $e;
            }
        }

        \Yii::$app->response->statusCode = 204;

        /*
         * Return empty object
         */
        return new \stdClass();
    }
}
