<?php

namespace common\components;

use Sil\Idp\IdBroker\Client\IdBrokerClient;
use yii\base\Component;

class Emailer extends Component
{
    /**
     * The configuration for the email-service client.
     *
     * @var array
     */
    public $emailServiceConfig = [];

    /** @var IdBrokerClient */
    protected $emailServiceClient = null;

    /**
     * Set up various values, using defaults when needed, and ensure the values
     * we end up with are valid.
     */
    public function init()
    {
        $this->assertConfigIsValid();

        parent::init();
    }

    /**
     * Assert that the given configuration values are acceptable.
     *
     * @throws \InvalidArgumentException
     */
    protected function assertConfigIsValid()
    {
        $requiredParams = [
            'accessToken',
            'assertValidIp',
            'baseUrl',
            'validIpRanges',
        ];

        foreach ($requiredParams as $param) {
            if (!isset($this->emailServiceConfig[$param])) {
                throw new \InvalidArgumentException(
                    'Missing email service configuration for ' . $param,
                    1502311757
                );
            }
        }
    }

    /**
     * Use the email service to send an email.
     *
     * @param string $toAddress The recipient's email address.
     * @param string $subject The subject.
     * @param string $htmlBody The email body (as HTML).
     * @param string $textBody The email body (as plain text).
     * @param null|string $ccAddress The cc email address.
     * @throws \Sil\EmailService\Client\EmailServiceClientException
     */
    public function email(
        string $toAddress,
        string $subject,
        string $htmlBody,
        string $textBody,
        string $ccAddress = null
    ) {
        $this->getEmailServiceClient()->email([
            'to_address' => $toAddress,
            'cc_address' => $ccAddress,
            'subject' => $subject,
            'html_body' => $htmlBody,
            'text_body' => $textBody,
        ]);
    }

    /**
     * @return EmailServiceClient
     * @throws \Sil\EmailService\Client\EmailServiceClientException
     */
    protected function getEmailServiceClient()
    {
        if ($this->emailServiceClient === null) {
            $this->emailServiceClient = new EmailServiceClient(
                $this->emailServiceConfig['baseUrl'],
                $this->emailServiceConfig['accessToken'],
                [
                    EmailServiceClient::ASSERT_VALID_IP_CONFIG => $this->emailServiceConfig['assertValidIp'],
                    EmailServiceClient::TRUSTED_IPS_CONFIG => $this->emailServiceConfig['validIpRanges'],
                ]
            );
        }

        return $this->emailServiceClient;
    }

    /**
     * Ping the /site/status URL, and throw an exception if there's a problem.
     *
     * @return string "OK".
     * @throws \Exception
     */
    public function getSiteStatus()
    {
        return $this->getEmailServiceClient()->getSiteStatus();
    }

}
