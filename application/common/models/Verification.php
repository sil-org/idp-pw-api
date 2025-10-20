<?php

namespace common\models;

use common\components\Emailer;
use yii\base\Model;
use yii\helpers\ArrayHelper;
use yii\web\ServerErrorHttpException;

class Verification extends Model
{
    public const TYPE_EMAIL = 'email';
    public const TYPE_PHONE = 'phone';

    /**
     * Send email for verification
     * @param string $toAddress
     * @param string $subject
     * @param string $view Full view path with alias, ex: @common/mail/reset/self
     * @param string $code
     * @param string $expireTime
     * @param User $forUser
     * @param null|string $ccAddress
     * @param null|integer $eventLogUserId
     * @param null|string $eventLogTopic
     * @param null|string $eventLogDetails
     * @param array $additionalEmailParameters
     * @throws ServerErrorHttpException
     */
    public static function sendEmail(
        $toAddress,
        $subject,
        $view,
        $code,
        $expireTime,
        $forUser,
        $ccAddress = null,
        $eventLogUserId = null,
        $eventLogTopic = null,
        $eventLogDetails = null,
        $additionalEmailParameters = []
    ) {

        $parameters = ArrayHelper::merge(
            [
                'idpDisplayName' => \Yii::$app->params['idpDisplayName'],
                'name' => $forUser->first_name,
                'code' => $code,
                'expireTime' => $expireTime,
                'toAddress' => $toAddress,
                'helpCenterUrl' => \Yii::$app->params['helpCenterUrl'],
                'supportName' => \Yii::$app->params['support']['name'],
                'supportEmail' => \Yii::$app->params['support']['email'],
                'emailSignature' => \Yii::$app->params['emailSignature'],
            ],
            $additionalEmailParameters
        );

        $body = \Yii::$app->view->render(
            $view,
            $parameters
        );

        /* @var $emailer Emailer */
        $emailer = \Yii::$app->emailer;
        $emailer->email($toAddress, $subject, $body, strip_tags($body), $ccAddress);

        if ($eventLogTopic !== null && $eventLogDetails !== null && $eventLogUserId !== null) {
            EventLog::log($eventLogTopic, $eventLogDetails, $eventLogUserId);
        }
    }

    /**
     * Check if user submitted code matches the code emailed to them
     * @param string $code
     * @param string $userProvided
     * @return bool
     */
    public static function isEmailCodeValid($code, $userProvided)
    {
        return strval($code) === strval($userProvided);
    }

}
