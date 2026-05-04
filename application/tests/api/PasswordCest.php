<?php

require_once "BaseCest.php";

use common\helpers\Utils;

class PasswordCest extends BaseCest
{
    public function test1(ApiTester $I)
    {
        $I->wantTo('check response when making GET request with no token for obtaining info about password');
        $I->sendGET('/password');
        $I->seeResponseCodeIs(401);
    }

    public function test2(ApiTester $I)
    {
        $I->wantTo('check response when making GET request with incorrect token for obtaining info about password');
        $I->setCookie('access_token', 'invalidToken', parent::getCookieConfig());
        $I->sendGET('/password');
        $I->seeResponseCodeIs(401);
    }

    public function test3(ApiTester $I)
    {
        $I->wantTo('check response when making authenticated POST request to /password');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendPOST('/password');
        $I->seeResponseCodeIs(405);
    }

    public function test4(ApiTester $I)
    {
        $I->wantTo('check response when making unauthenticated POST request to /password');
        $I->setCookie('access_token', 'invalidToken', parent::getCookieConfig());
        $I->sendPOST('/password');
        $I->seeResponseCodeIs(401);
    }

    public function test5(ApiTester $I)
    {
        $I->wantTo('check response when making authenticated PUT request to update the password');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendPUT('/password', ['password' => Utils::generateRandomString() . '!12']);
        $I->seeResponseCodeIs(200);
    }

    public function test6(ApiTester $I)
    {
        $I->wantTo('check response when making authenticated DELETE request to /password');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendDELETE('/password');
        $I->seeResponseCodeIs(405);
    }

    public function test7(ApiTester $I)
    {
        $I->wantTo('check response when making GET request with correct token for obtaining info about password');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendGET('/password');
        $I->seeResponseCodeIs(200);
        $I->seeResponseMatchesJsonType([
            'last_changed' => 'string:date',
            'expires' => 'string:date',
        ]);
    }

    public function test8(ApiTester $I)
    {
        $I->wantTo('check response when making unauthenticated DELETE request to /password');
        $I->setCookie('access_token', 'invalidToken', parent::getCookieConfig());
        $I->sendDELETE('/password');
        $I->seeResponseCodeIs(401);
    }

    public function test9(ApiTester $I)
    {
        $I->wantTo('check response when making unauthenticated PATCH request to /password');
        $I->setCookie('access_token', 'invalidToken', parent::getCookieConfig());
        $I->sendPATCH('/password');
        $I->seeResponseCodeIs(401);
    }

    public function test10(ApiTester $I)
    {
        $I->wantTo('check response when making authenticated PATCH request to /password');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendPATCH('/password');
        $I->seeResponseCodeIs(405);
    }

    public function test11(ApiTester $I)
    {
        $I->wantTo('check response when making authenticated OPTIONS request to /password');
        $I->setCookie('access_token', 'invalidToken', parent::getCookieConfig());
        $I->sendOPTIONS('/password');
        $I->seeResponseCodeIs(200);
    }

    public function test12(ApiTester $I)
    {
        $I->wantTo('check response when changing the password (PUT request) to something that '
            . 'does not meet minLength requirement');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendPUT('/password', ['password' => 'A!dswo']);
        $I->seeResponseCodeIs(400);
        $body = json_decode($I->grabResponse(), true);
        if (substr_count($body['message'], 'code 100') <= 0) {
            throw new \Exception('Expected error code not present in message', 1466798390);
        }
    }

    public function test15(ApiTester $I)
    {
        $I->wantTo('check response when changing the password (PUT request) to something that '
            . 'has zxcvbn score of 1');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendPUT('/password', ['password' => 'Je$u$12345']);
        $I->seeResponseCodeIs(400);
        $body = json_decode($I->grabResponse(), true);
        if (substr_count($body['message'], 'code 150') <= 0) {
            throw new \Exception('Expected error code not present in message', 1466798392);
        }
    }

    public function test16(ApiTester $I)
    {
        $I->wantTo('check response when changing the password (PUT request) to something that has zxcvbn score of 2');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendPUT('/password', ['password' => Utils::generateRandomString() . '!12']);
        $I->seeResponseCodeIs(200);
    }

