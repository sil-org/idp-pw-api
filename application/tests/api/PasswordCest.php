<?php

require_once "BaseCest.php";

use common\helpers\Utils;

class PasswordCest extends BaseCest
{
    public function getPasswordWithoutToken(ApiTester $I)
    {
        $I->wantTo('check response when making GET request with no token for obtaining info about password');
        $I->sendGET('/password');
        $I->seeResponseCodeIs(401);
    }

    public function getPasswordWithInvalidToken(ApiTester $I)
    {
        $I->wantTo('check response when making GET request with incorrect token for obtaining info about password');
        $I->setCookie('access_token', 'invalidToken', parent::getCookieConfig());
        $I->sendGET('/password');
        $I->seeResponseCodeIs(401);
    }

    public function postPasswordAuthenticated(ApiTester $I)
    {
        $I->wantTo('check response when making authenticated POST request to /password');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendPOST('/password');
        $I->seeResponseCodeIs(405);
    }

    public function postPasswordUnauthenticated(ApiTester $I)
    {
        $I->wantTo('check response when making unauthenticated POST request to /password');
        $I->setCookie('access_token', 'invalidToken', parent::getCookieConfig());
        $I->sendPOST('/password');
        $I->seeResponseCodeIs(401);
    }

    public function putPasswordAuthenticatedUpdates(ApiTester $I)
    {
        $I->wantTo('check response when making authenticated PUT request to update the password');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendPUT('/password', ['password' => Utils::generateRandomString() . '!12']);
        $I->seeResponseCodeIs(200);
    }

    public function deletePasswordAuthenticated(ApiTester $I)
    {
        $I->wantTo('check response when making authenticated DELETE request to /password');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendDELETE('/password');
        $I->seeResponseCodeIs(405);
    }

    public function getPasswordWithValidToken(ApiTester $I)
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

    public function deletePasswordUnauthenticated(ApiTester $I)
    {
        $I->wantTo('check response when making unauthenticated DELETE request to /password');
        $I->setCookie('access_token', 'invalidToken', parent::getCookieConfig());
        $I->sendDELETE('/password');
        $I->seeResponseCodeIs(401);
    }

    public function patchPasswordUnauthenticated(ApiTester $I)
    {
        $I->wantTo('check response when making unauthenticated PATCH request to /password');
        $I->setCookie('access_token', 'invalidToken', parent::getCookieConfig());
        $I->sendPATCH('/password');
        $I->seeResponseCodeIs(401);
    }

    public function patchPasswordAuthenticated(ApiTester $I)
    {
        $I->wantTo('check response when making authenticated PATCH request to /password');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendPATCH('/password');
        $I->seeResponseCodeIs(405);
    }

    public function optionsPasswordAuthenticated(ApiTester $I)
    {
        $I->wantTo('check response when making authenticated OPTIONS request to /password');
        $I->setCookie('access_token', 'invalidToken', parent::getCookieConfig());
        $I->sendOPTIONS('/password');
        $I->seeResponseCodeIs(200);
    }

    public function putPasswordTooShort(ApiTester $I)
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

    public function putPasswordWeakScoreOne(ApiTester $I)
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

    public function putPasswordAcceptsScoreTwo(ApiTester $I)
    {
        $I->wantTo('check response when changing the password (PUT request) to something that has zxcvbn score of 2');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendPUT('/password', ['password' => Utils::generateRandomString() . '!12']);
        $I->seeResponseCodeIs(200);
    }

    public function putPasswordAcceptsScoreThree(ApiTester $I)
    {
        $I->wantTo('check response when changing the password (PUT request) to something that has zxcvbn score of 3');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendPUT('/password', ['password' => Utils::generateRandomString() . '!12']);
        $I->seeResponseCodeIs(200);
    }

    public function putPasswordTooLong(ApiTester $I)
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

    public function putPasswordContainsFirstName(ApiTester $I)
    {
        $I->wantTo('check response when changing the password (PUT request) to something that contains the first_name');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendPUT('/password', ['password' => 'aFirstz012345']);
        $I->seeResponseCodeIs(400);
        $body = json_decode($I->grabResponse(), true);
        if (substr_count($body['message'], 'code 180') <= 0) {
            throw new \Exception('Expected error code not present in message', 1466798394);
        }
    }

    public function putPasswordContainsLastName(ApiTester $I)
    {
        $I->wantTo('check response when changing the password (PUT request) to something that contains the last_name');
        $I->setCookie('access_token', 'user1', parent::getCookieConfig());
        $I->sendPUT('/password', ['password' => 'aLastz']);
        $I->seeResponseCodeIs(400);
        $body = json_decode($I->grabResponse(), true);
        if (substr_count($body['message'], 'code 180') <= 0) {
            throw new \Exception('Expected error code not present in message', 1466798395);
        }
    }

    public function putPasswordContainsUsername(ApiTester $I)
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

    public function putPasswordContainsEmail(ApiTester $I)
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

    public function putPasswordWithInvalidToken(ApiTester $I)
    {
        $I->wantTo('check response when making PUT request with incorrect token');
        $I->setCookie('access_token', 'invalidToken', parent::getCookieConfig());
        $I->sendPUT('/password', ['password' => 'a_password']);
        $I->seeResponseCodeIs(401);
    }

    public function putPasswordContainingShortFirstName(ApiTester $I)
    {
        $I->wantTo('check response when changing the password (PUT request) to something that contains a short first_name');
        $I->setCookie('access_token', 'user6', parent::getCookieConfig());
        $I->sendPUT('/password', ['password' => 'aUsz56789!']);
        $I->seeResponseCodeIs(200);
    }

    public function putPasswordContainingShortLastName(ApiTester $I)
    {
        $I->wantTo('check response when changing the password (PUT request) to something that contains a short last_name');
        $I->setCookie('access_token', 'user6', parent::getCookieConfig());
        $I->sendPUT('/password', ['password' => 'aSxz56789!']);
        $I->seeResponseCodeIs(200);
    }
}
