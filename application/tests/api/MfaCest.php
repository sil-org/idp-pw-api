<?php

require_once "BaseCest.php";

class MfaCest extends BaseCest
{
    public function getMfaWithInvalidToken(ApiTester $I)
    {
        $I->wantTo('check response when making GET request to /mfa with incorrect token');
        $I->setCookie('access_token', 'invalidToken', parent::getCookieConfig());
        $I->sendGET('/mfa');
        $I->seeResponseCodeIs(401);
    }

    public function getMfaForResetForbidden(ApiTester $I)
    {
        $I->wantTo('check response when making GET request to /mfa for a user'
            . ' with auth_type=reset');
        $I->setCookie('access_token', 'user5', parent::getCookieConfig());
        $I->sendGET('/mfa');
        $I->seeResponseCodeIs(403);
    }

    // TODO: Add test(s) for authorized access to GET /mfa

    public function postMfaWithInvalidToken(ApiTester $I)
    {
        $I->wantTo('check response when making POST request to /mfa with incorrect token');
        $I->setCookie('access_token', 'invalidToken', parent::getCookieConfig());
        $I->sendPOST('/mfa');
        $I->seeResponseCodeIs(401);
    }

    public function postMfaForResetForbidden(ApiTester $I)
    {
        $I->wantTo('check response when making POST request to /mfa for a user'
            . ' with auth_type=reset');
        $I->setCookie('access_token', 'user5', parent::getCookieConfig());
        $I->sendPOST('/mfa');
        $I->seeResponseCodeIs(403);
    }

    // TODO: Add test(s) for authorized access to POST /mfa

    public function putMfaByIdWithInvalidToken(ApiTester $I)
    {
        $I->wantTo('check response when making PUT request to mfa/{id} with incorrect token');
        $I->setCookie('access_token', 'invalidToken', parent::getCookieConfig());
        $I->sendPUT('/mfa/1');
        $I->seeResponseCodeIs(401);
    }

    public function putMfaByIdForResetForbidden(ApiTester $I)
    {
        $I->wantTo('check response when making PUT request to mfa/{id} for a user'
            . ' with auth_type=reset');
        $I->setCookie('access_token', 'user5', parent::getCookieConfig());
        $I->sendPUT('/mfa/5');
        $I->seeResponseCodeIs(403);
    }

    public function putMfaWebauthnWithInvalidToken(ApiTester $I)
    {
        $I->wantTo('check response when making PUT request to mfa/{id}/webauthn/{webauthn_id} with incorrect token');
        $I->setCookie('access_token', 'invalidToken', parent::getCookieConfig());
        $I->sendPUT('/mfa/5/webauthn/6');
        $I->seeResponseCodeIs(401);
    }

    public function putMfaWebauthnForResetForbidden(ApiTester $I)
    {
        $I->wantTo('check response when making PUT request to mfa/{id}/webauthn/{webauthn_id} for a user'
            . ' with auth_type=reset');
        $I->setCookie('access_token', 'user5', parent::getCookieConfig());
        $I->sendPUT('/mfa/5/webauthn/6');
        $I->seeResponseCodeIs(403);
    }

    // TODO: Add test(s) for authorized access to PUT /mfa/{id}

    public function deleteMfaByIdWithInvalidToken(ApiTester $I)
    {
        $I->wantTo('check response when making DELETE request to mfa/{id} with incorrect token');
        $I->setCookie('access_token', 'invalidToken', parent::getCookieConfig());
        $I->sendDELETE('/mfa/1');
        $I->seeResponseCodeIs(401);
    }

    public function deleteMfaByIdForResetForbidden(ApiTester $I)
    {
        $I->wantTo('check response when making DELETE request to mfa/{id} for a user'
            . ' with auth_type=reset');
        $I->setCookie('access_token', 'user5', parent::getCookieConfig());
        $I->sendDELETE('/mfa/5');
        $I->seeResponseCodeIs(403);
    }

    public function deleteMfaWebauthnWithInvalidToken(ApiTester $I)
    {
        $I->wantTo('check response when making DELETE request to mfa/{id}/webauthn/{webauthn_id} with incorrect token');
        $I->setCookie('access_token', 'invalidToken', parent::getCookieConfig());
        $I->sendDELETE('/mfa/5/webauthn/6');
        $I->seeResponseCodeIs(401);
    }

    public function deleteMfaWebauthnForResetForbidden(ApiTester $I)
    {
        $I->wantTo('check response when making DELETE request to mfa/{id}/webauthn/{webauthn_id} for a user'
            . ' with auth_type=reset');
        $I->setCookie('access_token', 'user5', parent::getCookieConfig());
        $I->sendDELETE('/mfa/5/webauthn/6');
        $I->seeResponseCodeIs(403);
    }

    // TODO: Add test(s) for authorized access to DELETE /mfa/{id}

    public function verifyMfaWithInvalidToken(ApiTester $I)
    {
        $I->wantTo('check response when making PUT request to mfa/{id}/verify with incorrect token');
        $I->setCookie('access_token', 'invalidToken', parent::getCookieConfig());
        $I->sendPUT('/mfa/1/verify');
        $I->seeResponseCodeIs(401);
    }

    public function verifyMfaForResetForbidden(ApiTester $I)
    {
        $I->wantTo('check response when making PUT request to mfa/{id}/verify for a user'
            . ' with auth_type=reset');
        $I->setCookie('access_token', 'user5', parent::getCookieConfig());
        $I->sendPUT('/mfa/5/verify');
        $I->seeResponseCodeIs(403);
    }

    public function verifyMfaRegistrationForResetForbidden(ApiTester $I)
    {
        $I->wantTo('check response when making PUT request to mfa/{id}/verify/registration for a user'
            . ' with auth_type=reset');
        $I->setCookie('access_token', 'user5', parent::getCookieConfig());
        $I->sendPUT('/mfa/5/verify/registration');
        $I->seeResponseCodeIs(403);
    }

    // TODO: Add test(s) for authorized access to PUT /mfa/{id}/verify
}
