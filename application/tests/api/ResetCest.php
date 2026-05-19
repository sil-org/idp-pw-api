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

    public function test800(ApiTester $I)
    {
        $I->wantTo('check response when making unauthenticated PUT request to validate a reset UUID');
        $I->sendPUT('/reset/11111111111111111111111111111111/validate');
        $I->seeResponseCodeIs(200);
    }

    public function test810(ApiTester $I)
    {
        $I->wantTo('check response when making unauthenticated PUT request to validate an expired reset UUID');
        $I->sendPUT('/reset/22222222222222222222222222222222/validate');
        $I->seeResponseCodeIs(404);
    }

    public function test820(ApiTester $I)
    {
        $I->wantTo('check response when making authenticated PUT request to validate a reset UUID');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendPUT('/reset/33333333333333333333333333333333/validate');
        $I->seeResponseCodeIs(200);
    }

    public function test830(ApiTester $I)
    {
        $I->wantTo('check response when making authenticated DELETE request to reset/{uuid}/validate');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendDELETE('/reset/44444444444444444444444444444444/validate');
        $I->seeResponseCodeIs(405);
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
        $I->wantTo('check response when making an authenticated POST request to create a reset');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendPOST('/reset', ['username' => 'first_last']);
        $I->seeResponseCodeIs(204);
    }
}
