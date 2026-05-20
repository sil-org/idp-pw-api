<?php

namespace common\models;

use Sil\Idp\IdBroker\Client\IdBrokerClient;
use Sil\Idp\IdBroker\Client\ServiceException;
use yii\web\NotFoundHttpException;
use yii\web\ServerErrorHttpException;

class Method
{
    public const TYPE_EMAIL = 'email';
    public const TYPE_PRIMARY = 'primary';
    public const TYPE_SUPERVISOR = 'supervisor';

    protected static function getIdBrokerClient()
    {
        $config = \Yii::$app->params['idBrokerConfig'];
        return new IdBrokerClient(
            $config['baseUrl'],
            $config['accessToken'],
            [
                IdBrokerClient::TRUSTED_IPS_CONFIG              => $config['validIpRanges']       ?? [],
                IdBrokerClient::ASSERT_VALID_BROKER_IP_CONFIG   => $config['assertValidBrokerIp']   ?? true,
            ]
        );
    }

    /**
     * Gets all methods for user specified by $employeeId
     * @param string $employeeId
     * @return String[]
     * @throws ServerErrorHttpException
     * @throws ServiceException
     */
    public static function getMethods($employeeId)
    {
        try {
            return self::getIdBrokerClient()->listMethod($employeeId);
        } catch (ServiceException $e) {
            if ($e->httpStatusCode === 400) {
                throw new ServerErrorHttpException(\Yii::t('app', 'Method.PersonnelError'), 1542752270);
            } else {
                throw $e;
            }
        }
    }

    /**
     * Gets all verified methods for user specified by $employeeId
     * @param string $employeeId
     * @return array[]
     * @throws ServiceException
     * @throws ServerErrorHttpException
     */
    public static function getVerifiedMethods($employeeId)
    {
        $methods = self::getMethods($employeeId);

        $verifiedMethods = [];

        if (is_iterable($methods)) {
            foreach ($methods as $method) {
                if ($method['verified'] ?? false) {
                    $verifiedMethods[] = $method;
                }
            }
        }

        return $verifiedMethods;
    }

    /**
     * Gets a specific verified method for user specified by $employeeId
     * @param string $uid
     * @param string $employeeId
     * @return null|String[]
     * @throws NotFoundHttpException
     * @throws \Exception
     */
    public static function getOneVerifiedMethod($uid, $employeeId)
    {
        try {
            return self::getIdBrokerClient()->getMethod($uid, $employeeId);
        } catch (ServiceException $e) {
            if ($e->httpStatusCode === 404) {
                throw new NotFoundHttpException(
                    \Yii::t('app', 'Method.NotFound'),
                    1462989221
                );
            } else {
                throw new \Exception('Error retrieving method', 1553537402, $e);
            }
        }
    }
}
