<?php

namespace common\components\personnel;

use Sil\Idp\IdBroker\Client\IdBrokerClient;
use Sil\Idp\IdBroker\Client\ServiceException;
use yii\base\Component;

class IdBroker extends Component implements PersonnelInterface
{
    /**
     * @var IdBrokerClient $client
     */
    private $client;

    /**
     * Initializes the object.
     * This method is invoked at the end of the constructor after the object is initialized with the
     * given configuration.
     * @throws \Exception if a configured baseUrl falls outside the approved IP range
     * @throws \InvalidArgumentException if configuration is incomplete
     */
    public function init()
    {
        parent::init();
        $config = \Yii::$app->params['idBrokerConfig'];
        if (empty($config['baseUrl']) || empty($config['accessToken'])) {
            throw new \Exception('ID_BROKER_baseURL and ID_BROKER_accessToken are required', 1722423241);
        }
        $this->client = new IdBrokerClient(
            $config['baseUrl'],
            $config['accessToken'],
            [
                IdBrokerClient::TRUSTED_IPS_CONFIG => $config['validIpRanges'] ?? [],
                IdBrokerClient::ASSERT_VALID_BROKER_IP_CONFIG => $config['assertValidBrokerIp'] ?? true,
                'http_client_options' => [
                    'timeout' => 10, // An (optional) custom HTTP timeout, in seconds.
                ],
            ]
        );
    }

    /**
     * @param $userData
     * @throws \Exception
     */
    private function assertRequiredAttributesPresent($userData)
    {
        $required = ['uuid', 'first_name', 'last_name', 'email', 'employee_id', 'username'];

        foreach ($required as $requiredAttr) {
            if (! array_key_exists($requiredAttr, $userData)) {
                throw new \Exception(
                    'Personnel attributes missing attribute: ' . $requiredAttr,
                    1496328234
                );
            }
        }
    }

    /**
     * @param string $employeeId
     * @return PersonnelUser
     * @throws NotFoundException
     * @throws ServiceException
     */
    public function findByEmployeeId($employeeId): PersonnelUser
    {
        $results = $this->callIdBrokerGetUser($employeeId);
        if ($results === null) {
            throw new NotFoundException();
        }

        return $this->returnPersonnelUserFromResponse('employeeId', $employeeId, $results);
    }

    /**
     * Get the user attributes for the user with the given Employee ID.
     *
     * @param string $employeeId
     * @return array|null
     * @throws ServiceException
     */
    public function callIdBrokerGetUser($employeeId)
    {

        $idBrokerClient = $this->getIdBrokerClient();

        $results = $idBrokerClient->getUser($employeeId);

        return $results;
    }

    /**
     * Take the given response that came from the IdBrokerClient and return a
     * PersonnelUser representing the response's data.
     *
     * NOTE: Inactive users will be treated as not found.
     *
     * @param string $field The field searched. EXAMPLE: 'employee_id'
     * @param string $value The value searched for. EXAMPLE: '12345'
     * @param $response array|null The response returned by the IdBrokerClient.
     * @return PersonnelUser
     * @throws NotFoundException
     * @throws \Exception
     */
    public function returnPersonnelUserFromResponse($field, $value, $response): PersonnelUser
    {
        $active = $response['active'] ?? null;
        if ($active === null) {
            throw new \Exception(
                sprintf(
                    'No "active" value returned for user: %s',
                    var_export($response, true)
                ),
                1532961386
            );
        } elseif (strtolower($active) !== 'yes') {
            throw new NotFoundException();
        }

        try {
            $this->assertRequiredAttributesPresent($response);
            $pUser = new PersonnelUser();
            $pUser->uuid = $response['uuid'];
            $pUser->firstName = $response['first_name'];
            $pUser->lastName = $response['last_name'];
            $pUser->displayName = $response['display_name'];
            $pUser->email = $response['email'];
            $pUser->employeeId = $response['employee_id'];
            $pUser->username = $response['username'];
            $pUser->supervisorEmail = $response['manager_email'] ?? null;
            $pUser->lastLogin = $response['last_login_utc'];
            $pUser->authType = $response['token_type'] ?? null;

            return $pUser;
        } catch (\Exception $e) {
            throw new \Exception(
                sprintf('%s for %s=%s', $e->getMessage(), $field, $value),
                1496260921
            );
        }
    }

