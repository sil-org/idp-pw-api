<?php

namespace frontend\controllers;

use common\components\auth\RedirectException;
use common\components\auth\User as AuthUser;
use common\components\auth\AuthnInterface;
use common\components\personnel\NotFoundException;
use common\helpers\Utils;
use common\models\User;
use frontend\components\BaseRestController;
use Sil\Idp\IdBroker\Client\ServiceException;
use yii\filters\AccessControl;
use yii\helpers\ArrayHelper;
use yii\web\BadRequestHttpException;
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
                        'actions' => ['login', 'logout'],
                        'roles' => ['?'],
                    ],
                ]
            ],
            'authenticator' => [
                'except' => ['login', 'logout'] // bypass authentication for /auth/login
            ]
        ]);
    }

    public function actionLogin()
    {
        if (! \Yii::$app->user->isGuest) {
            return $this->redirect($this->getAfterLoginUrl($this->getReturnTo()));
        }

        /*
         * Initialize $log variable for logging
         */
        $log = ['action' => 'login'];

        try {
            /*
             * Grab state for use in response after successful login
             */
            $state = $this->getRequestState();

            try {
                $user = $this->authenticateUser();
            } catch (ServiceException $e) {
                if ($e->httpStatusCode == 410) {
                    $log['status'] = 'info';
                    $log['error'] = 'invite code expired';
                    \Yii::info($log, 'application');

                    return $this->redirect($this->getReturnToOnError());
                } else {
                    throw $e;
                }
            }

            $accessToken = $user->createAccessToken(User::AUTH_TYPE_LOGIN);

            \Yii::$app->response->cookies->add(new \yii\web\Cookie([
              'name' => 'access_token',
              'value' => $accessToken,
              'expire' => $user->access_token_expiration,
              'httpOnly' => true, // Ensures the cookie is not accessible via JavaScript
              'secure' => true,   // Ensures the cookie is sent only over HTTPS
              'sameSite' => 'Lax', // Adjust as needed
            ]));
            $loginSuccessUrl = $this->getLoginSuccessRedirectUrl($state, $accessToken, $user->access_token_expiration);

            $log['email'] = $user->email;
            $log['status'] = 'success';
            \Yii::warning($log, 'application');

            /*
             * Kill session
             */
            \Yii::$app->user->logout(true);

            /*
             * Redirect to UI
             */
            return $this->redirect($loginSuccessUrl);

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
        $cookies = \Yii::$app->response->cookies;
        $accessToken = $cookies->getValue('access_token');
        if ($accessToken !== null) {
            /*
             * Clear access_token
             */
            $accessTokenHash = Utils::getAccessTokenHash($accessToken);
            $cookies->remove('access_token');
            $user = User::findOne(['access_token' => $accessTokenHash]);
            if ($user != null) {
                $user->destroyAccessToken();

                /** @var AuthUser $authUser */
                $authUser = $user->getAuthUser();

                /*
                 * Log user out of IdP
                 */
                try {
                    /** @var AuthnInterface $auth */
                    $auth = \Yii::$app->auth;
                    $auth->logout(\Yii::$app->params['uiUrl'], $authUser);
                } catch (RedirectException $e) {
                    return $this->redirect($e->getUrl());
                }
            }
        }

        return $this->redirect(\Yii::$app->params['uiUrl']);
    }

    public function getAfterLoginUrl($returnTo)
    {
        /*
         * If $returnTo starts with UI_URL, return it, else relative build absolute
         */
        if (strpos($returnTo, \Yii::$app->params['uiUrl']) === 0) {
            return $returnTo;
        } elseif (substr($returnTo, 0, 1) == '/') {
            $path = $returnTo;
        } else {
            $path = '';
        }
        return \Yii::$app->params['uiUrl'] . $path;
    }

    /**
     * Get state from request or session and then store in session
     * @return string
     */
    public function getRequestState()
    {
        $state = \Yii::$app->request->get('state');
        if ($state === null) {
            $state = \Yii::$app->session->get('state');
        }
        \Yii::$app->session->set('state', $state);

        return $state;
    }

    /**
     * Build URL to redirect user to after successful login
     * @param string $state
     * @param string $accessToken
     * @param string $tokenExpiration
     * @return string
     * @throws \Exception
     */
    public function getLoginSuccessRedirectUrl($state, $accessToken, $tokenExpiration)
    {
        /*
         * Relay state holds the return to path from UI
         */
        $relayState = \Yii::$app->request->post('RelayState', $this->getReturnTo());

        /*
         * build url to redirect user to
         */
        $afterLogin = $this->getAfterLoginUrl($relayState);

        return $afterLogin;
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
        if (substr($returnTo, 0, 1) == '/') {
            $returnTo = \Yii::$app->params['uiUrl'] . $returnTo;
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
        if (substr($returnTo, 0, 1) == '/') {
            $returnTo = \Yii::$app->params['uiUrl'] . $returnTo;
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
