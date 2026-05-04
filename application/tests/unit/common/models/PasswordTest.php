<?php

namespace tests\unit\common\models;

use Sil\Codeception\TestCase\Test;
use common\helpers\Utils;
use common\models\Password;
use common\models\User;
use tests\unit\fixtures\common\models\UserFixture;

class PasswordTest extends Test
{
    public function _fixtures()
    {
        return [
            'users' => UserFixture::class,
        ];
    }

    public function testZxcvbn()
    {
        $this->markTestSkipped('Depends on zxcvbn api, enable after refactoring to use a mock or something.');
        $testData = $this->getTestData();
        foreach ($testData as $testCase) {
            $strength = Utils::getZxcvbnScore($testCase['password']);
            $this->assertEquals(
                $testCase['zxcvbnScore'],
                $strength['score'],
                'Zxcvbn score mismatch for password ' . $testCase['password']
            );
        }
    }

    public function testValidation()
    {
        $this->markTestSkipped('Depends on zxcvbn api, enable after refactoring to use a mock or something.');
        $testData = $this->getTestData();

        $employeeId = '111111';
        $user = User::findOne(['employee_id' => $employeeId]);

        foreach ($testData as $testCase) {
            $password = Password::create($user, $testCase['password']);
            $valid = $password->validate();
            $errors = $password->getErrors('password');
            $validationErrorsString = join('|', array_values($errors));

            $this->assertEquals(
                $testCase['overall'],
                $valid,
                'Failed validating test case: ' . $testCase['password']
            );

            $minLengthStatus = ! substr_count($validationErrorsString, 'code 100') > 0;
            $this->assertEquals(
                $testCase['minLength'],
                $minLengthStatus,
                'Failed validating test case: ' . $testCase['password']
            );

            $maxLengthStatus = ! substr_count($validationErrorsString, 'code 110') > 0;
            $this->assertEquals(
                $testCase['maxLength'],
                $maxLengthStatus,
                'Failed validating test case: ' . $testCase['password']
            );

            /*
             * Zxcvbn validation is skipped if any other validation errors occur, so only assert
             * failure if other tests pass and this should fail
             */
            if ($testCase['nonZxcvbnPass']) {
                $zxcvbnStatus = ! substr_count($validationErrorsString, 'code 150') > 0;
                $this->assertEquals(
                    $testCase['zxcvbnPass'],
                    $zxcvbnStatus,
                    'Failed validating test case: ' . $testCase['password']
                );
            }

        }
    }


    public function testVsUserAttributes()
    {
        $employeeId = '111111';
        $user = User::findOne(['employee_id' => $employeeId]);

        $testData = [
            'a' . $user->first_name . 'z',
            mb_strtoupper($user->last_name) . 'z',
            'a' . $user->idp_username,
            $user->email,
        ];
        foreach ($testData as $testPassword) {
            $password = Password::create($user, $testPassword);

            $password->validate();
            $errors = $password->getErrors('password');
            $validationErrorsString = join('|', array_values($errors));

            /*
             * Codeception is not working with Yii i18n as we have it
             * set up, so the error comparison is using the error message
             * translation key.
             */
            $this->assertTrue(
                substr_count($validationErrorsString, 'Password.DisallowedContent') > 0,
                'Failed validating test case: ' . $testPassword
                    . '. No error for matching a user attribute.'
            );
        }
    }

    public function testBadBytes()
    {
        $employeeId = '111111';
        $user = User::findOne(['employee_id' => $employeeId]);

        $badPassword = "1" . "\0" . "23456";
        $password = Password::create($user, $badPassword);
        $password->validate();
        $errors = join('|', array_values($password->getErrors('password')));
        $msg = sprintf('Failed validating test for bad bytes in password. (Errors: "%s")', $errors);
        $this->assertTrue(str_contains($errors, 'Password.ContainsBadByte'), $msg);
    }

    public function testAlphaAndNumeric()
    {
        $employeeId = '111111';
        $user = User::findOne(['employee_id' => $employeeId]);

        $passwords = [
            "123456" => false,
            "abcdef" => false,
            "éiéīơẖ" => false,
            "abc123" => true,
            "123abc" => true,
            "1ü3123" => true,
            "12345¨" => true,
        ];

        foreach ($passwords as $pw => $good) {
            $password = Password::create($user, $pw);
            $password->config['requireAlphaAndNumeric'] = true;
            $password->validate();

            $errors = join('|', array_values($password->getErrors('password')));
            if ($good) {
                $msg = sprintf('failed validating password for requiring alpha and numeric with good password "%s". (Errors: "%s")', $pw, $errors);
                $this->assertStringNotContainsString('Password.AlphaAndNumericRequired', $errors, $msg);
            } else {
                $msg = sprintf('Failed validating test for requiring alpha and numeric in password "%s". (Errors: "%s")', $pw, $errors);
                $this->assertStringContainsString('Password.AlphaAndNumericRequired', $errors, $msg);
            }
        }
    }

    private function getTestData()
    {
        return [
            [
                'password' => 'asdf1234',
                'zxcvbnScore' => 0,
                'zxcvbnPass' => false,
                'minLength' => false,
                'maxLength' => true,
                'overall' => false,
                'nonZxcvbnPass' => false,
            ],
            [
                'password' => 'Complex-ish p$ssw!or12',
                'zxcvbnScore' => 4,
                'zxcvbnPass' => true,
                'minLength' => true,
                'maxLength' => true,
                'overall' => true,
                'nonZxcvbnPass' => true,
            ],
            [
                'password' => 'password',
                'zxcvbnScore' => 0,
                'zxcvbnPass' => false,
                'minLength' => false,
                'maxLength' => true,
                'overall' => false,
                'nonZxcvbnPass' => false,
            ],
            [
                'password' => '1John 3:16',
                'zxcvbnScore' => 3,
                'zxcvbnPass' => true,
                'minLength' => true,
                'maxLength' => true,
                'overall' => true,
                'nonZxcvbnPass' => true,
            ],
            [
                'password' => 'luv kitties4!',
                'zxcvbnScore' => 4,
                'zxcvbnPass' => true,
                'minLength' => true,
                'maxLength' => true,
                'overall' => false,
                'nonZxcvbnPass' => false,
            ],
            [
                'password' => 'jesus',
                'zxcvbnScore' => 0,
                'zxcvbnPass' => false,
                'minLength' => false,
                'maxLength' => true,
                'overall' => false,
                'nonZxcvbnPass' => false,
            ],
            [
                'password' => 'Je$u$12345',
                'zxcvbnScore' => 1,
                'zxcvbnPass' => false,
                'minLength' => true,
                'maxLength' => true,
                'overall' => false,
                'nonZxcvbnPass' => true,
            ],

        ];
    }
}
