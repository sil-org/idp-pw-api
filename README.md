# idp-pw-api

Backend API for Identity Provider Password Management

## Dev Requirements

### Linux

1. Docker
2. Docker Compose

### Mac

1. Docker for Mac

## Setup

1. Clone this repo
2. Copy ```local.env.dist``` to ```local.env``` and update values as appropriate.
3. Setup environment variable for ```DOCKER_UIDGID``` in the format of ```"uid:gid"```.
   This will run some of the containers as you so that they can write to your host filesystem
   and the file permissions will be owned by you. On Mac (and possibly other *nix-based
   systems), this can be done by running this: `export DOCKER_UIDGID="$(id -u):$(id -g)"`
   (even in your `.bash_profile` file).
4. Setup environment variable for ```COMPOSER_CONFIG_FILE``` with the full system path
   to your composer config.json file, for example: ```/home/my/.composer/config.json```.
   This will allow the composer container to use your github auth token when pulling dependencies.
5. (OPTIONAL) Copy ```application/common/config/local.php.dist``` to ```application/common/config/local.php```
   and update with appropriate settings
6. Follow operating system specific steps below
7. You should be able to access the API using a REST client or your browser
   at http://idp-pw-api.local:51155.
8. You'll probably also want the web interface for this application which you can
   clone at <https://github.com/sil-org/idp-profile-ui>

## Configuration

By default, configuration is read from environment variables. These are documented
in the `local.env.dist` file. Optionally, you can define configuration in AWS Parameter Store.
To do this, set the following environment variables to point to the configuration in AWS:

* `AWS_REGION` - the AWS region in use
* `PARAMETER_STORE_PATH` - Parameter Store base path for this app, e.g. "/idp-pw-api/idp-name/prod"

