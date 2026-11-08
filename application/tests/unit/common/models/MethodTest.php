<?php

namespace tests\unit\common\models;

use common\models\Method;
use PHPUnit\Framework\TestCase;
use Sil\Idp\IdBroker\Client\IdBrokerClient;
use Sil\Idp\IdBroker\Client\ServiceException;
use yii\web\ServerErrorHttpException;

class MethodTest extends TestCase
{
    public function testGetMethods_UserNotFound()
    {
        MethodWithFakeClient::$client = $this->getClientThatThrows(
            new ServiceException('Unexpected response', 0, 404)
        );

        $this->expectException(ServerErrorHttpException::class);
        $this->expectExceptionCode(1542752270);
        MethodWithFakeClient::getMethods('12345');
    }

    /**
     * Earlier ID Broker versions returned 400 for a nonexistent user.
     */
    public function testGetMethods_UserNotFound_EarlierIdBroker()
    {
        MethodWithFakeClient::$client = $this->getClientThatThrows(
            new ServiceException('Unexpected response', 0, 400)
        );

        $this->expectException(ServerErrorHttpException::class);
        $this->expectExceptionCode(1542752270);
        MethodWithFakeClient::getMethods('12345');
    }

    public function testGetMethods_OtherErrorIsRethrown()
    {
        $exception = new ServiceException('Unexpected response', 0, 500);
        MethodWithFakeClient::$client = $this->getClientThatThrows($exception);

        $this->expectExceptionObject($exception);
        MethodWithFakeClient::getMethods('12345');
    }

    private function getClientThatThrows(ServiceException $exception): IdBrokerClient
    {
        $client = $this->createMock(IdBrokerClient::class);
        $client->method('listMethod')->willThrowException($exception);
        return $client;
    }
}

class MethodWithFakeClient extends Method
{
    public static IdBrokerClient $client;

    protected static function getIdBrokerClient()
    {
        return static::$client;
    }
}
