<?php

require_once "BaseCest.php";

class MethodCest extends BaseCest
{
    public function test1(ApiTester $I)
    {
        $I->wantTo('check response when making unauthenticated DELETE request to method');
        $I->sendDELETE('/method');
        $I->seeResponseCodeIs(401);
    }

    public function test2(ApiTester $I)
    {
        $I->wantTo('check response when making authenticated DELETE request to method');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendDELETE('/method');
        $I->seeResponseCodeIs(405);
    }

    public function test3(ApiTester $I)
    {
        $I->wantTo('check response when making unauthenticated PATCH request to method');
        $I->sendPATCH('/method');
        $I->seeResponseCodeIs(401);
    }

    public function test4(ApiTester $I)
    {
        $I->wantTo('check response when making authenticated PATCH request to method');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendPATCH('/method');
        $I->seeResponseCodeIs(405);
    }

    public function test5(ApiTester $I)
    {
        $I->wantTo('check response when making unauthenticated GET request for obtaining the'
            . ' methods of a user');
        $I->sendGET('/method');
        $I->seeResponseCodeIs(401);
    }

    public function test6(ApiTester $I, $scenario)
    {
        /**
         * This test may fail if the database is not in its unmodified state.
         * Use `./yii migrate/redo 1` in the broker container to redo the migration.
         */

        $I->wantTo('check response that verified AND unverified methods exist when making authenticated GET'
            . ' request for obtaining the methods of a user');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendGET('/method');
        $I->seeResponseCodeIs(200);

        $I->seeResponseContainsJson([
            'type' => "email",
            'value' => "email-1456769679@domain.org",
        ]);
        $I->seeResponseContainsJson([
            'value' => 'email-1456769721@domain.org',
        ]);
        $I->seeResponseContainsJson([
            'value' => 'email-145676972@domain.org',
        ]);
    }

    public function test62(ApiTester $I)
    {
        $I->wantTo('check response for authenticated GET request to method for a user'
            . ' with auth_type=reset');
        $I->setCookie('access_token', 'user5', parent::getCookieConfig());
        $I->sendGET('/method');
        $I->seeResponseCodeIs(403);
    }

    public function test7(ApiTester $I)
    {
        $I->wantTo('check response when making unauthenticated POST request for creating a new method');
        $I->sendPOST('/method', ['type' => 'email','value' => 'user@domain.com']);
        $I->seeResponseCodeIs(401);
    }

    public function test8(ApiTester $I)
    {
        $I->wantTo('check response when making authenticated POST request for creating a new method');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendPOST('/method', ['type' => 'email','value' => 'user@domain.com']);
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson([
            'type' => "email",
            'value' => "user@domain.com",
        ]);
    }

    public function test82(ApiTester $I, $scenario)
    {
        $I->wantTo('check response when making authenticated POST request for creating an'
            . ' already existing method');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendPOST('/method', ['type' => 'email','value' => 'email-1456769679@domain.org']);

        $I->seeResponseCodeIs(200);
    }

    public function test84(ApiTester $I)
    {
        $I->wantTo('check response for authenticated POST request to method for a user with'
            . ' auth_type=reset');
        $I->setCookie('access_token', 'user5', parent::getCookieConfig());
        $I->sendPOST('/method', ['type' => 'email','value' => 'email@example.com']);
        $I->seeResponseCodeIs(403);
    }

    public function test9(ApiTester $I)
    {
        $I->wantTo('check response when making unauthenticated GET request to obtain a method');
        $I->sendGET('/method/11111111111111111111111111111111');
        $I->seeResponseCodeIs(401);
    }

    public function test10(ApiTester $I, $scenario)
    {
        $I->wantTo('check response when making authenticated GET request to obtain a method');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendGET('/method/22222222222222222222222222222222');

        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson([
            'id' => "22222222222222222222222222222222",
            'value' => "email-1456769679@domain.org",
        ]);
    }

    public function test102(ApiTester $I)
    {
        $I->wantTo('check response for authenticated GET request to method/{uid} for a user'
            . ' with auth_type=reset');
        $I->setCookie('access_token', 'user5', parent::getCookieConfig());
        $I->sendGET('/method/55555555555555555555555555555555');
        $I->seeResponseCodeIs(403);
    }

    public function test11(ApiTester $I)
    {
        $I->wantTo('check response when making authenticated GET request to obtain a method as'
            . ' a non-owner of the method');
        $I->setCookie('access_token', 'user2', parent::getCookieConfig());
        $I->sendGET('/method/11111111111111111111111111111111');
        $I->seeResponseCodeIs(404);
    }

    public function test12(ApiTester $I)
    {
        $I->wantTo('check response when making unauthenticated POST request to method/id');
        $I->sendPOST('/method/11111111111111111111111111111111');
        $I->seeResponseCodeIs(401);
    }

    public function test13(ApiTester $I)
    {
        $I->wantTo('check response when making authenticated POST request method/id');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendPOST('/method/11111111111111111111111111111111');
        $I->seeResponseCodeIs(405);
    }

    public function test14(ApiTester $I)
    {
        $I->wantTo('check response when making a PUT /method/{uid}/verify with no code');
        $I->sendPUT('/method/11111111111111111111111111111111/verify');
        $I->seeResponseCodeIs(400);
    }

    public function test153(ApiTester $I)
    {
        $I->wantTo('check response when making a PUT /method/{uid}/verify with invalid code and'
            . ' expired verification time');
        $I->sendPUT('/method/33333333333333333333333333333333/verify', ['code' => '13245']);
        $I->seeResponseCodeIs(400);
    }

