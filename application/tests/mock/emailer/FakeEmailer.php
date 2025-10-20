<?php

namespace tests\mock\emailer;

use common\components\Emailer;

class FakeEmailer extends Emailer
{
    /**
     * @return FakeEmailServiceClient
     */
    protected function getEmailServiceClient()
    {
        if ($this->emailServiceClient === null) {

            $this->emailServiceClient = new FakeEmailServiceClient(
                $this->emailServiceConfig['baseUrl'],
                $this->emailServiceConfig['accessToken'],
                [
                    FakeEmailServiceClient::ASSERT_VALID_BROKER_IP_CONFIG => $this->emailServiceConfig['assertValidIp'],
                    FakeEmailServiceClient::TRUSTED_IPS_CONFIG => $this->emailServiceConfig['validIpRanges'],
                ]
            );
        }

        return $this->emailServiceClient;
    }

    public function forgetFakeEmailsSent()
    {
        return $this->getEmailServiceClient()->emailsSent = [];
    }

    public function getFakeEmailsSent()
    {
        return $this->getEmailServiceClient()->emailsSent;
    }
}
