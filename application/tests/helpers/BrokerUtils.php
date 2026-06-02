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

        self::setupTestAccessTokens($idBrokerClient);
    }

    /**
     * Pre-configure test access tokens for fake users.
     *
     * This helper supports both legacy user token fields
     * (access_token/access_token_expiration/auth_type) and new
     * token fields (token_hash/token_expiry_utc/token_type).
     */
    private static function setupTestAccessTokens(IdBrokerClient $idBrokerClient): void
    {
        $expiration = Utils::getDatetime(time() + \Yii::$app->params['accessTokenLifetime']);
        $tokenSetups = [
            ['cookie' => 'user1', 'employee_id' => '111111', 'auth_type' => 'login'],
            ['cookie' => 'user2', 'employee_id' => '222222', 'auth_type' => 'login'],
            ['cookie' => 'user3', 'employee_id' => '333333', 'auth_type' => 'login'],
            ['cookie' => 'user5', 'employee_id' => '5', 'auth_type' => 'reset'],
            ['cookie' => 'user6', 'employee_id' => '6', 'auth_type' => 'reset'],
        ];

        foreach ($tokenSetups as $setup) {
            $hash = Utils::getAccessTokenHash($setup['cookie']);

            try {
                $idBrokerClient->updateUser([
                    'employee_id' => $setup['employee_id'],
                    'token_hash' => $hash,
                    'token_expiry_utc' => $expiration,
                    'token_type' => $setup['auth_type'],
                ]);
            } catch (\Exception $e) {
                try {
                    $idBrokerClient->updateUser([
                        'employee_id' => $setup['employee_id'],
                        'access_token' => $hash,
                        'access_token_expiration' => $expiration,
                        'auth_type' => $setup['auth_type'],
                    ]);
                } catch (\Exception $ignored) {
                }
            }
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
