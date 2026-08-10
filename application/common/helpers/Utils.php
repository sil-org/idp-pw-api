<?php

namespace common\helpers;

use ReCaptcha\ReCaptcha;
use ReCaptcha\RequestMethod\CurlPost as ReCaptchaCurlPost;
use yii\base\Security;
use yii\helpers\Json;
use yii\web\BadRequestHttpException;
use yii\web\Request;
use yii\web\ServerErrorHttpException;

class Utils
{
    public const DT_FORMAT = 'Y-m-d H:i:s';
    public const FRIENDLY_DT_FORMAT = 'l F j, Y g:iA T';
    public const DT_ISO8601 = 'Y-m-d\TH:i:s\Z';
    public const UID_REGEX = '[a-zA-Z0-9_\-]{32}';

    /**
     * @param integer|string|null $time time as unix timestamp or mysql datetime. If omitted,
     *        the current time is used.
     * @return int
     * @throws \Exception
     */
    protected static function convertToTimestamp($time)
    {
        $time ??= time();
        $time = is_int($time) ? $time : strtotime($time);
        if ($time === false) {
            throw new \Exception('Unable to parse date to timestamp', 1468865840);
        }
        return $time;
    }

    /**
     * @param integer|string|null $time time as unix timestamp or mysql datetime. If omitted,
     *        the current time is used.
     * @return string
     * @throws \Exception
     */
    public static function getDatetime($time = null)
    {
        return date(self::DT_FORMAT, self::convertToTimestamp($time));
    }

    /**
     * @param integer|string|null $time time as unix timestamp or mysql datetime. If omitted,
     *        the current time is used.
     * @return string
     * @throws \Exception
     */
    public static function getIso8601($time = null)
    {
        return date(self::DT_ISO8601, self::convertToTimestamp($time));
    }

    /**
     * @param int $length
     * @return string
     */
    public static function generateRandomString($length = 32)
    {
        $security = new Security();
        return $security->generateRandomString($length);
    }

    /**
     * @return array
     * @throws ServerErrorHttpException
     */
    public static function getFrontendConfig()
    {
        $params = \Yii::$app->params;

        $config = [];

        $config['idpName'] = $params['idpDisplayName'];

        $config['support'] = [];
        foreach ($params['support'] as $supportOption => $value) {
            if (! empty($value)) {
                $config['support'][$supportOption] = $value;
            }
        }

        $config['passwordRules'] = $params['passwordRules'];

        return $config;
    }

    /**
     * Call reCaptcha API to verify response token
     * @param string $verificationToken
     * @param string $ipAddress
     * @return bool
     * @throws \Exception
     * @codeCoverageIgnore
     */
    public static function isRecaptchaResponseValid($verificationToken, $ipAddress)
    {
        $recaptcha = new ReCaptcha(\Yii::$app->params['recaptcha']['secretKey'], new ReCaptchaCurlPost());

        try {
            $response = $recaptcha->verify($verificationToken, $ipAddress);
        } catch (\Exception $e) {
            throw new \Exception('Error attempting to verify recaptcha token: ' . $e->getMessage(), 1666090198);
        }

        if ($response->isSuccess()) {
            return true;
        }

        \Yii::error([
            'action' => __METHOD__,
            'status' => 'error',
            'error' => Json::encode($response->getErrorCodes()),
        ]);
        throw new BadRequestHttpException(\Yii::t('app', 'Utils.RecaptchaVerifyFailure'), 1462904023);
    }

    /**
     * Get Client IP address by looking through headers for proxied requests
     * @param Request $request
     * @return string
     * @codeCoverageIgnore
     */
    public static function getClientIp(Request $request)
    {
        $checkHeaders = [
            'X-Forwarded-For',
            'X-Forwarded',
            'X-Cluster-Client-Ip',
            'Client-Ip',
        ];

        $ipAddress = $request->userIP;

        $requestHeaders = $request->getHeaders();
        foreach ($checkHeaders as $header) {
            if ($requestHeaders->has($header)) {
                $ip = trim(current(explode(',', $requestHeaders->get($header))));
                if (self::isValidIpAddress($ip)) {
                    $ipAddress = $ip;
                    break;
                }
            }
        }

        return $ipAddress;
    }

    /**
     * Check that a given string is a valid IP address
     *
     * @param  string  $ip
     * @return boolean
     */
    public static function isValidIpAddress($ip)
    {
        $flags = FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6;
        return (filter_var($ip, FILTER_VALIDATE_IP, $flags) !== false);
    }

    /**
     * Call Zxcvbn API and return full score object array
     * @param string $password
     * @return array
     * @throws \Exception
     * @codeCoverageIgnore
     */
    public static function getZxcvbnScore($password)
    {
        try {
            $zxcvbn = new \Zxcvbn\Score([
                'description_override' => [
                    'baseUrl' => \Yii::$app->params['zxcvbnApiBaseUrl'],
                ],
            ]);
            return $zxcvbn->getFull(['password' => $password])->toArray();
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Return HMAC SHA256 of access token
     * @param string $accessToken
     * @return string
     */
    public static function getAccessTokenHash($accessToken)
    {
        return hash_hmac('sha256', $accessToken, \Yii::$app->params['accessTokenHashKey']);
    }

    /**
     * Get the request origin, plus the base UI path (/#), if it is trusted.
     * @return string
     */
    public static function getTrustedUiUrl(): string
    {
        $origin = static::getTrustedOrigin();
        return empty($origin) ? '' : $origin . '/#';
    }

    /**
     * Get the origin from the request if it is trusted, otherwise return an empty string.
     * @return string
     */
    public static function getTrustedOrigin(): string
    {
        $origin = \Yii::$app->getRequest()->getOrigin();
        $trustedOrigins = \Yii::$app->params['trustedOrigins'] ?? [];

        if (in_array($origin, $trustedOrigins)) {
            return $origin ?? '';
        }

        return '';
    }
}