In addition, the AWS API requires authentication. It is best to use an access role
such as an [ECS Task Role](https://docs.aws.amazon.com/AmazonECS/latest/developerguide/task-iam-roles.html).
If that is not an option, you can specify an access token using the `AWS_ACCESS_KEY_ID` and
`AWS_SECRET_ACCESS_KEY` variables.

If `PARAMETER_STORE_PATH` is given, AWS Parameter Store will be used. Each parameter in AWS Parameter
Store is set as an environment variable in the execution environment.

### Additional setup

1. Add entry to ```/etc/hosts``` for ```127.0.0.1 idp-pw-api.local```
2. Run ```docker build -t idp-pw-api .```
3. Run ```make start```

### Makefile script aliases

To simplify common tasks there is a Makefile in place. The most common tasks will likely be:

- ```make start``` - Does what is needed to get API server online
- ```make test``` - Does cleanup and restart of test instances and runs local (unit and api) and integration tests
- ```make testlocal``` - Does cleanup and restart of test instances and runs just the local tests
- ```make testintegration``` - Runs just the integration tests
- ```make clean``` - Remove all containers
- ```make composerupdate``` - ```make start``` will run a ```composer install```, but to update composer
  you need to run ```make composerupdate```

**Note:** The CI/CD process only runs the local tests now.

## Component Architecture

With the goal of being reusable, this application is developed with a component based architecture that allows swapping
out specific components to suit your needs. All components must implement common interfaces to support this and new
components can be developed to implement the interface as needed.

### Common Interfaces and Classes

The interfaces for the following components are stored within
the [application/common/components](./application/common/components) source tree.

### Configuration

All components must extend
from [\yii\base\Component](http://www.yiiframework.com/doc-2.0/guide-structure-application-components.html) so that they
can be configured in the ```components``` section of the application configuration. This also allows them to be accessed
via ```\Yii::$app->componentId```. While each component has a defined interface for methods to implement, what
properties it needs for configuration are up to each implementation as appropriate.
See [our common/config/local.php.dist](https://github.com/sil-org/idp-pw-api/blob/develop/application/common/config/local.php.dist)
for examples of configurations.

### Authentication Component

We use SAML for authentication but this component can be replaced to support whatever method is needed. For example an
auth component could be written to implement OAuth or use Google, etc.

* Component ID: ```auth```
* Implement interface: ```common\components\auth\AuthnInterface```
* [Example implementation](application/common/components/auth/Saml.php)

### Password Store Component

Passwords are stored in the _Password Store Component_, which is defined by the `PasswordStoreInterface`. Two
implementations are included in this repository: ID Broker and Google. To synchronize multiple Password Store
Components, you can also use the Multiple adapter that allows you to configure more than one destination.

* Component ID: ```passwordstore```
* Implement interface: ```common\components\passwordStore\PasswordStoreInterface```
* [Example implementation](application/common/components/passwordStore/IdBroker.php)

### Personnel Component

The personnel component is used to look up information about users from your company's personnel system. This includes
verifying that they are an active employee, getting information about them like name, email, employee id, whether they
have a supervisor and what their supervisors email address is.

* Component ID: ```personnel```
* Implement interface: ```common\components\personnel\PersonnelInterface```
* [Example implementation](application/common/components/personnel/IdBroker.php)

### passwordStore/Google component

Password store component for IdP PW API that uses Google as the backend.

#### To Use

1. Create a project on <https://console.developers.google.com/>.
2. Still in the Google Developers Console, create a Service Account.
3. Check "Furnish a new private key" and "Enable Google Workspace Domain-wide Delegation".
4. Save the JSON file it provides (containing your private key), but **DO NOT**
   store it in public version control (such as in a public GitHub repo).
5. Enable the "Admin SDK" API for your project in the Google Developers Console.
6. Have an admin for the relevant Google Apps domain go to
   <http://admin.google.com/> and, under Security, Advanced, Manage API Client
   Access, grant your Client ID access to the following scope:  
   `https://www.googleapis.com/auth/admin.directory.user`
7. Set up a delegated admin account in Google Apps, authorized to make changes
   to users. You will use that email address as the value for an env. var.
8. See the `local.env.dist` file to know what environment variables to provide
   when using this component.

#### Example Configuration

    $googlePasswordStore = new GooglePasswordStore([
        
        // Required config fields (dummy values are shown here):
        'applicationName' => 'Name of Your Application',
        'delegatedAdminEmail' => 'some_admin@yourgoogledomain.com',
        
        // You must provide one of these two fields:
        'jsonAuthConfigBase64' => '...', // A base64-encoded string of the JSON
                                         // auth file provided by Google.
        'jsonAuthFilePath' => '/somewhere/in/your/filesystem/google-auth.json',
        
        // Optional config fields (current defaults are shown here):
        'emailFieldName' => 'email',
        'employeeIdFieldName' => 'employee_id',
        'userActiveRecordClass' => '\common\models\User',
        'displayName' => 'Google Workspace',
    ]);

For details about what each of those fields is used for, see the documenting
comments in the `/application/common/components/passwordStore/Google.php` file.

#### Testing the Google PasswordStore component

If running the Google PasswordStore tests (which are integration tests), you
will need to provide credentials in the `local.env file` in the
`TEST_GOOGLE_...` variables for the values described above. See the
`local.env.dist` file for the variable names.

## API Documentation

The API is described by [api.raml](api.raml), and an auto-generated [api.html](api.html) created by
`raml2html`. To regenerate the HTML file, run `make raml2html`.

### Quick start for manually interacting with API

To quickly get up and running to verify basic operation of the API, these are a
few endpoints to start with. GET endpoints can be exercised with any browser,
but others will need something like [Insomnia](http://insomnia.rest).

#### `GET /config`

Returns configuration parameters supplied by environment variables.

#### `GET /site/system-status`

This endpoint verifies connectivity to the database.

#### `POST /reset` and `PUT /reset/{uid}/validate`

This combination requires connection to a PasswordStore component and a Personnel
component containing a valid user record. After sending the `POST`, retrieve the reset
code from the email or the database, and the reset uid from the response body, then
supply them in the `PUT` request body and URI. The response will contain an
`access_token` to use for subsequent calls that require it.

#### `GET /auth/login`

This method of authentication will provide a full-scope `access_token`. The easiest
method is to use an `invite` code, which can be found in the ID Broker database after
creating a new user. The `access_token` can be found in the `Location` response header.

## Test configuration

Tests are configured in multiple places, using different test frameworks.
The chart below summarizes the test configuration.

| Suite   | Framework     | config      | details                                                 |
|---------|---------------|-------------|---------------------------------------------------------|
| Unit    | PHPUnit       | container   | unittest                                                |
|         |               | script      | run-tests.sh                                            | 
|         |               | env.        | common.env, test.env                                    |
|         |               | bootstrap   | tests/_bootstrap.php                                    | 
|         |               | config      | tests/unit.suite.yml, tests/codeception/config/unit.php |
|         |               | coverage    | IdBroker, IdBrokerPw                                    |
| ------- | ------------- | ----------- | ----------------------                                  |
| Unit    | Behat         | container   | unittest                                                |
|         |               | script      | run-tests.sh                                            | 
|         |               | env.        | common.env, test.env                                    |
|         |               | bootstrap   | Composer                                                |
|         |               | config      | features/behat.yml                                      |
|         |               | coverage    | Multiple, Google                                        |
| ------- | ------------- | ----------- | ----------------------                                  |
| Unit    | Codeception   | container   | unittest                                                |
|         |               | script      | run-tests.sh                                            | 
|         |               | env.        | common.env, test.env                                    |
|         |               | bootstrap   | tests/_bootstrap.php                                    | 
|         |               | config      | tests/unit.suite.yml                                    |
|         |               | coverage    | models, helpers                                         |
| ------- | ------------- | ----------- | ----------------------                                  |
| API     | Codeception   | container   | apitest                                                 |
|         |               | script      | run-tests-api.sh                                        | 
|         |               | env.        | common.env, test.env                                    |
|         |               | bootstrap   | tests/_bootstrap.php                                    | 
|         |               | config      | tests/api.suite.yml                                     |
|         |               | coverage    | controllers                                             |
| ------- | ------------- | ----------- | ----------------------                                  |

### Running tests

To run all tests, use `make test`.

To run a single unit test:

```
docker compose run --rm unittest vendor/bin/codecept run tests/unit/common/models/PasswordTest.php:testBadBytes
```
