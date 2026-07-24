<?php

namespace api;

use ApiTester;
use BaseCest;

require_once "BaseCest.php";

class CorsCest extends BaseCest
{
    public function testTrusted(ApiTester $I)
    {
        $I->wantTo('check response when making a request from a trusted origin');
        $I->haveHttpHeader('Origin', 'http://localhost');
        $I->sendGET('/config');
        $I->seeResponseCodeIs(200);
        $I->seeHttpHeader('Access-Control-Allow-Origin', 'http://localhost');
    }

    public function testUntrusted(ApiTester $I)
    {
        $I->wantTo('check response when making a request from an untrusted origin');
        $I->haveHttpHeader('Origin', 'http://bad');
        $I->sendGET('/config');
        $I->seeResponseCodeIs(200);
        $I->dontSeeHttpHeader('Access-Control-Allow-Origin');
    }
}