    public function test154(ApiTester $I)
    {
        $I->wantTo('check response when making a PUT /method/{uid}/verify with valid code and'
            . ' expired verification time');
        $I->sendPUT('/method/33333333333333333333333333333333/verify', ['code' => '123456']);
        $I->seeResponseCodeIs(410);
    }

    public function test155(ApiTester $I, $scenario)
    {
        /**
         * This test modifies the database, so is only a valid test the first time through.
         * Use `./yii migrate/redo 1` in the broker container to redo the migration.
         */

        $I->wantTo('check response when making a PUT /method/{uid}/verify with valid code to an'
            . ' unvalidated method');
        $I->sendPUT('/method/44444444444444444444444444444444/verify', ['code' => '444444']);

        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson([
            'id' => "44444444444444444444444444444444",
            'type' => "email",
            'value' => "email-1456769722@domain.org",
        ]);
    }

    public function test157(ApiTester $I, $scenario)
    {
        /**
         * This test modifies the database, and will only pass the first time through.
         * Use `./yii migrate/redo 1` in the broker container to redo the migration.
         */

        $I->wantTo('check response when making multiple unauthenticated PUT requests with invalid'
            . ' code and unexpired verification time');
        $I->sendPUT('/method/33333333333333333333333333333335/verify', ['code' => '13245']);

        $I->seeResponseCodeIs(400);
        $I->sendPUT('/method/33333333333333333333333333333335/verify', ['code' => '13245']);
        $I->seeResponseCodeIs(400);
        $I->sendPUT('/method/33333333333333333333333333333335/verify', ['code' => '13245']);
        $I->seeResponseCodeIs(400);
        $I->sendPUT('/method/33333333333333333333333333333335/verify', ['code' => '13245']);
        $I->seeResponseCodeIs(400);
        $I->sendPUT('/method/33333333333333333333333333333335/verify', ['code' => '13245']);
        $I->seeResponseCodeIs(400);
        $I->sendPUT('/method/33333333333333333333333333333335/verify', ['code' => '13245']);
        $I->seeResponseCodeIs(400);
        $I->sendPUT('/method/33333333333333333333333333333335/verify', ['code' => '13245']);
        $I->seeResponseCodeIs(400);
        $I->sendPUT('/method/33333333333333333333333333333335/verify', ['code' => '13245']);
        $I->seeResponseCodeIs(400);
        $I->sendPUT('/method/33333333333333333333333333333335/verify', ['code' => '13245']);
        $I->seeResponseCodeIs(400);
        $I->sendPUT('/method/33333333333333333333333333333335/verify', ['code' => '13245']);
        $I->seeResponseCodeIs(400);
        $I->sendPUT('/method/33333333333333333333333333333335/verify', ['code' => '13245']);
        $I->seeResponseCodeIs(429);
    }

    public function test16(ApiTester $I)
    {
        $I->wantTo('check response when making unauthenticated DELETE request to method/id');
        $I->sendDELETE('/method/11111111111111111111111111111111');
        $I->seeResponseCodeIs(401);
    }

    public function test17(ApiTester $I, $scenario)
    {
        /**
         * This test modifies the database, so will only pass the first time through.
         * Use `./yii migrate/redo 1` in the broker container to redo the migration.
         */

        $I->wantTo('check response when making authenticated DELETE request to method/id');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendDELETE('/method/33333333333333333333333333333335');

        $I->seeResponseCodeIs(204);
        $I->sendGET('/method/33333333333333333333333333333335');
        $I->seeResponseCodeIs(404);
    }

    public function test172(ApiTester $I, $scenario)
    {
        $I->wantTo('check response when making authenticated DELETE request as a non-owner of'
            . ' the method');
        $I->setCookie('access_token', 'user2', parent::getCookieConfig());

        $I->sendDELETE('/method/11111111111111111111111111111111');
        $I->seeResponseCodeIs(404);
    }

    public function test174(ApiTester $I)
    {
        $I->wantTo('check response for authenticated DELETE request to method/{uid} for a user'
            . ' with auth_type=reset');
        $I->setCookie('access_token', 'user5', parent::getCookieConfig());
        $I->sendDELETE('/method/55555555555555555555555555555555');
        $I->seeResponseCodeIs(403);
    }

    public function test18(ApiTester $I)
    {
        $I->wantTo('check response when making authenticated PATCH request to method/id');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendPATCH('/method/11111111111111111111111111111111');
        $I->seeResponseCodeIs(405);
    }

    public function test19(ApiTester $I)
    {
        $I->wantTo('check response when making authenticated OPTIONS request to method/id');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendOPTIONS('/method/11111111111111111111111111111111');
        $I->seeResponseCodeIs(200);
    }

    public function test20(ApiTester $I)
    {
        $I->wantTo('check response when making PUT request to method/{uid}/resend with incorrect token');
        $I->setCookie('access_token', 'invalidToken', parent::getCookieConfig());
        $I->sendPUT('/method/11111111111111111111111111111111/resend');
        $I->seeResponseCodeIs(401);
    }

    public function test21(ApiTester $I)
    {
        $I->wantTo('check response when making PUT request to method/{uid}/resend for a user'
            . ' with auth_type=reset');
        $I->setCookie('access_token', 'user5', parent::getCookieConfig());
        $I->sendPUT('/method/55555555555555555555555555555555/resend');
        $I->seeResponseCodeIs(403);
    }
}
