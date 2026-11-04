<?php

namespace Tests\Concerto\PanelBundle\Controller\FunctionalTests;

use Concerto\PanelBundle\Entity\User;
use Tests\Concerto\PanelBundle\AFunctionalTest;

/**
 * Contract for the admin form login: failed-login lockout and archived (disabled) accounts.
 */
class LoginSecurityTest extends AFunctionalTest
{
    const USERNAME = 'admin';
    const PASSWORD = 'admin';

    protected function setUp(): void
    {
        parent::setUp();
        self::$entityManager->createQuery(
            'UPDATE Concerto\PanelBundle\Entity\User u SET u.lockedUntil = NULL, u.failedAuthenticationStreak = 0, u.archived = false'
        )->execute();
        self::$entityManager->clear();
    }

    /**
     * Submits the login form and returns the path the firewall redirects to.
     */
    private function login($username, $password)
    {
        $client = static::createClient();
        $client->request('POST', '/admin/login_check', array(
            '_username' => $username,
            '_password' => $password,
        ));
        $response = $client->getResponse();
        $this->assertTrue($response->isRedirect(), 'login_check should redirect, got ' . $response->getStatusCode());

        return parse_url($response->headers->get('Location'), PHP_URL_PATH);
    }

    /**
     * @return User
     */
    private function reloadUser()
    {
        self::$entityManager->clear();
        return self::$entityManager->getRepository(\Concerto\PanelBundle\Entity\User::class)->findOneBy(array('username' => self::USERNAME));
    }

    private function lockStreak()
    {
        static::ensureKernelShutdown();
        static::bootKernel();
        $container = method_exists(static::class, 'getContainer') ? static::getContainer() : self::$container;
        $admin = $container->get(\Concerto\PanelBundle\Service\AdministrationService::class);
        $this->assertTrue($admin->isFailedAuthLockEnabled(), 'failed authentication lock must be enabled in the test environment');
        return (int)$admin->getFailedAuthLockStreak();
    }

    public function testValidCredentialsLogIn()
    {
        $this->assertSame('/admin', $this->login(self::USERNAME, self::PASSWORD));
    }

    public function testWrongPasswordIsRejected()
    {
        $this->assertSame('/login', $this->login(self::USERNAME, 'wrong'));
        $this->assertSame(1, $this->reloadUser()->getFailedAuthenticationStreak());
    }

    public function testRepeatedFailuresLockAccount()
    {
        $streak = $this->lockStreak();
        for ($i = 0; $i < $streak; $i++) {
            $this->assertSame('/login', $this->login(self::USERNAME, 'wrong'));
        }

        $user = $this->reloadUser();
        $this->assertNotNull($user->getLockedUntil());
        $this->assertFalse($user->isAccountNonLocked());

        // correct password is refused while locked
        $this->assertSame('/login', $this->login(self::USERNAME, self::PASSWORD));
    }

    public function testSuccessfulLoginResetsFailureStreak()
    {
        $this->assertSame('/login', $this->login(self::USERNAME, 'wrong'));
        $this->assertSame('/admin', $this->login(self::USERNAME, self::PASSWORD));
        $this->assertSame(0, $this->reloadUser()->getFailedAuthenticationStreak());
    }

    public function testArchivedUserCannotLogIn()
    {
        self::$entityManager->createQuery('UPDATE Concerto\PanelBundle\Entity\User u SET u.archived = true')->execute();

        $this->assertSame('/login', $this->login(self::USERNAME, self::PASSWORD));
    }
}
