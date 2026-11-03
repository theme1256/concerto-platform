<?php

namespace Tests\Concerto\APIBundle\Controller\FunctionalTests;

use Tests\Concerto\PanelBundle\AFunctionalTest;

/**
 * Contract for the OAuth2-protected API: clients created through the admin panel
 * can obtain a client_credentials token and use it on the protected API.
 */
class OAuthApiTest extends AFunctionalTest
{
    const PROTECTED_URL = '/api/data/1';

    /**
     * Creates an API client the same way the admin panel does and returns its credentials.
     *
     * @return array [client_id, client_secret]
     */
    private function createApiClient()
    {
        $client = self::createLoggedClient();
        $client->request('POST', '/admin/Administration/api_clients/clear');
        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $client->request('POST', '/admin/Administration/api_clients/add');
        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $client->request('POST', '/admin/Administration/api_clients/collection');
        $this->assertSame(200, $client->getResponse()->getStatusCode());

        $collection = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(1, $collection);
        $this->assertNotEmpty($collection[0]['fullId']);
        $this->assertNotEmpty($collection[0]['secret']);

        return array($collection[0]['fullId'], $collection[0]['secret']);
    }

    private function requestToken($clientId, $clientSecret)
    {
        $client = static::createClient();
        $client->request('POST', '/oauth/v2/token', array(
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ));

        return $client->getResponse();
    }

    private function fetchAccessToken()
    {
        list($clientId, $clientSecret) = $this->createApiClient();
        $response = $this->requestToken($clientId, $clientSecret);
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());

        $body = json_decode($response->getContent(), true);
        $this->assertNotEmpty($body['access_token']);
        $this->assertSame('bearer', strtolower($body['token_type']));
        $this->assertGreaterThan(0, $body['expires_in']);

        return $body['access_token'];
    }

    public function testTokenEndpointIssuesClientCredentialsToken()
    {
        $this->fetchAccessToken();
    }

    public function testTokenEndpointRejectsWrongSecret()
    {
        list($clientId) = $this->createApiClient();
        $response = $this->requestToken($clientId, 'wrong-secret');

        $this->assertContains($response->getStatusCode(), array(400, 401));
        $body = json_decode($response->getContent(), true);
        $this->assertSame('invalid_client', $body['error']);
    }

    public function testProtectedApiRequiresToken()
    {
        $client = static::createClient();
        $client->request('GET', self::PROTECTED_URL);

        $this->assertSame(401, $client->getResponse()->getStatusCode());
    }

    public function testProtectedApiRejectsInvalidToken()
    {
        $client = static::createClient();
        $client->request('GET', self::PROTECTED_URL, array(), array(), array(
            'HTTP_AUTHORIZATION' => 'Bearer not-a-valid-token',
        ));

        $this->assertSame(401, $client->getResponse()->getStatusCode());
    }

    public function testProtectedApiAcceptsBearerHeader()
    {
        $token = $this->fetchAccessToken();
        $client = static::createClient();
        $client->request('GET', self::PROTECTED_URL, array(), array(), array(
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ));

        $this->assertNotContains($client->getResponse()->getStatusCode(), array(401, 403), $client->getResponse()->getContent());
    }

    public function testProtectedApiAcceptsAccessTokenQueryParameter()
    {
        $token = $this->fetchAccessToken();
        $client = static::createClient();
        $client->request('GET', self::PROTECTED_URL . '?access_token=' . urlencode($token));

        $this->assertNotContains($client->getResponse()->getStatusCode(), array(401, 403), $client->getResponse()->getContent());
    }

    public function testOpenApiAllowsAnonymousAccess()
    {
        $client = static::createClient();
        $client->request('GET', '/api/check/health');

        $this->assertNotContains($client->getResponse()->getStatusCode(), array(401, 403), $client->getResponse()->getContent());
    }
}
