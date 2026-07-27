<?php

require_once "BaseCest.php";

class AuthCest extends BaseCest
{
    public function test2(ApiTester $I)
    {
        $I->wantTo('check response when making a GET request for logging in with no access_token');
        $I->stopFollowingRedirects();
        $I->sendGET('/auth/login');
        $I->seeResponseCodeIs(302);
    }

    public function test3(ApiTester $I)
    {
        $I->wantTo('check response when making a POST request for logging in');
        $I->stopFollowingRedirects();
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendPOST('/auth/login');
        $I->seeResponseCodeIs(302);
    }

    public function test33(ApiTester $I)
    {
        $I->wantTo('check response when making a PUT request for logging in');
        $I->stopFollowingRedirects();
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendPUT('/auth/login');
        $I->seeResponseCodeIs(405);
    }

    public function test34(ApiTester $I)
    {
        $I->wantTo('check response when making a DELETE request for logging in');
        $I->stopFollowingRedirects();
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendDELETE('/auth/login');
        $I->seeResponseCodeIs(405);
    }

    public function test35(ApiTester $I)
    {
        $I->wantTo('check response when making a OPTIONS request for logging in');
        $I->stopFollowingRedirects();
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendOPTIONS('/auth/login');
        $I->seeResponseCodeIs(405);
    }

    public function test4(ApiTester $I)
    {
        $I->wantTo('check response for making a GET request for logging out when already logged in');
        $I->stopFollowingRedirects();
        $I->setCookie('access_token', 'user2', parent::getCookieConfig());
        $I->haveHttpHeader('X-Codeception-CodeCoverage', '');
        $I->haveHttpHeader('HTTP_X_CODECEPTION_CODECOVERAGE', '');
        $I->sendGET('/user/me');
        $I->seeResponseCodeIs(200);
        $I->sendGET('/auth/logout');
        $I->seeResponseCodeIs(302);
        $I->setCookie('access_token', 'user2', parent::getCookieConfig());
        $I->sendGET('/user/me');
        $I->seeResponseCodeIs(401);
    }

    public function test5(ApiTester $I)
    {
        $I->wantTo('check response for making a GET request for logging out when already logged out');
        $I->stopFollowingRedirects();
        $I->setCookie('access_token', 'user4', parent::getCookieConfig());
        $I->sendGET('/user/me');
        $I->seeResponseCodeIs(401);
        $I->sendGET('/auth/logout');
        $I->seeResponseCodeIs(302);
        $I->setCookie('access_token', 'user4', parent::getCookieConfig());
        $I->sendGET('/user/me');
        $I->seeResponseCodeIs(401);
    }

    public function test6(ApiTester $I)
    {
        $I->wantTo('check response for making a POST request for logging out when already logged in');
        $I->stopFollowingRedirects();
        $I->setCookie('access_token', 'user2', parent::getCookieConfig());
        $I->sendPOST('/auth/logout');
        $I->seeResponseCodeIs(405);
    }

    public function test7(ApiTester $I)
    {
        $I->wantTo('check response for making a PUT request for logging out when already logged in');
        $I->stopFollowingRedirects();
        $I->setCookie('access_token', 'user2', parent::getCookieConfig());
        $I->sendPUT('/auth/logout');
        $I->seeResponseCodeIs(405);
    }

    public function test8(ApiTester $I)
    {
        $I->wantTo('check response for making a OPTIONS request for logging out when already logged in');
        $I->stopFollowingRedirects();
        $I->setCookie('access_token', 'user2', parent::getCookieConfig());
        $I->sendOPTIONS('/auth/logout');
        $I->seeResponseCodeIs(200);
    }

    public function test91(ApiTester $I)
    {
        $I->wantTo('check response for making a POST request for logging in with invite code and no access token');
        $I->stopFollowingRedirects();
        $I->sendGET('/auth/login?invite=abc123');
        $I->seeResponseCodeIs(302);
    }
}
