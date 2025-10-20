<?php

namespace tests\mock\emailer;

use Sil\Idp\IdBroker\Client\IdBrokerClient;

class FakeEmailServiceClient extends IdBrokerClient
{
    public $emailsSent = [];

    public function email(array $config = [])
    {
        $this->emailsSent[] = $config;
        return $config;
    }

    /**
     * Ping the /site/status URL, and throw an exception if there's a problem.
     *
     * @return string "OK".
     */
    public function getSiteStatus(): string
    {
        return 'OK';
    }
}
