<?php

namespace tests\unit\common\components;

use PHPUnit\Framework\TestCase;
use common\components\personnel\IdBroker;
use common\components\personnel\NotFoundException;

class IdBrokerTest extends TestCase
{
    private function getMockReturnValue()
    {
        return [
            'uuid' => '11111111-aaaa-1111-aaaa-111111111111',
            'employee_id' => '11111',
            'first_name' => 'John',
            'last_name' => 'Smith',
            'display_name' => 'John Smith',
            'username' => 'john_smith',
            'email' => 'john_smith@example.com',
            'manager_email' => 'manager@example.com',
            'active' => 'yes',
            'locked' => 'no',
            'last_login_utc' => '2017-07-01T12:30:00Z',
            'hide' => 'no',
        ];
    }

    public function testReturnPersonnelUserFromResponse()
    {
        $mockReturnValue = $this->getMockReturnValue();
        unset($mockReturnValue['email']);
        $brokerMock = $this->getMockComponent('callIdBrokerGetUser', $mockReturnValue);

        $employeeId = '11111';
        $this->expectExceptionCode(1496260921);
        $this->expectExceptionMessage(
            'Personnel attributes missing attribute: email for employeeId='
            . $employeeId
        );
        $brokerMock->findByEmployeeId($employeeId);
    }

    public function testFindByEmployeeId()
    {
        $mockReturnValue = $this->getMockReturnValue();
        $brokerMock = $this->getMockComponent('callIdBrokerGetUser', $mockReturnValue);

        $employeeId = '11111';
        $results = $brokerMock->findByEmployeeId($employeeId);

        $this->assertResultPropertiesMatch($results, $mockReturnValue);
    }

    public function testFindByUsername()
    {
        $mockReturnValue = [ $this->getMockReturnValue() ];
        $brokerMock = $this->getMockComponent('listUsers', $mockReturnValue);

        $username = $mockReturnValue[0]['username'];
        $results = $brokerMock->findByUsername($username);

        $this->assertResultPropertiesMatch($results, $mockReturnValue[0]);
    }

    public function testFindByEmail()
    {
        $mockReturnValue = [ $this->getMockReturnValue() ];
        $brokerMock = $this->getMockComponent('listUsers', $mockReturnValue);

        $email = $mockReturnValue[0]['email'];
        $results = $brokerMock->findByEmail($email);

        $this->assertResultPropertiesMatch($results, $mockReturnValue[0]);
    }

    public function testFindByEmployeeId_MissingUser()
    {
        $brokerMock = $this->getMockComponent('callIdBrokerGetUser', null);

        // Setup
        $employeeId = time();

        $this->expectException(NotFoundException::class);
        $brokerMock->findByEmployeeId($employeeId);
    }

    /**
     * Ensure that users who are not flagged as active are not returned, and
     * thus look like they are simply missing.
     *
     * @throws NotFoundException
     */
    public function testReturnPersonnelUserFromResponse_NotActiveEqualsMissing()
    {
        // Arrange:
        $employeeId = '66666';
        $fakeIdBrokerClientResponse = $this->getMockReturnValue();
        $fakeIdBrokerClientResponse['active'] = 'no';

        // Pre-assert:
        $this->expectException(NotFoundException::class);

        // Act:
        $this->getMockComponent()->returnPersonnelUserFromResponse(
            'employee_id',
            $employeeId,
            $fakeIdBrokerClientResponse
        );
    }

    /**
     * Ensure that receiving user info back that lacks an `active` value causes
     * an exception.
     */
    public function testReturnPersonnelUserFromResponse_ActiveUnknown()
    {
        // Arrange:
        $employeeId = '77777';
        $fakeIdBrokerClientResponse = $this->getMockReturnValue();
        unset($fakeIdBrokerClientResponse['active']);

        // Pre-assert:
        $this->expectException(\Exception::class);

        // Act:
        $this->getMockComponent()->returnPersonnelUserFromResponse(
            'employee_id',
            $employeeId,
            $fakeIdBrokerClientResponse
        );
    }

    protected function assertResultPropertiesMatch($results, $mockReturnValue)
    {
        $properties = [
            'uuid' => $mockReturnValue['uuid'],
            'employeeId' => $mockReturnValue['employee_id'],
            'firstName' => $mockReturnValue['first_name'],
            'lastName' => $mockReturnValue['last_name'],
            'displayName' => $mockReturnValue['display_name'],
            'username' => $mockReturnValue['username'],
            'email' => $mockReturnValue['email'],
            'supervisorEmail' => $mockReturnValue['manager_email'],
            'hide' => $mockReturnValue['hide'],
            'lastLogin' => $mockReturnValue['last_login_utc'],
        ];

        foreach ($properties as $propertyName => $propertyValue) {
            $this->assertEquals($propertyValue, $results->$propertyName, sprintf(
                "Returned property '%s' value '%s' does not match '%s'.",
                $propertyName,
                $results->$propertyName,
                $propertyValue
            ));
        }
    }

    /**
     * @param string $mockedMethod name of method to replace with mocked implementation
     * @param mixed $returnValue return value from mocked method
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    public function getMockComponent($mockedMethod = null, $returnValue = null)
    {
        $brokerMock = $this->getMockBuilder(IdBroker::class)
            ->setMethods(['callIdBrokerGetUser', 'listUsers'])
            ->getMock();

        if (is_string($mockedMethod)) {
            $brokerMock->expects($this->any())
                ->method($mockedMethod)
                ->willReturn($returnValue);
        }

        return $brokerMock;
    }
}
