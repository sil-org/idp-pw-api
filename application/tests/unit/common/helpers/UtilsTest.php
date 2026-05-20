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

    public function testMaskEmail()
    {
        $email1 = 'abc@domain.com';
        $expected1 = 'a*c@d*****.c**';
        $this->assertEquals($expected1, Utils::maskEmail($email1));

        $email2 = 'first_last@myco.org';
        $expected2 = 'f****_l**t@m***.o**';
        $this->assertEquals($expected2, Utils::maskEmail($email2));

        $email3 = 'first_last@myco.org.uk';
        $expected3 = 'f****_l**t@m***.o**.u*';
        $this->assertEquals($expected3, Utils::maskEmail($email3));
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
                'maxLength' => 255,
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
            'maxLength' => 255,
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
}
