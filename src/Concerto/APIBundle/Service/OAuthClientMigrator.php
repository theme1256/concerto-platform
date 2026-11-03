<?php

namespace Concerto\APIBundle\Service;

use Concerto\APIBundle\Command\CreateClientCommand;
use Concerto\APIBundle\Entity\Client;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use League\Bundle\OAuth2ServerBundle\ValueObject\Grant;
use League\Bundle\OAuth2ServerBundle\ValueObject\RedirectUri;
use Psr\Log\LoggerInterface;

/**
 * One-off migration of API clients from FOSOAuthServerBundle to league/oauth2-server-bundle.
 *
 * Existing clients keep working with the credentials shown in the panel: the FOS public id
 * ("<id>_<randomId>") becomes the league client identifier and the secret is copied as is.
 * Issued FOS access/refresh tokens and auth codes are not migrated (league issues signed
 * JWT access tokens); API consumers simply request a new token.
 *
 * Runs from concerto:setup after the schema update and is idempotent: legacy tables are
 * dropped only after all clients were copied, so a re-run picks up where it stopped.
 */
class OAuthClientMigrator
{
    const LEGACY_CLIENT_TABLE = 'Client';
    const LEGACY_CLIENT_COLUMNS = array('id', 'random_id', 'secret', 'redirect_uris', 'allowed_grant_types');
    /** Dropped in this order: token tables reference the client table. */
    const LEGACY_TABLES = array('AuthCode', 'RefreshToken', 'AccessToken', 'Client');

    private $entityManager;
    private $clientManager;
    private $logger;

    public function __construct(EntityManagerInterface $entityManager, ClientManagerInterface $clientManager, LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->clientManager = $clientManager;
        $this->logger = $logger;
    }

    /**
     * @return int|null number of migrated clients, or null when there is nothing to migrate
     */
    public function migrate()
    {
        $connection = $this->entityManager->getConnection();
        if (!$this->hasLegacyClientTable($connection)) {
            return null;
        }

        $migrated = 0;
        $rows = $connection->fetchAllAssociative(
            'SELECT id, random_id, secret, redirect_uris, allowed_grant_types FROM ' . $connection->quoteIdentifier(self::LEGACY_CLIENT_TABLE)
        );
        foreach ($rows as $row) {
            $identifier = $row['id'] . '_' . $row['random_id'];
            if ($this->clientManager->find($identifier) !== null) {
                continue;
            }

            $client = new Client('API client #' . $row['id'] . ' (migrated)', $identifier, $row['secret']);
            $client->setGrants(...array_map(function ($grant) {
                return new Grant(CreateClientCommand::normalizeGrantName($grant));
            }, array_unique(self::unserializeArray($row['allowed_grant_types']))));
            $client->setRedirectUris(...$this->toRedirectUris($identifier, self::unserializeArray($row['redirect_uris'])));
            $this->clientManager->save($client);
            $migrated++;
        }

        $schemaManager = $connection->getSchemaManager();
        foreach (self::LEGACY_TABLES as $table) {
            if ($schemaManager->tablesExist(array($table))) {
                $schemaManager->dropTable($connection->quoteIdentifier($table));
            }
        }

        return $migrated;
    }

    /**
     * Only treat "Client" as the legacy table if it has the FOSOAuthServerBundle columns.
     */
    private function hasLegacyClientTable(Connection $connection)
    {
        $schemaManager = $connection->getSchemaManager();
        if (!$schemaManager->tablesExist(array(self::LEGACY_CLIENT_TABLE))) {
            return false;
        }
        $columns = array_map('strtolower', array_keys($schemaManager->listTableColumns(self::LEGACY_CLIENT_TABLE)));
        return count(array_diff(self::LEGACY_CLIENT_COLUMNS, $columns)) === 0;
    }

    /**
     * Doctrine "array" columns hold PHP-serialized arrays of strings.
     */
    private static function unserializeArray($value)
    {
        if ($value === null || $value === '') {
            return array();
        }
        $array = @unserialize($value, array('allowed_classes' => false));
        return is_array($array) ? array_values(array_filter($array, 'is_string')) : array();
    }

    private function toRedirectUris($identifier, array $uris)
    {
        $result = array();
        foreach ($uris as $uri) {
            try {
                $result[] = new RedirectUri($uri);
            } catch (\RuntimeException $e) {
                $this->logger->warning("OAuth client migration: dropped invalid redirect URI of client $identifier", array('uri' => $uri));
            }
        }
        return $result;
    }
}
