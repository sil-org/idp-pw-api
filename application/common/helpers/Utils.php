<?php

namespace common\helpers;

use ReCaptcha\ReCaptcha;
use ReCaptcha\RequestMethod\CurlPost as ReCaptchaCurlPost;
use yii\base\Security;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\validators\EmailValidator;
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
     * @param string $email an email address
     * @return string with most letters changed to asterisks
     * @throws BadRequestHttpException
     */
    public static function maskEmail($email)
    {
        $validator = new EmailValidator();
        if (! $validator->validate($email)) {
            \Yii::warning([
                'action' => 'mask email',
                'status' => 'error',
                'error' => 'Invalid email address provided: ' . Html::encode($email),
            ]);
            throw new BadRequestHttpException(\Yii::t('app', 'Utils.InvalidEmail'), 1461459797);
        }

        [$part1, $domain] = explode('@', $email);
        $newEmail = '';
        $useRealChar = true;

        /*
         * Replace all characters with '*', except
         * the first one, the last one, underscores and each
         * character that follows and underscore.
         */
        foreach (str_split($part1) as $nextChar) {
            if ($useRealChar) {
                $newEmail .= $nextChar;
                $useRealChar = false;
            } elseif ($nextChar === '_') {
                $newEmail .= $nextChar;
                $useRealChar = true;
            } else {
                $newEmail .= '*';
            }
        }

        // replace the last * with the last real character
        $newEmail = substr($newEmail, 0, -1);
        $newEmail .= substr($part1, -1);
        $newEmail .= '@';

        /*
         * Add an '*' for each of the characters of the domain, except
         * for the first character of each part and the .
         */
        $domainParts = explode('.', $domain);
        $countParts = count($domainParts);

        // Leave the last part for later, to avoid adding a '.' after it.
        for ($i = 0; $i < $countParts - 1; $i++) {
            $nextPart = $domainParts[$i];
            $newEmail .= substr($nextPart, 0, 1);
            $newEmail .= str_repeat('*', strlen($nextPart) - 1);
            $newEmail .= '.';
        }

        $nextPart = $domainParts[$countParts - 1];
        $newEmail .= substr($nextPart, 0, 1);
        $newEmail .= str_repeat('*', strlen($nextPart) - 1);

        return $newEmail;
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
}
