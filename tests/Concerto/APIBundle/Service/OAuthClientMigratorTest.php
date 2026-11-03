<?php

namespace Tests\Concerto\APIBundle\Service;

use Concerto\APIBundle\Service\OAuthClientMigrator;
use Tests\Concerto\PanelBundle\AFunctionalTest;

/**
 * Migration of API clients from FOSOAuthServerBundle tables to league/oauth2-server-bundle.
 */
class OAuthClientMigratorTest extends AFunctionalTest
{
    const LEGACY_ID = 7;
    const LEGACY_RANDOM_ID = '4ok3ywl3h6qs0wkg8gw0cgsww0o4cco8wc4oswcwckg4g4wks4';
    const LEGACY_SECRET = '1w7ql2pd4gzoso4osc0gko40c4wsw8w4kg8sko0o4sswg8g00o';

    private function connection()
    {
        return self::$entityManager->getConnection();
    }

    private function createLegacyTables()
    {
        $c = $this->connection();
        $c->executeStatement('DROP TABLE IF EXISTS AccessToken, RefreshToken, AuthCode, Client');
        // Schema as created by FOSOAuthServerBundle 1.6 for Concerto's entities.
        $c->executeStatement('CREATE TABLE Client (id INT AUTO_INCREMENT NOT NULL, random_id VARCHAR(255) NOT NULL, redirect_uris LONGTEXT NOT NULL, secret VARCHAR(255) NOT NULL, allowed_grant_types LONGTEXT NOT NULL, PRIMARY KEY(id)) ENGINE = InnoDB');
        $c->executeStatement('CREATE TABLE AccessToken (id INT AUTO_INCREMENT NOT NULL, client_id INT NOT NULL, user_id INT DEFAULT NULL, token VARCHAR(255) NOT NULL, expires_at INT DEFAULT NULL, scope VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_B39617F55F37A13B (token), PRIMARY KEY(id), CONSTRAINT FK_LEGACY_AT_CLIENT FOREIGN KEY (client_id) REFERENCES Client (id)) ENGINE = InnoDB');
        $c->executeStatement('CREATE TABLE RefreshToken (id INT AUTO_INCREMENT NOT NULL, client_id INT NOT NULL, token VARCHAR(255) NOT NULL, PRIMARY KEY(id), CONSTRAINT FK_LEGACY_RT_CLIENT FOREIGN KEY (client_id) REFERENCES Client (id)) ENGINE = InnoDB');
        $c->executeStatement('CREATE TABLE AuthCode (id INT AUTO_INCREMENT NOT NULL, client_id INT NOT NULL, token VARCHAR(255) NOT NULL, PRIMARY KEY(id), CONSTRAINT FK_LEGACY_AC_CLIENT FOREIGN KEY (client_id) REFERENCES Client (id)) ENGINE = InnoDB');

        $c->insert('Client', array(
            'id' => self::LEGACY_ID,
            'random_id' => self::LEGACY_RANDOM_ID,
            'redirect_uris' => serialize(array('https://example.org/callback', 'not a uri')),
            'secret' => self::LEGACY_SECRET,
            'allowed_grant_types' => serialize(array('token', 'client_credentials')),
        ));
        $c->insert('AccessToken', array('client_id' => self::LEGACY_ID, 'token' => 'legacy-token'));
    }

    private function migrator()
    {
        static::ensureKernelShutdown();
        static::bootKernel();
        return static::getContainer()->get(OAuthClientMigrator::class);
    }

    protected function tearDown(): void
    {
        $this->connection()->executeStatement('DROP TABLE IF EXISTS AccessToken, RefreshToken, AuthCode, Client');
        $this->connection()->executeStatement('DELETE FROM oauth2_client');
        parent::tearDown();
    }

    public function testNothingToMigrateWithoutLegacyTables()
    {
        $this->connection()->executeStatement('DROP TABLE IF EXISTS AccessToken, RefreshToken, AuthCode, Client');
        $this->assertNull($this->migrator()->migrate());
    }

    public function testMigratedClientKeepsCredentialsAndLegacyTablesAreDropped()
    {
        $this->createLegacyTables();

        $this->assertSame(1, $this->migrator()->migrate());

        $schemaManager = $this->connection()->getSchemaManager();
        foreach (array('Client', 'AccessToken', 'RefreshToken', 'AuthCode') as $table) {
            $this->assertFalse($schemaManager->tablesExist(array($table)), "$table should be dropped");
        }

        $client = static::createClient();
        $client->request('POST', '/oauth/v2/token', array(
            'grant_type' => 'client_credentials',
            'client_id' => self::LEGACY_ID . '_' . self::LEGACY_RANDOM_ID,
            'client_secret' => self::LEGACY_SECRET,
        ));
        $this->assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());
        $this->assertNotEmpty(json_decode($client->getResponse()->getContent(), true)['access_token']);
    }

    public function testMigrationIsIdempotent()
    {
        $this->createLegacyTables();
        $this->assertSame(1, $this->migrator()->migrate());
        $this->assertNull($this->migrator()->migrate());

        $this->createLegacyTables();
        $this->assertSame(0, $this->migrator()->migrate(), 'already migrated clients are not duplicated');
    }
}
