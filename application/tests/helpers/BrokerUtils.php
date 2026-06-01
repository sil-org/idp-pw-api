<?php

namespace tests\helpers;

use common\helpers\Utils;
use Sil\Idp\IdBroker\Client\IdBrokerClient;

class BrokerUtils
{
    public static function insertFakeUsers()
    {
        $data = include __DIR__ . '/BrokerFakeData.php';

        $baseUrl = \Yii::$app->params['idBrokerConfig']['baseUrl'];
        $accessToken = \Yii::$app->params['idBrokerConfig']['accessToken'];
        $idBrokerClient = new IdBrokerClient($baseUrl, $accessToken, [
            IdBrokerClient::ASSERT_VALID_BROKER_IP_CONFIG => false,
        ]);

        $userExistsCode = 1490802526;

        foreach ($data as $userInfo) {
            try {
                $idBrokerClient->createUser($userInfo);
            } catch (\Exception $e) {
                if ($e->getCode() == $userExistsCode) {
                    $idBrokerClient->updateUser($userInfo);
                } else {
                    throw $e;
                }
            }
        }
    }

    /**
     * Pre-configure well-known test access tokens in IdBroker so that the API
     * test suite can authenticate using fixed cookie values.
     *
     * Cookie value → employee_id mapping used by the API test fixtures:
     *   'user1' → '111111'  (auth_type: login)
     *   'user2' → '222222'  (auth_type: login)
     *   'user3' → '333333'  (auth_type: reset)
     *   'user5' → '5'       (auth_type: reset)
     *   'user6' → '6'       (auth_type: reset)
     *
     * Note: 'user4' deliberately has NO access token (used to test unauthenticated
     * access with a stale cookie).
     *
     * This requires IdBroker to support `access_token`, `access_token_expiration`,
     * and `auth_type` as user fields.
     */
    public static function setupTestAccessTokens()
    {
        $baseUrl = \Yii::$app->params['idBrokerConfig']['baseUrl'];
        $accessToken = \Yii::$app->params['idBrokerConfig']['accessToken'];
        $idBrokerClient = new IdBrokerClient($baseUrl, $accessToken, [
            IdBrokerClient::ASSERT_VALID_BROKER_IP_CONFIG => false,
        ]);

        $expiration = date('Y-m-d H:i:s', time() + 1800);

        $tokenSetups = [
            ['cookie' => 'user1', 'employee_id' => '111111', 'auth_type' => 'login'],
            ['cookie' => 'user2', 'employee_id' => '222222', 'auth_type' => 'login'],
            ['cookie' => 'user3', 'employee_id' => '333333', 'auth_type' => 'reset'],
            ['cookie' => 'user5', 'employee_id' => '5',      'auth_type' => 'reset'],
            ['cookie' => 'user6', 'employee_id' => '6',      'auth_type' => 'reset'],
        ];

        foreach ($tokenSetups as $setup) {
            $hash = Utils::getAccessTokenHash($setup['cookie']);
            $idBrokerClient->updateUser([
                'employee_id'             => $setup['employee_id'],
                'access_token'            => $hash,
                'access_token_expiration' => $expiration,
                'auth_type'               => $setup['auth_type'],
            ]);
        }
    }

    public static function insertFakeMethods()
    {
        $data = include __DIR__ . '/BrokerFakeMethods.php';

        $baseUrl = \Yii::$app->params['idBrokerConfig']['baseUrl'];
        $accessToken = \Yii::$app->params['idBrokerConfig']['accessToken'];
        $idBrokerClient = new IdBrokerClient($baseUrl, $accessToken, [
            IdBrokerClient::ASSERT_VALID_BROKER_IP_CONFIG => false,
        ]);

        foreach ($data as $methodInfo) {
            $idBrokerClient->createMethod($methodInfo['employee_id'], $methodInfo['value']);
        }
    }
}
