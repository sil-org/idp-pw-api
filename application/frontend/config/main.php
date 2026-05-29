<?php

const UID_ROUTE_PATTERN = '<uid:([a-zA-Z0-9_\-]{32})>';

return [
    'id' => 'app-frontend',
    'basePath' => dirname(__DIR__),
    'bootstrap' => [
        'log',
        'errorHandler',
        [
            'class' => 'yii\filters\ContentNegotiator',
            'languages' => [
                'en',
                'fr',
                'es',
                'ko',
            ],
        ],
    ],
    'controllerNamespace' => 'frontend\controllers',
    'components' => [
        'user' => [
            'identityClass' => 'common\models\User',
            'enableAutoLogin' => false,
            'enableSession' => false,
            'loginUrl' => null,
        ],
        'response' => [
            'class' => 'yii\web\Response',
            'on beforeSend' => function ($event) {
                /** @var yii\web\Response $response */
                $response = $event->sender;
                $response->headers->set('Access-Control-Allow-Origin', \Yii::$app->params['uiCorsOrigin']);
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
                $response->headers->set(
                    'Access-Control-Allow-Methods',
                    'GET, POST, PUT, PATCH, DELETE, HEAD, OPTIONS'
                );
                $response->headers->set('Access-Control-Allow-Headers', 'authorization, content-type');
                $response->headers->set('Access-Control-Max-Age', 86400);
            },
        ],
        'log' => [

        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'request' => [
            'enableCookieValidation' => false,
            'enableCsrfValidation' => false,
            'parsers' => [
                'application/json' => 'yii\web\JsonParser',
            ],
        ],
        'urlManager' => [
            'cache' => null,
            'enablePrettyUrl' => true,
            'enableStrictParsing' => true,
            'showScriptName' => false,
            'rules' => [
                /*
                 * Auth routes
                 */
                'GET /auth/login' => 'auth/login',
                'POST /auth/login' => 'auth/login',
                'GET /auth/logout' => 'auth/logout',
                'OPTIONS /auth/logout' => 'auth/options',

                /*
                 * Config routes
                 */
                'GET /config' => 'config/index',
                'OPTIONS /config' => 'config/options',

                /*
                 * Method routes
                 */
                'GET /method'                                      => 'method/index',
                'GET /method/' . UID_ROUTE_PATTERN                 => 'method/view',
                'POST /method'                                     => 'method/create',
                'PUT /method/' . UID_ROUTE_PATTERN . '/verify'     => 'method/verify',
                'PUT /method/' . UID_ROUTE_PATTERN . '/resend'     => 'method/resend',
                'DELETE /method/' . UID_ROUTE_PATTERN              => 'method/delete',
                'OPTIONS /method'                                  => 'method/options',
                'OPTIONS /method/' . UID_ROUTE_PATTERN             => 'method/options',
                'OPTIONS /method/' . UID_ROUTE_PATTERN . '/verify' => 'method/options',
                'OPTIONS /method/' . UID_ROUTE_PATTERN . '/resend' => 'method/options',

                /*
                 * Password routes
                 */
                'GET /password' => 'password/view',
                'PUT /password' => 'password/update',
                'PUT /password/assess' => 'password/assess',
                'OPTIONS /password' => 'password/options',
                'OPTIONS /password/assess' => 'password/options',

                /*
                 * Reset routes
                 */
                'POST /reset' => 'reset/create',
                'PUT /reset/<uuid>/validate' => 'reset/validate',
                'OPTIONS /reset' => 'reset/options',
                'OPTIONS /reset/<uuid>/validate' => 'reset/options',

                /*
                 * User  routes
                 */
                'GET /user/me'     => 'user/me',
                'OPTIONS /user/me' => 'user/options',

                /*
                 * MFA routes
                 */
                'GET /mfa'                                               => 'mfa/index',
                'POST /mfa'                                              => 'mfa/create',
                'PUT /mfa/<mfaId:(\d+)>'                                 => 'mfa/update',
                'PUT /mfa/<mfaId:(\d+)>/webauthn/<webauthnId:(\d+)>'     => 'mfa/update-webauthn',
                'DELETE /mfa/<mfaId:(\d+)>'                              => 'mfa/delete',
                'DELETE /mfa/<mfaId:(\d+)>/webauthn/<webauthnId:(\d+)>'  => 'mfa/delete-webauthn',
                'PUT /mfa/<mfaId:(\d+)>/verify'                          => 'mfa/verify',
                'PUT /mfa/<mfaId:(\d+)>/verify/registration'             => 'mfa/verify-registration',
                'OPTIONS /mfa'                                           => 'mfa/options',
                'OPTIONS /mfa/<mfaId:(\d+)>'                             => 'mfa/options',
                'OPTIONS /mfa/<mfaId:(\d+)>/webauthn/<webauthnId:(\d+)>' => 'mfa/options',
                'OPTIONS /mfa/<mfaId:(\d+)>/verify'                      => 'mfa/options',
                'OPTIONS /mfa/<mfaId:(\d+)>/verify/registration'         => 'mfa/options',

                /*
                 * Status route
                 */
                'GET /site/system-status' => 'site/system-status',

                /*
                 * Catch all to throw 401 or 405
                 */
                '/<url:.*>' => 'site/index',
            ],
        ],
    ],
    'params' => [

    ],
];
