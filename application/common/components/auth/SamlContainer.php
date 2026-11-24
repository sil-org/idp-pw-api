<?php

namespace common\components\auth;

use SAML2\Compat\AbstractContainer;

class SamlContainer extends AbstractContainer
{
    public function getLogger(): \Psr\Log\LoggerInterface
    {
        return new \Monolog\Logger('log');
    }

    public function generateId(): string
    {
        return microtime();
    }

    public function debugMessage($message, $type): void
    {
        // No action desired.
    }

    public function redirect($url, $data = []): void
    {
        foreach ($data as $key => $value) {
            if (substr_count($url, '?') > 0) {
                $url .= '&';
            } else {
                $url .= '?';
            }

            $url .= urlencode($key) . '=' . urlencode($value);
        }

        throw new RedirectException($url);
    }

    public function postRedirect($url, $data = []): void
    {
        $this->redirect($url, $data);
    }

    public function getTempDir(): string
    {
        return sys_get_temp_dir();
    }

    public function writeFile(string $filename, string $data, int $mode = null): void
    {
        // No action desired.
    }
    public function getInflateSizeLimit(): int
    {
        return 100 * 1024;
    }
}
