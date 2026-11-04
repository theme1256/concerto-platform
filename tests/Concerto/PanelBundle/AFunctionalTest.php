<?php

namespace Tests\Concerto\PanelBundle;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Concerto\PanelBundle\Entity\User;
use Concerto\PanelBundle\Entity\Role;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;

abstract class AFunctionalTest extends WebTestCase {

    protected static $passwordHasher;
    protected static $entityManager;

    /**
     * Symfony 4.4 deprecates (and 5.0 forbids) creating a client while a kernel is booted.
     */
    protected static function createClient(array $options = [], array $server = []): KernelBrowser
    {
        static::ensureKernelShutdown();
        return parent::createClient($options, $server);
    }

    protected function setUp(): void {
        parent::setUp();

        static::truncateClass(\Concerto\PanelBundle\Entity\DataTable::class);
        static::truncateClass(\Concerto\PanelBundle\Entity\Test::class);
        static::truncateClass(\Concerto\PanelBundle\Entity\TestNode::class);
        static::truncateClass(\Concerto\PanelBundle\Entity\TestNodePort::class);
        static::truncateClass(\Concerto\PanelBundle\Entity\TestNodeConnection::class);
        static::truncateClass(\Concerto\PanelBundle\Entity\TestSession::class);
        static::truncateClass(\Concerto\PanelBundle\Entity\TestSessionLog::class);
        static::truncateClass(\Concerto\PanelBundle\Entity\TestWizardParam::class);
        static::truncateClass(\Concerto\PanelBundle\Entity\TestWizardStep::class);
        static::truncateClass(\Concerto\PanelBundle\Entity\TestWizard::class);
        static::truncateClass(\Concerto\PanelBundle\Entity\TestVariable::class);
        static::truncateClass(\Concerto\PanelBundle\Entity\ViewTemplate::class);
    }

    public static function setUpBeforeClass(): void {

        $client = static::createClient();
        self::$passwordHasher = $client->getContainer()->get("test.security.user_password_hasher");
        self::$entityManager = $client->getContainer()->get("doctrine")->getManager();

        $repo = self::$entityManager->getRepository(\Concerto\PanelBundle\Entity\User::class);
        foreach ($repo->findAll() as $user) {
            self::$entityManager->remove($user);
        }
        self::$entityManager->flush();
        static::truncateClass(\Concerto\PanelBundle\Entity\User::class);
        static::truncateClass(\Concerto\PanelBundle\Entity\Role::class);

        $role = null;
        $roles = array(
            User::ROLE_FILE,
            User::ROLE_TABLE,
            User::ROLE_TEMPLATE,
            User::ROLE_TEST,
            User::ROLE_WIZARD,
            User::ROLE_SUPER_ADMIN
        );
        foreach ($roles as $r) {
            $role = new Role();
            $role->setName($r);
            $role->setRole($r);
            self::$entityManager->persist($role);
            self::$entityManager->flush();
        }

        $user = new User();
        $user->setEmail("username@domain.com");
        $user->setUsername("admin");
        $user->addRole($role);
        $password = self::$passwordHasher->hashPassword($user, "admin");
        $passwordConfirmation = self::$passwordHasher->hashPassword($user, "admin");
        $user->setPassword($password);
        $user->setPasswordConfirmation($passwordConfirmation);
        self::$entityManager->persist($user);
        self::$entityManager->flush();
    }

    public static function truncateClass($class) {
        $cmd = self::$entityManager->getClassMetadata($class);
        $connection = self::$entityManager->getConnection();
        $dbPlatform = $connection->getDatabasePlatform();

        $prefix = "";
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $prefix.='ALTER SEQUENCE ' . $cmd->getTableName() . '_id_seq RESTART;';
        }
        if ($dbPlatform instanceof AbstractMySQLPlatform) {
            $prefix.='SET FOREIGN_KEY_CHECKS=0;';
        }

        $q = $dbPlatform->getTruncateTableSQL($cmd->getTableName()) . "";
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $q.= ' CASCADE';
        }
        $q.=";";

        $suffix = "";
        if ($dbPlatform instanceof AbstractMySQLPlatform) {
            $suffix.='SET FOREIGN_KEY_CHECKS=1;';
        }

        // No transaction: TRUNCATE commits implicitly, and PHP 8 PDO throws on the commit() that follows.
        $connection->executeStatement($prefix . $q . $suffix);

        // The deleted rows' entities must leave the identity map: new rows will reuse their ids.
        $identityMap = self::$entityManager->getUnitOfWork()->getIdentityMap();
        foreach ($identityMap[$cmd->rootEntityName] ?? array() as $entity) {
            self::$entityManager->detach($entity);
        }
    }

    /**
     *
     * @return KernelBrowser
     */
    public static function createLoggedClient() {
        $client = static::createClient();
        $client->followRedirects();
        $repo = $client->getContainer()->get("doctrine")->getRepository(\Concerto\PanelBundle\Entity\User::class);
        $user = $repo->findOneBy(array("username" => "admin"));
        $client->loginUser($user, 'admin_area');
        return $client;
    }

}
