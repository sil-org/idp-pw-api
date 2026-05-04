<?php

namespace common\components\auth;

use RobRichards\XMLSecLibs\XMLSecurityKey;
use SAML2\AuthnRequest;
use SAML2\Compat\ContainerSingleton;
use SAML2\EncryptedAssertion;
use SAML2\HTTPPost;
use SAML2\HTTPRedirect;
use common\components\auth\User as AuthUser;
use SAML2\XML\saml\Issuer;
use yii\base\Component;
use yii\web\Request;

class Saml extends Component implements AuthnInterface
{
    public const TYPE_PUBLIC = 'public';
    public const TYPE_PRIVATE = 'private';

    /**
     * Whether or not to sign request
     * @var bool [default=true]
     */
    public bool $signRequest = true;

    /**
     * Whether or not response should be signed
     * @var bool [default=true]
     */
    public bool $checkResponseSigning = true;

    /**
     * Whether or not to require response assertion to be encrypted
     * @var bool [default=true]
     */
    public bool $requireEncryptedAssertion = true;

    /**
     * Certificate contents for remote IdP
     * @var string
     */
    public string $idpCertificate;

    /**
     * Certificate contents for this SP
     * @var string|null If null, request will not be signed
     */
    public ?string $spCertificate;

    /**
     * PEM encoded private key file associated with $spCertificate
     * @var string|null If null, request will not be signed
     */
    public ?string $spPrivateKey;

    /**
     * This SP Entity ID as known by the remote IdP
     * @var string
     */
    public string $entityId;

    /**
     * Single-Sign-On url for remote IdP
     * @var string
     */
    public string $ssoUrl;

    /**
     * Single-Log-Out url for remote IdP
     * @var string
     */
    public string $sloUrl;

    /**
     * Mapping configuration for IdP attributes to User
     * @var array[]
     */
    public array $attributeMap = [
        'idp_username' => ['field' => 'eduPersonPrincipalName', 'element' => 0],
        'first_name' => ['field' => 'givenName', 'element' => 0],
        'last_name' => ['field' => 'sn', 'element' => 0],
        'email' => ['field' => 'mail', 'element' => 0],
        'employee_id' => ['field' => 'employeeNumber', 'element' => 0],
    ];

    public function init()
    {
        /*
         * Ensure all required properties are set
         */
        $required = [
            'ssoUrl', 'sloUrl', 'attributeMap',
        ];
        foreach ($required as $field) {
            if (is_null($this->$field)) {
                throw new \Exception(
                    'Missing required configuration for ' . $field . ' in auth component configuration',
                    1459883515
                );
            }
        }

        /*
         * Ensure conditionally required properties are set when needed
         */
        if ($this->signRequest && (empty($this->spCertificate) || empty($this->spPrivateKey))) {
            throw new \Exception(
                'Signing requests requires spCertificate and spPrivateKey to be set in auth component configuration',
                1459883965
            );
        }

        if ($this->checkResponseSigning && empty($this->idpCertificate)) {
            throw new \Exception(
                'Checking if responses are signed requires idpCertificate to be set in auth component configuration',
                145988396
            );
        }

        if ($this->requireEncryptedAssertion && empty($this->spPrivateKey)) {
            throw new \Exception(
                'Decrypting assertions requires spPrivateKey to be set in auth component configuration',
                145988397
            );
        }

        // check idpCertificate to see if PEM encoded and if not attempt to do so
        $this->idpCertificate = $this->pemEncodeCertificate($this->idpCertificate, self::TYPE_PUBLIC);

        // check spPrivateKey to see if PEM encoded and if not attempt to do so
        $this->spPrivateKey = $this->pemEncodeCertificate($this->spPrivateKey, self::TYPE_PRIVATE);

        parent::init();
    }

