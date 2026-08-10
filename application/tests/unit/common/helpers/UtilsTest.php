<?php

namespace tests\unit\common\helpers;

use common\helpers\Utils;
use Sil\Codeception\TestCase\Test;

class UtilsTest extends Test
{
    public function testUidRegexGenerateRandomString()
    {
        $regex = '/' . Utils::UID_REGEX . '/';
        for ($i = 0; $i < 50; $i++) {
            $uid = Utils::generateRandomString();
            $this->assertRegExp($regex, $uid);
        }
    }

    public function testIsValidIpAddress()
    {
        $this->assertTrue(Utils::isValidIpAddress('127.0.0.1'));
        $this->assertTrue(Utils::isValidIpAddress('fe80::58bb:d8ff:feec:ff6c'));
        $this->assertFalse(Utils::isValidIpAddress('not an ip address'));
        $this->assertFalse(Utils::isValidIpAddress('10.256.123.123'));
    }

    public function testGetFrontendConfig()
    {
        \Yii::$app->params = [
            'idpDisplayName' => 'My IdP',
            'passwordRules' => [
                'minLength' => 10,
                'maxLength' => 72,
                'minScore' => 2,
                'enableHIBP' => true,
            ],
            'recaptcha' => [
                'siteKey' => 'key',
                'secretKey' => 'secret',
            ],
            'support' => [
                'phone' => '123-123-1234',
                'email' => 'email@domain.com',
                'url' => 'http://url',
            ],
        ];

        $params = \Yii::$app->params;
        $config = Utils::getFrontendConfig();
        $this->assertEquals($params['idpDisplayName'], $config['idpName']);

        $expectedPasswordRules = [
            'minLength' => 10,
            'maxLength' => 72,
            'minScore' => 2,
            'enableHIBP' => true,
        ];

        $this->assertEquals($expectedPasswordRules, $config['passwordRules']);

        $expectedSupport = [
            'phone' => '123-123-1234',
            'email' => 'email@domain.com',
            'url' => 'http://url',
        ];

        $this->assertEquals($expectedSupport, $config['support']);
    }

    public function testGetIso8601()
    {
        $expected = '2016-06-15T13:09:28Z';
        $timestamp = 1465996168;

        $this->assertEquals($expected, Utils::getIso8601($timestamp));
    }

    public function testGetDatetime()
    {
        $expected = '2016-07-18 18:17:18';
        $timestamp = 1468865838;

        $this->assertEquals($expected, Utils::getDatetime($timestamp));
    }

    public function testIsTrustedUrl()
    {
        \Yii::$app->params['trustedOrigins'] = ['https://a.example.com', 'http://localhost'];

        $this->assertTrue(Utils::isTrustedUrl('https://a.example.com/#/foo'));
        $this->assertTrue(Utils::isTrustedUrl('http://localhost/#/foo?x=1'));

        // Not one of the configured origins, even though it starts with a trusted origin string
        $this->assertFalse(Utils::isTrustedUrl('https://a.example.com.evil.com/#/foo'));

        // Relative paths have no origin to check
        $this->assertFalse(Utils::isTrustedUrl('/foo'));
        $this->assertFalse(Utils::isTrustedUrl(''));

        // Trust is independent of the current request's Origin header
        \Yii::$app->request->headers->set('Origin', 'https://unrelated.example.com');
        $this->assertTrue(Utils::isTrustedUrl('https://a.example.com/#/foo'));
    }
}
