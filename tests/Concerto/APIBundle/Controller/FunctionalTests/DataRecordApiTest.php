<?php

namespace Tests\Concerto\APIBundle\Controller\FunctionalTests;

use Concerto\PanelBundle\Service\AdministrationService;
use Tests\Concerto\PanelBundle\AFunctionalTest;

/**
 * Data API: inserting and updating records of a data table, and the errors returned for
 * fields that are not columns of the table.
 */
class DataRecordApiTest extends AFunctionalTest
{
    const TABLE = 'api_record_table';

    private $token;

    protected function setUp(): void
    {
        parent::setUp();
        static::$entityManager->getConnection()->executeStatement('DROP TABLE IF EXISTS ' . self::TABLE);

        $client = self::createLoggedClient();
        $client->request('POST', '/admin/DataTable/-1/save', array(
            'name' => self::TABLE,
            'description' => '',
            'accessibility' => 0
        ));
        $this->assertTrue($client->getResponse()->isSuccessful(), $client->getResponse()->getContent());

        static::getContainer()->get(AdministrationService::class)->setSettings(array(
            'api_enabled' => 'true',
            'data_api_enabled' => 'true'
        ), true);

        $client->request('POST', '/admin/Administration/api_clients/clear');
        $client->request('POST', '/admin/Administration/api_clients/add');
        $client->request('POST', '/admin/Administration/api_clients/collection');
        $apiClient = json_decode($client->getResponse()->getContent(), true)[0];

        $client = static::createClient();
        $client->request('POST', '/oauth/v2/token', array(
            'grant_type' => 'client_credentials',
            'client_id' => $apiClient['fullId'],
            'client_secret' => $apiClient['secret'],
        ));
        $this->assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());
        $this->token = json_decode($client->getResponse()->getContent(), true)['access_token'];
    }

    protected function tearDown(): void
    {
        static::$entityManager->getConnection()->executeStatement('DROP TABLE IF EXISTS ' . self::TABLE);
        parent::tearDown();
    }

    private function sendJson($method, $url, $body)
    {
        $client = static::createClient();
        $client->request($method, $url, array(), array(), array(
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
            'CONTENT_TYPE' => 'application/json',
        ), is_string($body) ? $body : json_encode($body));
        return $client->getResponse();
    }

    public function testInsertAndUpdateRecord()
    {
        $response = $this->sendJson('POST', '/api/data/1', array('temp' => 'inserted'));
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $this->assertEquals(array(array('id' => 1, 'temp' => 'inserted')), json_decode($response->getContent(), true));

        $response = $this->sendJson('PUT', '/api/data/1/1', array('temp' => 'updated'));
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $this->assertEquals(array(array('id' => 1, 'temp' => 'updated')), json_decode($response->getContent(), true));
    }

    public function testFieldNamesMustMatchColumnNamesExactly()
    {
        $this->sendJson('POST', '/api/data/1', array('temp' => 'inserted'));

        // the row update ignores keys that are not exact column names, so they must not pass validation
        $response = $this->sendJson('PUT', '/api/data/1/1', array('TEMP' => 'upper'));
        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Incorrect fields: unknown field "TEMP". Expected fields: id, temp', $response->getContent());
    }

    public function testInsertWithUnknownFieldsNamesThemAndTheExpectedFields()
    {
        $response = $this->sendJson('POST', '/api/data/1', array('temp' => 'x', 'tmep' => 'y', 'score' => 1));
        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Incorrect fields: unknown fields "tmep", "score". Expected fields: id, temp', $response->getContent());
        $this->assertSame(0, (int)static::$entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM ' . self::TABLE));
    }

    public function testUpdateWithUnknownFieldNamesItAndTheExpectedFields()
    {
        $this->sendJson('POST', '/api/data/1', array('temp' => 'inserted'));

        $response = $this->sendJson('PUT', '/api/data/1/1', array('score' => 1));
        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Incorrect fields: unknown field "score". Expected fields: id, temp', $response->getContent());
    }

    public function testBodyThatIsNotAJsonObjectIsRejected()
    {
        foreach (array('not json', '["temp"]', '"temp"') as $body) {
            $response = $this->sendJson('POST', '/api/data/1', $body);
            $this->assertSame(400, $response->getStatusCode(), $body);
            $this->assertSame('Incorrect fields: request body must be a JSON object of field names and values. Expected fields: id, temp', $response->getContent(), $body);
        }
    }
}
