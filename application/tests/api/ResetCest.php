<?php

require_once "BaseCest.php";

class ResetCest extends BaseCest
{
    public function test1(ApiTester $I)
    {
        $I->wantTo('check response when making authenticated GET request to /reset');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendGET('/reset');
        $I->seeResponseCodeIs(405);
    }

    public function test2(ApiTester $I)
    {
        $I->wantTo('check response when making unauthenticated GET request to /reset');
        $I->sendGET('/reset');
        $I->seeResponseCodeIs(401);
    }

    public function test12(ApiTester $I)
    {
        $I->wantTo('check response when making authenticated DELETE request to /reset');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendDELETE('/reset');
        $I->seeResponseCodeIs(405);
    }

    public function test13(ApiTester $I)
    {
        $I->wantTo('check response when making unauthenticated DELETE request to /reset');
        $I->sendDELETE('/reset');
        $I->seeResponseCodeIs(401);
    }

    public function test14(ApiTester $I)
    {
        $I->wantTo('check response when making unauthenticated OPTIONS request to /reset');
        $I->sendOPTIONS('/reset');
        $I->seeResponseCodeIs(200);
    }

    public function test3(ApiTester $I)
    {
        $I->wantTo('check response when making authenticated GET request for obtaining reset object');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendGET('/reset/11111111111111111111111111111111');
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson([
            'uid' => "11111111111111111111111111111111",
            'methods' => [
                'type' => "primary",
                'value' => "f****_l**t@o***********.o**",
            ]
        ]);
    }

    public function test4(ApiTester $I)
    {
        $I->wantTo('check response when making unauthenticated GET request for obtaining reset object and verify methods are masked out');
        $I->sendGET('/reset/11111111111111111111111111111111');
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson([
            'uid' => "11111111111111111111111111111111",
            'methods' => [
                'type' => "primary",
                'value' => "f****_l**t@o***********.o**",
            ]
        ]);
    }

    public function test5(ApiTester $I)
    {
        $I->wantTo('check response when making PUT request for updating a reset object method');
        $I->sendPUT('/reset/11111111111111111111111111111111', [
            'uid' => '22222222222222222222222222222222',
            'type' => 'phone',
            'value' => '###-###-4567'
        ]);
        $I->seeResponseCodeIs(400); // phone reset is not supported
    }

    public function test6(ApiTester $I)
    {
        $I->wantTo('check response when making PUT request for updating a reset object method supervisor');
        $I->sendPUT('/reset/11111111111111111111111111111111', [
            'type' => 'supervisor'
        ]);
        $I->seeResponseCodeIs(200);
    }

    public function test62(ApiTester $I)
    {
        $I->wantTo('check response when making authenticated DELETE request to /reset/id');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendDELETE('/reset/11111111111111111111111111111111');
        $I->seeResponseCodeIs(405);
    }

    public function test63(ApiTester $I)
    {
        $I->wantTo('check response when making unauthenticated DELETE request to /reset/id');
        $I->sendDELETE('/reset/11111111111111111111111111111111');
        $I->seeResponseCodeIs(401);
    }

    public function test7(ApiTester $I)
    {
        $I->wantTo('check response when making unauthenticated PUT request to resend the reset');
        $I->sendPUT('/reset/11111111111111111111111111111111/resend');
        $I->seeResponseCodeIs(200);
    }

    public function test72(ApiTester $I)
    {
        $I->wantTo('check response when making authenticated PUT request to resend the verification');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendPUT('/reset/11111111111111111111111111111111/resend');
        $I->seeResponseCodeIs(200);
    }

    public function test8(ApiTester $I)
    {
        $I->wantTo('check response when making unauthenticated PUT request to validate a reset code');
        $I->sendPUT('/reset/33333333333333333333333333333333/validate', ['code' => '333']);
        $I->seeResponseCodeIs(200);
    }

    public function test81(ApiTester $I)
    {
        $I->wantTo('check response when making unauthenticated PUT request to validate an expired reset code');
        $I->sendPUT('/reset/33333333333333333333333333333334/validate', ['code' => '444']);
        $I->seeResponseCodeIs(410);
    }

    public function test812(ApiTester $I)
    {
        $I->wantTo('check response on unauthenticated PUT request to validate an expired, incorrect reset code');
        $I->sendPUT('/reset/33333333333333333333333333333334/validate', ['code' => 'xxx']);
        $I->seeResponseCodeIs(400);
    }

    public function test82(ApiTester $I)
    {
        $I->wantTo('check response when making authenticated PUT request to validate a reset code');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendPUT('/reset/33333333333333333333333333333333/validate', ['code' => '333']);
        $I->seeResponseCodeIs(200);
    }

    public function test83(ApiTester $I)
    {
        $I->wantTo('check response when making authenticated DELETE request to reset/id/validate');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendDELETE('/reset/33333333333333333333333333333333/validate', ['code' => '333']);
        $I->seeResponseCodeIs(405);
    }

    public function test9(ApiTester $I)
    {
        $I->wantTo('check response when making multiple authenticated PUT request to validate a reset code');
        for ($i = 0; $i < 10; $i++) {
            $I->sendPUT('/reset/33333333333333333333333333333334/validate', ['code' => '344']);
            if ($i < 9) {
                $I->seeResponseCodeIs(400);
            } else {
                $I->seeResponseCodeIs(429);
            }
        }
    }

    public function test91(ApiTester $I)
    {
        $I->wantTo('check response when making a POST request to create a reset');
        $I->sendPOST('/reset', ['username' => 'first_last']);
        $I->seeResponseCodeIs(204);
    }

    public function test92(ApiTester $I)
    {
        $I->wantTo('check response when making a POST request to create a reset for an invalid user');
        $I->sendPOST('/reset', ['username' => 'xxxxx']);
        $I->seeResponseCodeIs(204);
    }

    public function test93(ApiTester $I)
    {
        $I->wantTo('check response when making a POST request to create a reset for a user with hide flag');
        $I->sendPOST('/reset', ['username' => 'user_two']);
        $I->seeResponseCodeIs(204);
    }
}
