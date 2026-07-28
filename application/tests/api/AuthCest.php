<?php

require_once "BaseCest.php";

class AuthCest extends BaseCest
{
    public function loginGetWithoutToken(ApiTester $I)
    {
        $I->wantTo('check response when making a GET request for logging in with no access_token');
        $I->stopFollowingRedirects();
        $I->sendGET('/auth/login');
        $I->seeResponseCodeIs(302);
    }

    public function loginPost(ApiTester $I)
    {
        $I->wantTo('check response when making a POST request for logging in');
        $I->stopFollowingRedirects();
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendPOST('/auth/login');
        $I->seeResponseCodeIs(302);
    }

    public function loginPut(ApiTester $I)
    {
        $I->wantTo('check response when making a PUT request for logging in');
        $I->stopFollowingRedirects();
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendPUT('/auth/login');
        $I->seeResponseCodeIs(405);
    }

    public function loginDelete(ApiTester $I)
    {
        $I->wantTo('check response when making a DELETE request for logging in');
        $I->stopFollowingRedirects();
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendDELETE('/auth/login');
        $I->seeResponseCodeIs(405);
    }

    public function loginOptions(ApiTester $I)
    {
        $I->wantTo('check response when making a OPTIONS request for logging in');
        $I->stopFollowingRedirects();
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendOPTIONS('/auth/login');
        $I->seeResponseCodeIs(405);
    }

    public function logoutGetLoggedIn(ApiTester $I)
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

    public function logoutGetLoggedOutWithoutOrigin(ApiTester $I)
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

    public function logoutPost(ApiTester $I)
    {
        $I->wantTo('check response for making a POST request for logging out when already logged in');
        $I->stopFollowingRedirects();
        $I->setCookie('access_token', 'user2', parent::getCookieConfig());
        $I->sendPOST('/auth/logout');
        $I->seeResponseCodeIs(405);
    }

    public function logoutPut(ApiTester $I)
    {
        $I->wantTo('check response for making a PUT request for logging out when already logged in');
        $I->stopFollowingRedirects();
        $I->setCookie('access_token', 'user2', parent::getCookieConfig());
        $I->sendPUT('/auth/logout');
        $I->seeResponseCodeIs(405);
    }

    public function logoutOptions(ApiTester $I)
    {
        $I->wantTo('check response for making a OPTIONS request for logging out when already logged in');
        $I->stopFollowingRedirects();
        $I->setCookie('access_token', 'user2', parent::getCookieConfig());
        $I->sendOPTIONS('/auth/logout');
        $I->seeResponseCodeIs(200);
    }

    public function loginWithInviteWithoutToken(ApiTester $I)
    {
        $I->wantTo('check response for making a POST request for logging in with invite code and no access token');
        $I->stopFollowingRedirects();
        $I->sendGET('/auth/login?invite=abc123');
        $I->seeResponseCodeIs(302);
    }
}
