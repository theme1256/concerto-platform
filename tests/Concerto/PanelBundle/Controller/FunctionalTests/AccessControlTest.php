<?php

namespace Tests\Concerto\PanelBundle\Controller\FunctionalTests;

use Concerto\PanelBundle\Entity\Role;
use Concerto\PanelBundle\Entity\User;
use Tests\Concerto\PanelBundle\AFunctionalTest;

/**
 * Contract for the per-section role checks on admin controllers: a user holding only one
 * section role reaches that section and is denied the others.
 */
class AccessControlTest extends AFunctionalTest
{
    const ROLES = array(
        User::ROLE_TEST,
        User::ROLE_TABLE,
        User::ROLE_TEMPLATE,
        User::ROLE_WIZARD,
        User::ROLE_FILE,
        User::ROLE_SUPER_ADMIN,
    );

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $roleRepository = self::$entityManager->getRepository(Role::class);
        foreach (self::ROLES as $roleName) {
            $user = new User();
            $user->setUsername(self::username($roleName));
            $user->setEmail(self::username($roleName) . '@example.com');
            $user->addRole($roleRepository->findOneBy(array('role' => $roleName)));
            $password = self::$passwordHasher->hashPassword($user, 'secret');
            $user->setPassword($password);
            $user->setPasswordConfirmation($password);
            self::$entityManager->persist($user);
        }
        self::$entityManager->flush();
    }

    private static function username($roleName)
    {
        return strtolower(str_replace('ROLE_', '', $roleName)) . '_only';
    }

    public static function accessProvider()
    {
        return array(
            'test section, test role' => array(User::ROLE_TEST, 'GET', '/admin/Test/collection', true),
            'test section, table role' => array(User::ROLE_TABLE, 'GET', '/admin/Test/collection', false),
            'table section, table role' => array(User::ROLE_TABLE, 'GET', '/admin/DataTable/collection', true),
            'table section, test role' => array(User::ROLE_TEST, 'GET', '/admin/DataTable/collection', false),
            'wizard section, wizard role' => array(User::ROLE_WIZARD, 'GET', '/admin/TestWizard/collection', true),
            'wizard section, test role' => array(User::ROLE_TEST, 'GET', '/admin/TestWizard/collection', false),
            'template section, template role' => array(User::ROLE_TEMPLATE, 'GET', '/admin/ViewTemplate/collection', true),
            'template section, test role' => array(User::ROLE_TEST, 'GET', '/admin/ViewTemplate/collection', false),
            'files, file role' => array(User::ROLE_FILE, 'POST', '/admin/file/list', true),
            'files, test role' => array(User::ROLE_TEST, 'POST', '/admin/file/list', false),
            'users, super admin' => array(User::ROLE_SUPER_ADMIN, 'GET', '/admin/User/collection', true),
            'users, test role' => array(User::ROLE_TEST, 'GET', '/admin/User/collection', false),
            'administration, super admin' => array(User::ROLE_SUPER_ADMIN, 'GET', '/admin/Administration/Messages/collection', true),
            'administration, test role' => array(User::ROLE_TEST, 'GET', '/admin/Administration/Messages/collection', false),
        );
    }

    /**
     * @dataProvider accessProvider
     */
    public function testSectionAccess($roleName, $method, $path, $allowed)
    {
        $client = static::createClient();
        $user = static::getContainer()->get('doctrine')->getRepository(User::class)->findOneBy(array('username' => self::username($roleName)));
        $client->loginUser($user, 'admin_area');
        $client->request($method, $path);

        $status = $client->getResponse()->getStatusCode();
        if ($allowed) {
            $this->assertNotSame(403, $status, "$roleName must reach $path");
            $this->assertLessThan(500, $status, "$roleName on $path");
        } else {
            $this->assertSame(403, $status, "$roleName must be denied $path");
        }
    }
}
