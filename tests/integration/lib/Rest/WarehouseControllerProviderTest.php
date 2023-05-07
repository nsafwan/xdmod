<?php

namespace IntegrationTests\Rest;

use TestHarness\TokenAuthTest;
use TestHarness\XdmodTestHelper;

class WarehouseControllerProviderTest extends TokenAuthTest
{
    private static $helper;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        self::$helper = new XdmodTestHelper();
    }

    /**
     * The warehouse/aggregate data endpoint uses a json encoded configuration
     * settings. This helper function generates a valid set of parameters for the
     * endpoint.
     */
    protected function getAggDataParameterGenerator($configOverrides = array())
    {
        $config = array(
            'realm' => 'Jobs',
            'group_by' => 'person',
            'statistics' => array('job_count', 'total_cpu_hours'),
            'aggregation_unit' => 'day',
            'start_date' => '2016-12-01',
            'end_date' => '2017-01-01',
            'order_by' => array(
                'field' => 'job_count',
                'dirn' => 'asc'
            )
        );

        $config = array_merge($config, $configOverrides);

        return array(
            'start' => 0,
            'limit' => 10,
            'config' => json_encode($config)
        );
    }


    public function aggregateDataMalformedRequestProvider()
    {
        $inputs = array();

        $inputs[] = array('cd', array('start' => 0, 'limit' => 10));
        $inputs[] = array('cd', array('start' => 0, 'limit' => 10, 'config' => ''));
        $inputs[] = array('cd', array('start' => 0, 'limit' => 10, 'config' => '{"realm": "Jobs"}'));
        $inputs[] = array('cd', array('start' => 0, 'limit' => 10, 'config' => 'not json data'));
        $inputs[] = array('cd', array('start' => 'smart', 'limit' => 'asdf', 'config' => '{"realm": "Jobs"}'));

        return $inputs;
    }

    public function aggregateDataAccessControlsProvider()
    {
        $inputs = array();

        $inputs[] = array('pub', 401, $this->getAggDataParameterGenerator());
        $inputs[] = array('cd', 403, $this->getAggDataParameterGenerator(array('statistics' => array('unavaiablestat'))));
        $inputs[] = array('cd', 403, $this->getAggDataParameterGenerator(array('group_by' => 'turnips')));
        $inputs[] = array('cd', 403, $this->getAggDataParameterGenerator(array('realm' => 'Agricultural')));

        return $inputs;
    }

    /**
     * @dataProvider aggregateDataMalformedRequestProvider
     */
    public function testGetAggregateDataMalformedRequests($user, $params)
    {
        self::$helper->authenticate($user);
        $response = self::$helper->get('rest/warehouse/aggregatedata', $params);

        $this->assertEquals(400, $response[1]['http_code']);
        $this->assertFalse($response[0]['success']);
        self::$helper->logout();
    }

    /**
     *  @dataProvider aggregateDataAccessControlsProvider
     */
    public function testGetAggregateDataAccessControls($user, $http_code, $params)
    {
        if ('pub' !== $user) {
            self::$helper->authenticate($user);
        }
        $response = self::$helper->get('rest/warehouse/aggregatedata', $params);

        $this->assertEquals($http_code, $response[1]['http_code']);
        $this->assertFalse($response[0]['success']);
        self::$helper->logout();
    }

    public function testGetAggregateData()
    {
        //TODO: Needs further integration for other realms.
        if (!in_array("jobs", parent::$XDMOD_REALMS)) {
            $this->markTestSkipped('Needs realm integration.');
        }

        $params = $this->getAggDataParameterGenerator();

        self::$helper->authenticate('cd');
        $response = self::$helper->get('rest/warehouse/aggregatedata', $params);

        $this->assertEquals(200, $response[1]['http_code']);
        $this->assertTrue($response[0]['success']);
        $this->assertCount($params['limit'], $response[0]['results']);
        $this->assertEquals(66, $response[0]['total']);
        self::$helper->logout();
    }

    /**
     * Confirm that a filter can be applied to the aggregatedata endpoint
     */
    public function testGetAggregateWithFilters()
    {
        if (!in_array("jobs", parent::$XDMOD_REALMS)) {
            $this->markTestSkipped('This test requires the Jobs realm');
        }

        $params = $this->getAggDataParameterGenerator(array('filters' => array('jobsize' => 1)));

        self::$helper->authenticate('cd');
        $response = self::$helper->get('rest/warehouse/aggregatedata', $params);

        $this->assertEquals(200, $response[1]['http_code']);
        $this->assertTrue($response[0]['success']);
        $this->assertCount($params['limit'], $response[0]['results']);
        $this->assertEquals(23, $response[0]['total']);
        self::$helper->logout();
    }

    /**
     * @dataProvider provideGetRawData
     */
    public function testGetRawData($role, $tokenType, $testKey)
    {
        parent::runTokenAuthTest(
            $role,
            $tokenType,
            'integration/rest/warehouse',
            'get_raw_data',
            $testKey
        );
    }

    /**
     * dataProvider for testGetRawData.
     */
    public function provideGetRawData()
    {
        // Get the test keys out of the test input artifact file.
        $input = parent::loadJsonTestArtifact(
            'integration/rest/warehouse',
            'get_raw_data',
            'input'
        );
        unset($input['$path']);
        $testKeys = array_keys($input);

        // Build the arrays of test data — one for each role, token type, and
        // test key.
        $testData = [];
        foreach (parent::provideTokenAuthTestData() as $roleAndTokenType) {
            list($role, $tokenType) = $roleAndTokenType;
            if ('valid_token' === $tokenType) {
                foreach ($testKeys as $testKey) {
                    // Only run the non-default valid token test for one
                    // non-public user to make the tests take less time.
                    if (
                        'pub' !== $role
                        && 'usr' !== $role
                        && 'defaults' !== $testKey
                    ) {
                        continue;
                    }
                    $testData[] = [$role, $tokenType, $testKey];
                }
            } else {
                $testData[] = [$role, $tokenType, 'defaults'];
            }
        }
        return $testData;
    }

    /**
     * @dataProvider provideTokenAuthTestData
     */
    public function testGetRawDataLimit($role, $tokenType)
    {
        parent::runTokenAuthTest(
            $role,
            $tokenType,
            'integration/rest/warehouse',
            'get_raw_data_limit'
        );
    }
}
