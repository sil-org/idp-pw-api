<?php

require_once "BaseCest.php";

class ConfigCest extends BaseCest
{
    public function getConfigUnauthenticated(ApiTester $I)
    {
        $I->wantTo('check response when making unauthenticated GET request to config');
        $I->sendGET('/config');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $body = json_decode($I->grabResponse(), true);
        if (! array_key_exists('idpName', $body) || ! array_key_exists('support', $body)) {
            throw new \Exception('Config response does not include keys expected', 1466799197);
        }
    }

    public function getConfigAuthenticated(ApiTester $I)
    {
        $I->wantTo('check response when making authenticated GET request to config');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendGET('/config');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $body = json_decode($I->grabResponse(), true);
        if (! array_key_exists('idpName', $body) || ! array_key_exists('support', $body)) {
            throw new \Exception('Config response does not include keys expected', 1466799198);
        }
    }

    public function postConfigUnauthenticated(ApiTester $I)
    {
        $I->wantTo('check response when making unauthenticated POST request to config');
        $I->sendPOST('/config');
        $I->seeResponseCodeIs(401);
    }

    public function postConfigAuthenticated(ApiTester $I)
    {
        $I->wantTo('check response when making authenticated POST request to config');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendPOST('/config');
        $I->seeResponseCodeIs(405);
    }

    public function putConfigUnauthenticated(ApiTester $I)
    {
        $I->wantTo('check response when making unathenticated PUT request to config');
        $I->sendPUT('/config');
        $I->seeResponseCodeIs(401);
    }

    public function putConfigAuthenticated(ApiTester $I)
    {
        $I->wantTo('check response when making authenticated PUT request to config');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendPUT('/config');
        $I->seeResponseCodeIs(405);
    }

    public function deleteConfigUnauthenticated(ApiTester $I)
    {
        $I->wantTo('check response when making unathenticated DELETE request to config');
        $I->sendDELETE('/config');
        $I->seeResponseCodeIs(401);
    }

    public function deleteConfigAuthenticated(ApiTester $I)
    {
        $I->wantTo('check response when making authenticated DELETE request to config');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendDELETE('/config');
        $I->seeResponseCodeIs(405);
    }

    public function patchConfigUnauthenticated(ApiTester $I)
    {
        $I->wantTo('check response when making unathenticated PATCH request to config');
        $I->sendPATCH('/config');
        $I->seeResponseCodeIs(401);
    }

    public function patchConfigAuthenticated(ApiTester $I)
    {
        $I->wantTo('check response when making authenticated PATCH request to config');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendPATCH('/config');
        $I->seeResponseCodeIs(405);
    }

    public function optionsConfigUnauthenticated(ApiTester $I)
    {
        $I->wantTo('check response when making unauthenticated OPTIONS request to config');
        $I->sendOPTIONS('/config');
        $I->seeResponseCodeIs(200);
    }

    public function optionsConfigAuthenticated(ApiTester $I)
    {
        $I->wantTo('check response when making authenticated OPTIONS request to config');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendOPTIONS('/config');
        $I->seeResponseCodeIs(200);
    }
}
