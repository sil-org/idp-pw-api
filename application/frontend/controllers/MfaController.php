<?php

namespace frontend\controllers;

use frontend\components\BaseRestController;
use Sil\Idp\IdBroker\Client\IdBrokerClient;
use Sil\Idp\IdBroker\Client\ServiceException;
use stdClass;
use yii\filters\AccessControl;
use yii\helpers\ArrayHelper;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;

class MfaController extends BaseRestController
{
    /**
     * @var IdBrokerClient
     */
    public $idBrokerClient;

    /**
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
                        'matchCallback' => function () {
                            $user = \Yii::$app->user->identity;
                            return $user->isAuthScopeFull();
                        },
                    ],
                ],
            ],
        ]);
    }

    /**
     * @throws \Exception
     */
    public function init()
    {
        parent::init();
        $config = \Yii::$app->params['idBrokerConfig'];
        if (empty($config['baseUrl']) || empty($config['accessToken'])) {
            throw new \Exception('ID_BROKER_baseURL and ID_BROKER_accessToken are required', 1722423243);
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
     * @return array
     * @throws ServiceException
     */
    public function actionIndex(): array
    {
        return $this->idBrokerClient->mfaList(
            \Yii::$app->user->identity->employee_id,
            \Yii::$app->params['rpOrigin']
        );
    }

    /**
     * @return array|null
     * @throws BadRequestHttpException
     * @throws HttpException
     */
    public function actionCreate(): ?array
    {
        $messages = [
            409 => \Yii::t('app', 'Mfa.AlreadyExists'),
        ];

        $type = \Yii::$app->request->getBodyParam('type');
        if ($type === null) {
            throw new BadRequestHttpException(\Yii::t('app', 'Mfa.TypeMissing'));
        }

        $label = trim(\Yii::$app->request->getBodyParam('label', ''));

        try {
            $mfa = $this->idBrokerClient->mfaCreate(
                \Yii::$app->user->identity->employee_id,
                $type,
                $label,
                \Yii::$app->params['rpOrigin']
            );
        } catch (ServiceException $e) {
            \Yii::error([
                'status' => 'MFA create error',
                'error' => $e->getMessage(),
                'httpStatusCode' => $e->httpStatusCode,
            ], __METHOD__);

            throw new HttpException(
                $e->httpStatusCode,
                $messages[$e->httpStatusCode] ?? 'Unexpected Problem',
                1551192684
            );
        }

        return $mfa;
    }

    /**
     * @param $mfaId
     * @return null
     * @throws ServiceException
     * @throws NotFoundHttpException
     */
    public function actionDelete($mfaId)
    {
        try {
            return $this->idBrokerClient->mfaDelete($mfaId, \Yii::$app->user->identity->employee_id);
        } catch (ServiceException $e) {
            \Yii::error([
                'status' => 'MFA delete error',
                'message' => $e->getMessage(),
            ], __METHOD__);
            if ($e->httpStatusCode == 404) {
                throw new NotFoundHttpException(\Yii::t('app', 'Mfa.RecordNotFound'));
            }

            /*
             * Other status codes will result in a 500 response
             */
            throw $e;
        }
    }

    /**
     * @param $mfaId
     * @param $webauthnId
     * @return null
     * @throws ServiceException
     * @throws NotFoundHttpException
     */
    public function actionDeleteWebauthn($mfaId, $webauthnId)
    {
        try {
            return $this->idBrokerClient->mfaDeleteWebauthn(
                $mfaId,
                \Yii::$app->user->identity->employee_id,
                $webauthnId
            );
        } catch (ServiceException $e) {
            \Yii::error([
                'status' => 'MFA delete webauthn error',
                'message' => $e->getMessage(),
            ], __METHOD__);
            if ($e->httpStatusCode == 404) {
                throw new NotFoundHttpException(\Yii::t('app', 'Mfa.RecordNotFound'));
            }

            /*
             * Other status codes will result in a 500 response
             */
            throw $e;
        }
    }

    /**
     * @return array|null
     */
    private static function getVerifyValue()
    {
        $value = \Yii::$app->request->getBodyParam('value');
        if ($value === null) {
            return null;
        }

        if (isset($value['clientExtensionResults']) && empty($value['clientExtensionResults'])) {
            // Force JSON-encoding to treat this as an empty object, not an empty array.
            $value['clientExtensionResults'] = new stdClass();
        }

        return $value;
    }

    /**
     * @param $mfaId
     * @return array|bool
     * @throws HttpException
     */
    public function actionVerify($mfaId)
    {
        $messages = [
            400 => \Yii::t('app', 'Mfa.InvalidCode'),
            404 => \Yii::t('app', 'Mfa.VerifyFailure'),
            429 => \Yii::t('app', 'Mfa.RateLimitFailure'),
        ];

        $value = self::getVerifyValue();
        if ($value === null) {
            throw new BadRequestHttpException(\Yii::t('app', 'Mfa.MissingValue'));
        }

        try {
            $mfa = $this->idBrokerClient->mfaVerify(
                $mfaId,
                \Yii::$app->user->identity->employee_id,
                $value,
                \Yii::$app->params['rpOrigin']
            );
        } catch (ServiceException $e) {
            \Yii::warning([
                'status' => 'MFA verify error',
                'error' => $e->getMessage(),
                'httpStatusCode' => $e->httpStatusCode,
            ], __METHOD__);

            throw new HttpException(
                $e->httpStatusCode,
                $messages[$e->httpStatusCode] ?? '',
                1551109134
            );
        }

        return $mfa;
    }

    /**
     * @param $mfaId
     * @return array|bool
     * @throws HttpException
     */
    public function actionVerifyRegistration($mfaId)
    {
        $messages = [
            400 => \Yii::t('app', 'Mfa.InvalidCode'),
            404 => \Yii::t('app', 'Mfa.VerifyFailure'),
            429 => \Yii::t('app', 'Mfa.RateLimitFailure'),
        ];

        $value = self::getVerifyValue();
        if ($value === null) {
            throw new BadRequestHttpException(\Yii::t('app', 'Mfa.MissingValue'));
        }

        $label = trim(\Yii::$app->request->getBodyParam('label', ''));

        try {
            $mfa = $this->idBrokerClient->mfaVerify(
                $mfaId,
                \Yii::$app->user->identity->employee_id,
                $value,
                \Yii::$app->params['rpOrigin'],
                'registration',
                $label,
            );
        } catch (ServiceException $e) {
            \Yii::warning([
                'status' => 'MFA verify registration error',
                'error' => $e->getMessage(),
                'httpStatusCode' => $e->httpStatusCode,
            ], __METHOD__);

            throw new HttpException(
                $e->httpStatusCode,
                $messages[$e->httpStatusCode] ?? '',
                1669805221
            );
        }

        return $mfa;
    }

    /**
     * @param $mfaId
     * @throws NotFoundHttpException
     * @throws BadRequestHttpException
     * @throws ServiceException
     */
    public function actionUpdate($mfaId)
    {
        $label = trim(\Yii::$app->request->getBodyParam('label', ''));
        if ($label === '') {
            throw new BadRequestHttpException(\Yii::t('app', 'Mfa.MissingLabel'));
        }

        try {
            return $this->idBrokerClient->mfaUpdate($mfaId, \Yii::$app->user->identity->employee_id, $label);
        } catch (ServiceException $e) {
            \Yii::error([
                'status' => 'MFA update error',
                'message' => $e->getMessage(),
            ], __METHOD__);
            if ($e->httpStatusCode == 404) {
                throw new NotFoundHttpException(\Yii::t('app', 'Mfa.UpdateFailure'), $e->getCode());
            }

            /*
             * Other status codes will result in a 500 response
             */
            throw $e;
        }
    }

    /**
     * @param $mfaId
     * @param $webauthnId
     * @throws NotFoundHttpException
     * @throws ServiceException
     */
    public function actionUpdateWebauthn($mfaId, $webauthnId)
    {
        $label = trim(\Yii::$app->request->getBodyParam('label', ''));
        if ($label === '') {
            throw new BadRequestHttpException(\Yii::t('app', 'Mfa.MissingLabel'));
        }

        try {
            return $this->idBrokerClient->mfaUpdateWebauthn(
                $mfaId,
                \Yii::$app->user->identity->employee_id,
                $label,
                $webauthnId
            );
        } catch (ServiceException $e) {
            \Yii::error([
                'status' => 'MFA update webauthn error',
                'message' => $e->getMessage(),
            ], __METHOD__);
            if ($e->httpStatusCode == 404) {
                throw new NotFoundHttpException(\Yii::t('app', 'Mfa.UpdateFailure'), $e->getCode());
            }

            /*
             * Other status codes will result in a 500 response
             */
            throw $e;
        }
    }
}
