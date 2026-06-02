<?php

require_once "BaseCest.php";

class UserCest extends BaseCest
{
    public function test1(ApiTester $I)
    {
        $I->wantTo('check response when making GET request to /user/me with correct token');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendGET('/user/me');
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson([
            'first_name' => "First",
            'last_name' => "Last",
            'idp_username' => 'first_last',
            'email' => 'first_last@organization.org',
        ]);
    }

    public function test2(ApiTester $I)
    {
        $I->wantTo('check response when making GET request to /user/me with incorrect token');
        $I->setCookie('access_token', 'invalidToken', parent::getCookieConfig());
        $I->sendGET('/user/me');
        $I->seeResponseCodeIs(401);
    }

    public function test3(ApiTester $I)
    {
        $I->wantTo('check response when making authenticated POST request to /user/me');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendPOST('/user/me');
        $I->seeResponseCodeIs(405);
    }

    public function test4(ApiTester $I)
    {
        $I->wantTo('check response when making unauthenticated POST request to /user/me');
        $I->setCookie('access_token', 'invalidToken', parent::getCookieConfig());
        $I->sendPOST('/user/me');
        $I->seeResponseCodeIs(401);
    }

    public function test5(ApiTester $I)
    {
        $I->wantTo('check response when making authenticated DELETE request to /user/me');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendDELETE('/user/me');
        $I->seeResponseCodeIs(405);
    }

    public function test6(ApiTester $I)
    {
        $I->wantTo('check response when making unauthenticated DELETE request to /user/me');
        $I->setCookie('access_token', 'invalidToken', parent::getCookieConfig());
        $I->sendDELETE('/user/me');
        $I->seeResponseCodeIs(401);
    }

    public function test7(ApiTester $I)
    {
        $I->wantTo('check response when making authenticated PATCH request to /user/me');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendPATCH('/user/me');
        $I->seeResponseCodeIs(405);
    }

    public function test8(ApiTester $I)
    {
        $I->wantTo('check response when making unauthenticated PATCH request to /user/me');
        $I->setCookie('access_token', 'invalidToken', parent::getCookieConfig());
        $I->sendPATCH('/user/me');
        $I->seeResponseCodeIs(401);
    }

    public function test9(ApiTester $I)
    {
        $I->wantTo('check response when making authenticated OPTIONS request to /user/me');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendOPTIONS('/user/me');
        $I->seeResponseCodeIs(200);
    }

    public function test10(ApiTester $I)
    {
        $I->wantTo('check response when making unauthenticated OPTIONS request to /user/me');
        $I->setCookie('access_token', 'invalidToken', parent::getCookieConfig());
        $I->sendOPTIONS('/user/me');
        $I->seeResponseCodeIs(200);
    }

    public function test11(ApiTester $I)
    {
        $I->wantTo('check response when making PUT request to /user/me with correct token');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendPUT('/user/me');
        $I->seeResponseCodeIs(405);
    }

    public function test12(ApiTester $I)
    {
        $I->wantTo('check response when making PUT request to /user/me with incorrect token');
        $I->setCookie('access_token', 'invalidToken', parent::getCookieConfig());
        $I->sendPUT('/user/me');
        $I->seeResponseCodeIs(401);
    }
}
