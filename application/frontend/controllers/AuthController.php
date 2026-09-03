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
                // bypass authentication for /auth/login, /auth/logout, and /auth/logout-legacy
                'except' => ['login', 'logout', 'logout-legacy'],
            ],
        ]);
    }

    /**
     * @throws BadRequestHttpException
     * @throws ServerErrorHttpException
     */
    public function actionLogin(): Response
    {
        if (! \Yii::$app->user->isGuest) {
            return $this->safeUiRedirect($this->getAfterLoginUrl($this->getReturnTo()));
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

                    return $this->safeUiRedirect($this->getReturnToOnError());
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
            return $this->safeUiRedirect($loginSuccessUrl);

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

    /**
     * Shared logout logic: clear cookie + token, call IdP SLO, return redirect URL.
     * @param string $returnTo The trusted UI URL to use as the SLO return target
     * @return string The URL to redirect the browser to (either IdP SLO or UI home)
     */
    protected function performLogout(string $returnTo): string
    {
        $requestCookies = \Yii::$app->request->cookies;
        $accessToken = $requestCookies->getValue('access_token');
        if ($accessToken === null) {
            return $returnTo;
        }

        /*
         * Remove access_token cookie from browser
         */
        $responseCookies = \Yii::$app->response->cookies;
        $responseCookies->remove('access_token', true);

        /*
         * Look up and clear the token in IdBroker, then log out of IdP
         */
        $accessTokenHash = Utils::getAccessTokenHash($accessToken);
        /** @var \common\components\personnel\PersonnelInterface $personnel */
        $personnel = \Yii::$app->personnel;

        try {
            $personnelUser = $personnel->findByAccessToken($accessTokenHash);
        } catch (\Exception $e) {
            \Yii::error('Failed to find personnel user for logout: ' . $e->getMessage());
            return $returnTo;
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
            $auth->logout($returnTo, $authUser);
        } catch (RedirectException $e) {
            $returnTo = $e->getUrl();
        }

        return $returnTo;
    }

    /**
     * Logout endpoint for POST requests. Clears the access token cookie and token in ID Broker,
     * then calls the IdP SLO endpoint and returns a redirect URL to the caller.
     *
     * @return string[]
     * @throws BadRequestHttpException
     */
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
     * removed in a future version. It should not be used if UI_URL is not provided in
     * the environment.
     *
     * @throws ServerErrorHttpException
     * @deprecated Use POST /auth/logout instead
     */
    public function actionLogoutLegacy()
    {
        $trustedUiUrl = Utils::getTrustedUiUrl();

        if ($trustedUiUrl === '') {
            $defaultUiUrl = \Yii::$app->params['uiUrl'] ?? '';

            if ($defaultUiUrl === '') {
                /*
                 * Nothing safe to redirect to. No IdP SLO is attempted because the
                 * IdP would reject a bare or unlisted ReturnTo.
                 */
                throw new ServerErrorHttpException('No safe redirect target available for logout');
            }

            /*
             * Fall back to the default UI URL.
             */
            return $this->redirect($this->performLogout($defaultUiUrl));
        }

        return $this->redirect($this->performLogout($trustedUiUrl));
    }

    /**
     * Redirect to $url if it points at the currently trusted UI.
     * @throws ServerErrorHttpException
     */
    protected function safeUiRedirect(string $url): Response
    {
        if (Utils::isTrustedUrl($url)) {
            return $this->redirect($url);
        }

        \Yii::error([
            'action' => 'safeUiRedirect',
            'status' => 'blocked',
            'reason' => $url === '' ? 'no_url' : 'target_not_trusted',
            'referrer' => \Yii::$app->request->getReferrer(),
        ]);

        throw new ServerErrorHttpException('No safe redirect target available');
    }

    public function getAfterLoginUrl($returnTo)
    {
        /*
         * If $returnTo is already an absolute URL at a trusted origin, return it
         * as-is (this is the case after a SAML round trip, where $returnTo was
         * resolved to an absolute URL before being sent as RelayState).
         * Otherwise treat it as a relative path and build an absolute URL using
         * the trusted origin for the current request.
         */
        if (Utils::isTrustedUrl($returnTo)) {
            return $returnTo;
        }
        $path = str_starts_with($returnTo, '/') ? $returnTo : '';
        return Utils::getTrustedUiUrl() . $path;
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
