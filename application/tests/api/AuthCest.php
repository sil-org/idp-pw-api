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

    public function logoutPostLoggedIn(ApiTester $I)
    {
        $I->wantTo('check response for POST /auth/logout from a trusted origin when logged in');
        $I->stopFollowingRedirects();
        $I->haveHttpHeader('Origin', 'http://localhost');
        $I->setCookie('access_token', 'user2', parent::getCookieConfig());
        $I->haveHttpHeader('X-Codeception-CodeCoverage', '');
        $I->haveHttpHeader('HTTP_X_CODECEPTION_CODECOVERAGE', '');
        $I->sendGET('/user/me');
        $I->seeResponseCodeIs(200);
        $I->sendPOST('/auth/logout');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['redirectUrl' => 'http://localhost/#']);
        $I->setCookie('access_token', 'user2', parent::getCookieConfig());
        $I->sendGET('/user/me');
        $I->seeResponseCodeIs(401);
    }

    public function logoutPostLoggedOutFromTrustedOrigin(ApiTester $I)
    {
        $I->wantTo('check response for POST /auth/logout from a trusted origin when already logged out');
        $I->stopFollowingRedirects();
        $I->haveHttpHeader('Origin', 'http://localhost');
        $I->setCookie('access_token', 'user4', parent::getCookieConfig());
        $I->sendGET('/user/me');
        $I->seeResponseCodeIs(401);
        $I->sendPOST('/auth/logout');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['redirectUrl' => 'http://localhost/#']);
        $I->setCookie('access_token', 'user4', parent::getCookieConfig());
        $I->sendGET('/user/me');
        $I->seeResponseCodeIs(401);
    }

    public function logoutPostWithoutOrigin(ApiTester $I)
    {
        $I->wantTo('check response for POST /auth/logout when no Origin header is sent');
        $I->stopFollowingRedirects();
        $I->setCookie('access_token', 'user2', parent::getCookieConfig());
        $I->sendPOST('/auth/logout');
        $I->seeResponseCodeIs(400);
        $I->dontSeeHttpHeader('Location');
    }

    public function logoutPostWithUntrustedOrigin(ApiTester $I)
    {
        $I->wantTo('check response for POST /auth/logout when Origin is untrusted');
        $I->stopFollowingRedirects();
        $I->haveHttpHeader('Origin', 'http://bad');
        $I->setCookie('access_token', 'user4', parent::getCookieConfig());
        $I->sendPOST('/auth/logout');
        $I->seeResponseCodeIs(400);
        $I->dontSeeHttpHeader('Location');
    }

    public function logoutGetLegacyWithOrigin(ApiTester $I)
    {
        $I->wantTo('check legacy GET /auth/logout works in single-origin mode with Origin header');
        $I->stopFollowingRedirects();
        $I->haveHttpHeader('Origin', 'http://localhost');
        $I->setCookie('access_token', 'user2', parent::getCookieConfig());
        $I->sendGET('/auth/logout');
        $I->seeResponseCodeIs(302);
        $I->seeHttpHeader('location', 'http://localhost/#');
        $I->setCookie('access_token', 'user2', parent::getCookieConfig());
        $I->sendGET('/user/me');
        $I->seeResponseCodeIs(401);
    }

    public function logoutGetLegacyWithoutOrigin(ApiTester $I)
    {
        $I->wantTo('check legacy GET /auth/logout falls back to plain-text in multi-origin mode without Origin header');
        $I->stopFollowingRedirects();
        $I->setCookie('access_token', 'user3', parent::getCookieConfig());
        $I->sendGET('/auth/logout');
        $I->seeResponseCodeIs(200);
        $I->seeHttpHeader('Content-Type', 'text/plain; charset=utf-8');
        $I->seeHttpHeader('X-Content-Type-Options', 'nosniff');
        $I->dontSeeHttpHeader('Location');
        $I->seeResponseContains('signed out');
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