    public function test17(ApiTester $I)
    {
        $I->wantTo('check response when changing the password (PUT request) to something that has zxcvbn score of 3');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendPUT('/password', ['password' => Utils::generateRandomString() . '!12']);
        $I->seeResponseCodeIs(200);
    }

    public function test18(ApiTester $I)
    {
        $I->wantTo('check response when changing the password (PUT request) to something that '
            . 'does not meet maxLength requirement');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendPUT('/password', ['password' => 'Lorem ipsum dolor sit amet, nonummy ligula volutpat '
            . 'hac integer nonummy. Suspendisse ultricies, congue etiam tellus, erat libero, nulla '
            . 'eleifend, mauris pellentesque. Suspendisse integer praesent vel, integer gravida mauris, '
            . 'fringilla vehicula lacinia non123. Suspendisse integer praesent vel, integer gravida '
            . 'mauris, fringilla vehi. Suspendisse integer praesent vel, integer gravida mauris, '
            . 'fringilla vehi']);
        $I->seeResponseCodeIs(400);
        $body = json_decode($I->grabResponse(), true);
        if (substr_count($body['message'], 'code 110') <= 0) {
            throw new \Exception('Expected error code not present in message', 1466798393);
        }
    }

    public function test19(ApiTester $I)
    {
        $I->wantTo('check response when changing the password (PUT request) to something that contains the first_name');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendPUT('/password', ['password' => 'aUSERz']);
        $I->seeResponseCodeIs(400);
        $body = json_decode($I->grabResponse(), true);
        if (substr_count($body['message'], 'code 180') <= 0) {
            throw new \Exception('Expected error code not present in message', 1466798394);
        }
    }

    public function test20(ApiTester $I)
    {
        $I->wantTo('check response when changing the password (PUT request) to something that contains the last_name');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendPUT('/password', ['password' => 'aONEz']);
        $I->seeResponseCodeIs(400);
        $body = json_decode($I->grabResponse(), true);
        if (substr_count($body['message'], 'code 180') <= 0) {
            throw new \Exception('Expected error code not present in message', 1466798395);
        }
    }

    public function test21(ApiTester $I)
    {
        $I->wantTo('check response when changing the password (PUT request) to something that contains the idp_username');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendPUT('/password', ['password' => 'First_Lastzzzz']);
        $I->seeResponseCodeIs(400);
        $body = json_decode($I->grabResponse(), true);
        if (substr_count($body['message'], 'code 180') <= 0) {
            throw new \Exception('Expected error code not present in message', 1466798396);
        }
    }

    public function test22(ApiTester $I)
    {
        $I->wantTo('check response when changing the password (PUT request) to something that contains the email address');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendPUT('/password', ['password' => 'aaaafirst_last@organization.org']);
        $I->seeResponseCodeIs(400);
        $body = json_decode($I->grabResponse(), true);
        if (substr_count($body['message'], 'code 180') <= 0) {
            throw new \Exception('Expected error code not present in message', 1466798397);
        }
    }

    public function test23(ApiTester $I)
    {
        $I->wantTo('check response when making PUT request with incorrect token');
        $I->setCookie('access_token', 'invalidToken', parent::getCookieConfig());
        $I->sendPUT('/password', ['password' => 'a_password']);
        $I->seeResponseCodeIs(401);
    }

    public function test24(ApiTester $I)
    {
        $I->wantTo('check response when changing the password (PUT request) to something that contains a short first_name');
        $I->setCookie('access_token', 'user6', parent::getCookieConfig());
        $I->sendPUT('/password', ['password' => 'aUsz56789!']);
        $I->seeResponseCodeIs(200);
    }

    public function test25(ApiTester $I)
    {
        $I->wantTo('check response when changing the password (PUT request) to something that contains a short last_name');
        $I->setCookie('access_token', 'user6', parent::getCookieConfig());
        $I->sendPUT('/password', ['password' => 'aSxz56789!']);
        $I->seeResponseCodeIs(200);
    }
}