    /**
     * @param string $field
     * @param string $value
     * @return PersonnelUser
     * @throws NotFoundException
     * @throws \Exception
     */
    public function findByField($field, $value): PersonnelUser
    {
        $results = $this->listUsers($field, $value);

        if (count($results) > 1) {
            throw new \Exception(
                sprintf('More than one user found when searching by %s "%s"', $field, $value),
                1497636205
            );
        } elseif (count($results) === 1) {
            if (mb_strtolower($results[0][$field]) == mb_strtolower($value)) {
                return $this->returnPersonnelUserFromResponse($field, $value, $results[0]);
            }
        }

        throw new NotFoundException();
    }

    /**
     * @param string $username
     * @return PersonnelUser
     * @throws NotFoundException
     * @throws \Exception
     */
    public function findByUsername($username): PersonnelUser
    {
        return $this->findByField('username', $username);
    }

    /**
     * @param string $email
     * @return PersonnelUser
     * @throws NotFoundException
     * @throws \Exception
     */
    public function findByEmail($email): PersonnelUser
    {
        return $this->findByField('email', $email);
    }

    /**
     * @return IdBrokerClient
     */
    private function getIdBrokerClient()
    {
        return $this->client;
    }

    /**
     * Updates properties on a personnel record. At a minimum, `$properties` must
     * contain an `'employee_id'` key.
     *
     * @param array $properties
     * @throws NotFoundException
     * @throws ServiceException
     */
    public function updateUser($properties)
    {
        $idBrokerClient = $this->getIdBrokerClient();

        try {
            $idBrokerClient->updateUser($properties);
        } catch (ServiceException $e) {
            if ($e->httpStatusCode == 204) {
                throw new NotFoundException();
            } else {
                throw $e;
            }
        }
    }

    /**
     * @param string $invite
     * @return PersonnelUser
     * @throws NotFoundException
     * @throws ServiceException
     */
    public function findByInvite($invite): PersonnelUser
    {
        $idBrokerClient = $this->getIdBrokerClient();

        $userAttributes = $idBrokerClient->authenticateNewUser($invite);
        if ($userAttributes === null) {
            throw new NotFoundException();
        }

        return $this->returnPersonnelUserFromResponse('invite', '********', $userAttributes);
    }

    /**
     * @param string $field field name to search
     * @param string $value search value
     * @return array raw array of zero or more arrays of user properties
     * @throws ServiceException
     */
    protected function listUsers($field, $value): array
    {
        $idBrokerClient = $this->getIdBrokerClient();

        return $idBrokerClient->listUsers(null, [$field => $value]);
    }

    /**
     * Store an access token for the given user in IdBroker.
     *
     * @param string $employeeId
     * @param string $authType
     * @param string $accessTokenHash
     * @param string $expiration
     * @throws NotFoundException
     * @throws ServiceException
     */
    public function setAccessToken(string $employeeId, string $authType, string $accessTokenHash, string $expiration): void
    {
        $this->updateUser([
            'employee_id' => $employeeId,
            'token_hash' => $accessTokenHash,
            'token_expiry_utc' => $expiration,
            'token_type' => $authType,
        ]);
    }

    /**
     * Clear the access token for the given user in IdBroker.
     *
     * @param string $employeeId
     * @throws ServiceException
     */
    public function clearAccessToken(string $employeeId): void
    {
        $this->updateUser([
            'employee_id' => $employeeId,
            'token_hash' => null,
            'token_expiry_utc' => null,
            'token_type' => null,
        ]);
    }

    /**
     * Find a user by their hashed access token.
     *
     * @param string $accessTokenHash
     * @return PersonnelUser
     * @throws NotFoundException
     * @throws ServiceException
     * @throws \Exception
     */
    public function findByAccessToken(string $accessTokenHash): PersonnelUser
    {
        $results = $this->listUsers('token_hash', $accessTokenHash);

        if (count($results) === 1) {
            return $this->returnPersonnelUserFromResponse('token_hash', '***', $results[0]);
        }

        throw new NotFoundException();
    }
}
