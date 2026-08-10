<?php

namespace frontend\controllers;

use common\components\auth\AuthnInterface;
use common\components\auth\RedirectException;
use common\components\auth\User as AuthUser;
use common\components\personnel\NotFoundException;
use common\helpers\Utils;
use common\models\User;
use frontend\components\BaseRestController;
use Sil\Idp\IdBroker\Client\ServiceException;
use yii\filters\AccessControl;
use yii\helpers\ArrayHelper;
use yii\web\BadRequestHttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

class AuthController extends BaseRestController
{
    /**
     * Access Control Filter
     * NEEDS TO BE UPDATED FOR EVERY ACTION
     */
    public function behaviors()
    {
        return ArrayHelper::merge(parent::behaviors(), [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                    [
                        'allow' => true,
                        'actions' => ['login', 'logout', 'logout-legacy'],
                        'roles' => ['?'],
                    ],
                ],
            ],
            'authenticator' => [
                'except' => ['login', 'logout', 'logout-legacy'], // bypass authentication for /auth/login and logout
            ],
        ]);
    }

    public function actionLogin()
    {
        if (! \Yii::$app->user->isGuest) {
            return $this->safeUiRedirect(
                $this->getAfterLoginUrl($this->getReturnTo()),
                'You are already signed in. Please return to the application you started from.'
            );
        }

        /*
         * Initialize $log variable for logging
         */
        $log = ['action' => 'login'];

        try {
            try {
                $user = $this->authenticateUser();
            } catch (ServiceException $e) {
                if ($e->httpStatusCode == 410) {
                    $log['status'] = 'info';
                    $log['error'] = 'invite code expired';
                    \Yii::info($log, 'application');

                    return $this->safeUiRedirect(
                        $this->getReturnToOnError(),
                        'Your invitation has expired. Please request a new one from the application you started from.'
                    );
                } else {
                    throw $e;
                }
            }

            /*
             * Create access token and set the HttpOnly cookie on the response
             */
            $user->createAccessToken(User::AUTH_TYPE_LOGIN);
            $loginSuccessUrl = $this->getLoginSuccessRedirectUrl();

            $log['email'] = $user->email;
            $log['status'] = 'success';
            \Yii::warning($log, 'application');

            /*
             * Clear identity before redirecting
             */
            \Yii::$app->user->logout(true);

            /*
             * Redirect to UI
             */
            return $this->safeUiRedirect(
                $loginSuccessUrl,
                'You are signed in. Please return to the application you started from.'
            );

        } catch (RedirectException $e) {
            /*
             * Login triggered redirect to IdP to login, so return a redirect to it
             */
            return $this->redirect($e->getUrl());
        } catch (BadRequestHttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            /*
             * log exception
             */
            $log['status'] = 'error';
            $log['error'] = $e->getMessage();
            $log['code'] = $e->getCode();
            \Yii::error($log, 'application');

            throw new ServerErrorHttpException('server error ' . $e->getCode(), 1546440970);
        }

    }

    public function actionLogout()
    {
        $trustedUiUrl = Utils::getTrustedUiUrl();
        if ($trustedUiUrl === '') {
            /*
             * Because /auth/logout is POST-only, browsers always send Origin.
             * An absent or untrusted Origin means the request did not come from
             * a trusted UI, so we refuse rather than guess a destination.
             */
            \Yii::warning([
                'action' => 'logout',
                'status' => 'rejected',
                'reason' => 'untrusted_origin',
                'origin' => \Yii::$app->request->getOrigin(),
                'ip' => \Yii::$app->request->getUserIP(),
            ], 'application');
            throw new BadRequestHttpException('Untrusted origin');
        }

        $redirectUrl = $this->performLogout($trustedUiUrl);

        return ['redirectUrl' => $redirectUrl];
    }

    /**
     * Legacy GET-based logout endpoint. Preserved for backward compatibility with
     * single-origin deployments where UI_URL / TRUSTED_ORIGINS has exactly one entry.
     *
     * DEPRECATED: Use POST /auth/logout (actionLogout) instead. This endpoint will be
     * removed in a future version.
     *
     * In multi-origin deployments (2+ trusted origins), this endpoint will fail with
     * a 400 or plain-text fallback, since the backend cannot safely determine which
     * UI to redirect to without an Origin header.
     *
     * @deprecated Use POST /auth/logout instead
     */
    public function actionLogoutLegacy()
    {
        $trustedUiUrl = Utils::getTrustedUiUrl();
        if ($trustedUiUrl === '') {
            /*
             * Multi-origin deployment with no usable Origin header.
             * Cannot safely redirect, so render a terminal plain-text page.
             */
            return $this->safeUiRedirect(
                '',
                'You have been signed out. Please close your browser to complete sign-out.'
            );
        }

        $redirectUrl = $this->performLogout($trustedUiUrl);

        return $this->redirect($redirectUrl);
    }

    /**
     * Shared logout logic: clear cookie + token, call IdP SLO, return redirect URL.
     * @param string $trustedUiUrl The trusted UI URL to use as the SLO return target
     * @return string The URL to redirect the browser to (either IdP SLO or UI home)
     */
    protected function performLogout(string $trustedUiUrl): string
    {
        $redirectUrl = $trustedUiUrl;

        $requestCookies = \Yii::$app->request->cookies;
        $accessToken = $requestCookies->getValue('access_token');
        if ($accessToken === null) {
            return $redirectUrl;
        }

        /*
         * Remove access_token cookie from browser
         */
        $responseCookies = \Yii::$app->response->cookies;
        $responseCookies->remove('access_token', true);

        /*
         * Look up user in personnel system
         */
        $accessTokenHash = Utils::getAccessTokenHash($accessToken);
        /** @var \common\components\personnel\PersonnelInterface $personnel */
        $personnel = \Yii::$app->personnel;

        try {
            $personnelUser = $personnel->findByAccessToken($accessTokenHash);
        } catch (\Exception $e) {
            \Yii::error('Failed to find personnel user for logout: ' . $e->getMessage());
            return $redirectUrl;
        }

        /*
         * Clear the token in IdBroker (best-effort)
         */
        try {
            $personnel->clearAccessToken($personnelUser->employeeId);
        } catch (\Exception $e) {
            \Yii::error('Failed to clear access token for logout: ' . $e->getMessage());
        }

        /*
         * Ask the IdP to perform Single Logout. It signals the target
         * URL by throwing RedirectException; we return that URL to the caller.
         */
        $authUser = User::constructFromPersonnelUser($personnelUser)->getAuthUser();
        try {
            /** @var AuthnInterface $auth */
            $auth = \Yii::$app->auth;
            $auth->logout($trustedUiUrl, $authUser);
        } catch (RedirectException $e) {
            $redirectUrl = $e->getUrl();
        }

        return $redirectUrl;
    }

    /**
     * Redirect to $url if it points at the currently trusted UI, otherwise emit
     * a minimal plain-text terminal response from the API's own origin.
     *
     * This is the only safe answer when no trusted origin can be determined:
     * the backend supports multiple UI domains and has no fixed default, so it
     * must not redirect anywhere derived from untrusted input, and it must not
     * echo any request-supplied value back into the response.
     */
    protected function safeUiRedirect(string $url, string $fallbackMessage)
    {
        $trustedUiUrl = Utils::getTrustedUiUrl();
        if ($trustedUiUrl !== '' && $url !== '' && str_starts_with($url, $trustedUiUrl)) {
            return $this->redirect($url);
        }

        \Yii::warning([
            'action' => 'safeUiRedirect',
            'status' => 'blocked',
            'reason' => $trustedUiUrl === '' ? 'no_trusted_origin' : 'target_not_trusted',
            'origin' => \Yii::$app->request->getOrigin(),
            'referrer' => \Yii::$app->request->getReferrer(),
            'ip' => \Yii::$app->request->getUserIP(),
        ], 'application');

        $response = \Yii::$app->response;
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'text/plain; charset=utf-8');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->statusCode = 200;
        $response->content = $fallbackMessage;
        return $response;
    }

    public function getAfterLoginUrl($returnTo)
    {
        $trustedUiUrl = Utils::getTrustedUiUrl();
        /*
         * If $returnTo starts with $trustedUiUrl, return it, else relative build absolute
         */
        if (str_starts_with($returnTo, $trustedUiUrl)) {
            return $returnTo;
        } elseif (str_starts_with($returnTo, '/')) {
            $path = $returnTo;
        } else {
            $path = '';
        }
        return $trustedUiUrl . $path;
    }

    /**
     * Build URL to redirect user to after successful login
     * @return string
     * @throws \Exception
     */
    public function getLoginSuccessRedirectUrl()
    {
        /*
         * Relay state holds the return to path from UI
         */
        $relayState = \Yii::$app->request->post('RelayState', $this->getReturnTo());

        /*
         * build url to redirect user to
         */
        return $this->getAfterLoginUrl($relayState);
    }

    /**
     * @return array|mixed|string
     */
    protected function getReturnTo()
    {
        /*
         * Collect return to url of where to send user after successful login
         * Expected as relative url starting with /
         * Before redirecting user after login this will be prefixed with ui_url
         */
        $returnTo = \Yii::$app->request->get('ReturnTo', '');
        if (str_starts_with($returnTo, '/')) {
            $returnTo = Utils::getTrustedUiUrl() . $returnTo;
        }
        return $returnTo;
    }

    /**
     * Get a return-to url for where to send browser in the event of an error
     * If it's a relative url (starting with '/') it will be prefixed with uiUrl
     */
    protected function getReturnToOnError(): string
    {
        $returnTo = \Yii::$app->request->get('ReturnToOnError', '');
        if (str_starts_with($returnTo, '/')) {
            $returnTo = Utils::getTrustedUiUrl() . $returnTo;
        }
        return $returnTo;
    }

    /**
     * Authenticate User either by an invite code, or by an Auth login call
     *
     * @return User|null
     * @throws NotFoundException
     * @throws RedirectException
     * @throws \common\components\auth\InvalidLoginException
     * @throws ServiceException
     */
    protected function authenticateUser()
    {
        $inviteCode = \Yii::$app->request->get('invite');

        /**
         * @var $user User
         */
        $user = null;

        if (is_string($inviteCode)) {
            $user = User::getUserFromInviteCode($inviteCode);
        }

        if ($user === null) {
            /*
             * If invite code is not recognized, fail over to normal login
             */

            /** @var AuthnInterface $auth */
            $auth = \Yii::$app->auth;
            /** @var AuthUser $authUser */
            $authUser = $auth->login($this->getReturnTo(), \Yii::$app->request);

            /*
             * Get local user instance or create one.
             * Use employeeId since username or email could change.
             */
            $user = User::findOrCreate(null, null, $authUser->employeeId);
        }

        return $user;
    }
}
