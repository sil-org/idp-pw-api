<?php

namespace tests\codeception\common\_support;

use Codeception\Module;
use tests\helpers\BrokerUtils;
use yii\test\FixtureTrait;

/**
 * This helper is used to populate the test IdBroker instance with needed test
 * data before any tests are run, and to clean up afterwards.
 */
class FixtureHelper extends Module
{
    /**
     * Redeclare visibility because Codeception includes all public methods that
     * do not start with "_" (and are not excluded) in the actor class.
     */
    use FixtureTrait {
        loadFixtures   as protected;
        fixtures       as protected;
        globalFixtures as protected;
        unloadFixtures as protected;
        getFixtures    as protected;
        getFixture     as protected;
    }

    /**
     * Called before all suite tests run.
     * Inserts test users into IdBroker.
     *
     * @param array $settings
     */
    public function _beforeSuite($settings = [])
    {
        BrokerUtils::insertFakeUsers();
    }

    /**
     * Method is called after all suite tests run.
     */
    public function _afterSuite()
    {
        // No local fixtures to unload.
    }
}
