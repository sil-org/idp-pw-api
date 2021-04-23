<?php

use Sil\PhpEnv\Env;

/* Get frontend-specific config settings from ENV vars or set defaults. */
$frontCookieSecure = Env::get('FRONT_COOKIE_SECURE', true);
// default to match docker-compose.yml settings
$memcache1 = Env::getArrayFromPrefix('MEMCACHE_CONFIG1_');
$memcache2 = Env::getArrayFromPrefix('MEMCACHE_CONFIG2_');
$memcacheConfig = [];
if (is_array($memcache1) && ! empty($memcache1)) {
    $memcache1['weight'] = $memcache1['weight'] ?? 100;
    $memcache1['port'] = $memcache1['port'] ?? 11211;
    $memcacheConfig[] = $memcache1;
}
if (is_array($memcache2) && ! empty($memcache2)) {
    $memcache2['weight'] = $memcache2['weight'] ?? 50;
    $memcache2['port'] = $memcache2['port'] ?? 11211;
    $memcacheConfig[] = $memcache2;
}

$sessionLifetime = 1800; // 30 minutes

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
        'sessionCache' => [
            'class' => 'yii\caching\MemCache',
            'servers' => $memcacheConfig,
        ],
        'session' => [
            'class' => 'yii\web\CacheSession',
            'cache' => 'sessionCache',
            'cookieParams' => [// http://us2.php.net/manual/en/function.session-set-cookie-params.php
                'lifetime' => $sessionLifetime,
                'path' => '/',
                'httponly' => true,
                'secure' => $frontCookieSecure,
            ],

        ],
        'response' => [
            'class' => 'yii\web\Response',
            'on beforeSend' => function($event) {
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
            ]
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
                'GET /reset/' . UID_ROUTE_PATTERN => 'reset/view',
                'POST /reset' => 'reset/create',
                'PUT /reset/' . UID_ROUTE_PATTERN => 'reset/update',
                'PUT /reset/' . UID_ROUTE_PATTERN . '/resend' => 'reset/resend',
                'PUT /reset/' . UID_ROUTE_PATTERN . '/validate' => 'reset/validate',
                'OPTIONS /reset' => 'reset/options',
                'OPTIONS /reset/' . UID_ROUTE_PATTERN => 'reset/options',
                'OPTIONS /reset/' . UID_ROUTE_PATTERN . '/resend' => 'reset/options',
                'OPTIONS /reset/' . UID_ROUTE_PATTERN . '/validate' => 'reset/options',

                /*
                 * User  routes
                 */
                'GET /user/me'     => 'user/me',
                'PUT /user/me'     => 'user/update',
                'OPTIONS /user/me' => 'user/options',

                /*
                 * MFA routes
                 */
                'GET /mfa'                          => 'mfa/index',
                'POST /mfa'                         => 'mfa/create',
                'PUT /mfa/<mfaId:(\d+)>'            => 'mfa/update',
                'DELETE /mfa/<mfaId:(\d+)>'         => 'mfa/delete',
                'PUT /mfa/<mfaId:(\d+)>/verify'     => 'mfa/verify',
                'OPTIONS /mfa'                      => 'mfa/options',
                'OPTIONS /mfa/<mfaId:(\d+)>'        => 'mfa/options',
                'OPTIONS /mfa/<mfaId:(\d+)>/verify' => 'mfa/options',

                /*
                 * Status route
                 */
                'GET /site/system-status' => 'site/system-status',

                /*
                 * Catch all to throw 401 or 405
                 */
                '/<url:.*>' => 'site/index',
            ]
        ]
    ],
    'params' => [
        
    ],
];