    /**
     * @param string $returnTo Where to have IdP send user after login
     * @param \yii\web\Request|null $request
     * @return \common\components\auth\User
     * @throws \common\components\auth\InvalidLoginException
     * @throws RedirectException
     */
    public function login($returnTo, Request $request = null)
    {
        $container = new SamlContainer();
        ContainerSingleton::setContainer($container);

        $issuer = new Issuer();
        $issuer->setValue($this->entityId);
        $request = new AuthnRequest();
        $request->setId($container->generateId());
        $request->setIssuer($issuer);
        $request->setDestination($this->ssoUrl);
        $request->setRelayState($returnTo);

        /*
         * Sign request if spCertificate and spPrivateKey are provided
         */
        if ($this->signRequest) {
            $key = new XMLSecurityKey(
                XMLSecurityKey::RSA_SHA1,
                ['type' => 'private']
            );
            $key->loadKey($this->spPrivateKey, false);
            $request->setSignatureKey($key);
        }

        try {
            /*
             * Check for SAMLRequest or SAMLResponse to see if user is returning after login
             */
            $binding = new HTTPPost();

            /** @var \SAML2\Response $response */
            $response = $binding->receive();
        } catch (\Exception $e) {
            /*
             * User was not logged in, so redirect to IdP for login
             */
            $binding = new HTTPRedirect();
            $url = $binding->getRedirectURL($request);
            throw new RedirectException($url);
        }

        try {

            /*
             * If needed, check if response is signed
             */
            if ($this->checkResponseSigning) {
                $idpKey = new XMLSecurityKey(
                    XMLSecurityKey::RSA_SHA256,
                    ['type' => 'public']
                );
                $idpKey->loadKey($this->idpCertificate, false, true);
                if (! $response->validate($idpKey)) {
                    throw new \Exception(
                        'SAML response was not signed properly',
                        1459884735
                    );
                }
            }

            /** @var \SAML2\Assertion[]|\SAML2\EncryptedAssertion[] $assertions */
            $assertions = $response->getAssertions();
            /*
             * If requiring encrypted assertion, use key to decrypt it
             */
            if ($this->requireEncryptedAssertion) {
                $decryptKey = new XMLSecurityKey(
                    XMLSecurityKey::RSA_OAEP_MGF1P,
                    ['type' => 'private']
                );
                $decryptKey->loadKey($this->spPrivateKey, false, false);

                if (! $assertions[0] instanceof EncryptedAssertion) {
                    throw new \Exception(
                        'Response assertion is required to be encrypted but was not',
                        1459884392
                    );
                }

                $assertion = $assertions[0]->getAssertion($decryptKey);
            } else {
                $assertion = $assertions[0];
            }

            /*
             * Get attributes using mapping config, make sure expected fields
             * are present, and return as new User
             */
            /** @var \SAML2\Assertion $assertion */
            $samlAttrs = $assertion->getAttributes();
            $normalizedAttrs = $this->extractSamlAttributes($samlAttrs, $this->attributeMap);
            $this->assertHasRequiredSamlAttributes($normalizedAttrs, $this->attributeMap);

            $authUser = new AuthUser();
            $authUser->firstName = $normalizedAttrs['first_name'];
            $authUser->lastName = $normalizedAttrs['last_name'];
            $authUser->email = $normalizedAttrs['email'];
            $authUser->employeeId = $normalizedAttrs['employee_id'];
            $authUser->idpUsername = $normalizedAttrs['idp_username'];

            return $authUser;

        } catch (\Exception $e) {
            /*
             * An error occurred processing SAML data
             */
            throw new InvalidLoginException($e->getMessage(), 1459803743);
        }

    }

    /**
     * @param string $returnTo Where to have IdP send user after logout
     * @param null|\common\components\auth\User $user
     * @throws RedirectException
     */
    public function logout($returnTo, AuthUser $user = null)
    {
        if (substr_count($this->sloUrl, '?') > 0) {
            $joinChar = '&';
        } else {
            $joinChar = '?';
        }

        $url = $this->sloUrl . $joinChar . 'ReturnTo=' . urlencode($returnTo);
        throw new RedirectException($url);
    }

    /**
     * Utility function to extract attribute values from SAML attributes and
     * return as a simple array
     * @param array $attributes the SAML attributes returned
     * @param array $map configuration map of attribute names with field and element values
     * @return array
     */
    public function extractSamlAttributes(array $attributes, array $map): array
    {
        $attrs = [];

        foreach ($map as $attr => $details) {
            if (isset($details['element'])) {
                if (isset($attributes[$details['field']][$details['element']])) {
                    $attrs[$attr] = $attributes[$details['field']][$details['element']];
                }
            } else {
                if (isset($attributes[$details['field']])) {
                    $attrs[$attr] = $attributes[$details['field']];
                }
            }
        }

        return $attrs;
    }

    /**
     * Check if given array of $attributes includes all keys from $map
     * @param array $attributes
     * @param array $map
     * @throws \Exception
     */
    public function assertHasRequiredSamlAttributes(array $attributes, array $map)
    {
        $username = $attributes['idp_username'] ?? 'missing username';
        foreach ($map as $key => $value) {
            if (! array_key_exists($key, $attributes)) {
                throw new \Exception(
                    'SAML attributes missing attribute: ' . $key . ' for user ' . $username,
                    1454436522
                );
            }
        }
    }

    /**
     * Simple solution for PEM encoding cert data
     * @param string $data
     * @param string $type Either self::TYPE_PUBLIC or self::TYPE_PRIVATE
     * @return string
     */
    public function pemEncodeCertificate(?string $data, string $type): string
    {
        if ($data !== null && substr($data, 0, 1) !== '-') {
            $prefix = $type == self::TYPE_PUBLIC ? '-----BEGIN CERTIFICATE-----' : '-----BEGIN PRIVATE KEY-----';
            $suffix = $type == self::TYPE_PUBLIC ? '-----END CERTIFICATE-----' : '-----END PRIVATE KEY-----';

            $data = preg_replace('/\s+/', '', $data);
            $data = $prefix . PHP_EOL
                . chunk_split($data, 64)
                . $suffix . PHP_EOL;
        }

        return $data;
    }
}
