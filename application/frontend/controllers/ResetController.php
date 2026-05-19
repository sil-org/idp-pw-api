<?php

namespace frontend\controllers;

use common\components\personnel\NotFoundException;
use common\helpers\Utils;
use common\models\User;
use Exception;
use frontend\components\BaseRestController;
use Sil\Idp\IdBroker\Client\IdBrokerClient;
use Sil\Idp\IdBroker\Client\ServiceException;
use Yii;
use yii\filters\AccessControl;
use yii\helpers\ArrayHelper;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;

class ResetController extends BaseRestController
{
    public IdBrokerClient $idBrokerClient;

    /**
     * @throws Exception
     */
    public function init(): void
    {
        parent::init();
        $config = Yii::$app->params['idBrokerConfig'];
        if (empty($config['baseUrl']) || empty($config['accessToken'])) {
            throw new Exception('ID_BROKER_baseURL and ID_BROKER_accessToken are required', 1778752282);
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
                        'actions' => ['create', 'validate'],
                        'roles' => ['@', '?'],
                    ],
                ],
            ],
            'authenticator' => [
                'optional' => ['create', 'validate'], // bypass authentication for specified routes
            ],
        ]);
    }

    /**
     * Create new password reset. Calls the ID Broker to request a new Reset record and send one or more email
     * messages containing a link to click, which will direct the user to the Validate action in this controller.
     * @return void
     * @throws BadRequestHttpException
     * @throws ServiceException
     */
    public function actionCreate(): void
    {
        $username = trim(Yii::$app->request->getBodyParam('username', ''));
        $verificationToken = trim(Yii::$app->request->getBodyParam('verification_token', ''));

        if ($username === '') {
            throw new BadRequestHttpException(Yii::t('app', 'Reset.MissingUsername'));
        }

        /*
         * Validate reCaptcha $verificationToken before proceeding.
         * This will throw an exception if not successful, checking response to
         * be double sure an exception is thrown.
         */
        if (Yii::$app->params['recaptcha']['required']) {
            if ($verificationToken === '') {
                throw new BadRequestHttpException(Yii::t('app', 'Reset.MissingRecaptchaCode'));
            }

            $clientIp = Utils::getClientIp(Yii::$app->request);
            if (!Utils::isRecaptchaResponseValid($verificationToken, $clientIp)) {
                throw new BadRequestHttpException(Yii::t('app', 'Reset.RecaptchaFailedVerification'));
            }
        }

        $this->idBrokerClient->createReset($username);

        Yii::$app->response->statusCode = 204;
    }

    /**
     * Validate reset code. If successful, a limited-access cookie will be set.
     * @param string $uuid
     * @return void
     * @throws HttpException
     * @throws NotFoundException
     * @throws Exception
     */
    public function actionValidate(string $uuid): void
    {
        try {
            $brokerResponse = $this->idBrokerClient->verifyReset($uuid);
        } catch (ServiceException $e) {
            throw new HttpException($e->httpStatusCode);
        }

        $user = User::findOrCreate(null, null, $brokerResponse['employee_id']);

        $user->createAccessToken(User::AUTH_TYPE_RESET);

        Yii::info([
            'action' => 'Validate reset',
            'status' => 'success',
            'employee_id' => $user->employee_id,
            'ip_address' => Utils::getClientIp(Yii::$app->request),
        ]);
    }
}
