<?php

namespace Tests\Concerto\TestBundle\Controller\FunctionalTests;

use Tests\Concerto\PanelBundle\AFunctionalTest;
use Concerto\PanelBundle\Entity\ATopEntity;
use Concerto\PanelBundle\Entity\TestSession;
use Concerto\PanelBundle\Entity\Test;
use Concerto\PanelBundle\Service\TestSessionService;

class TestRunnerControllerTest extends AFunctionalTest {

    private static $repository;
    private static $testRepository;

    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        self::$repository = static::$entityManager->getRepository(\Concerto\PanelBundle\Entity\TestSession::class);
        self::$testRepository = static::$entityManager->getRepository(\Concerto\PanelBundle\Entity\Test::class);
    }

    protected function setUp(): void {
        parent::setUp();

        $client = self::createLoggedClient();
        $client->request("POST", "/admin/Test/-1/save", array(
            "name" => "test",
            "description" => "description",
            "code" => "print('start')",
            "visibility" => Test::VISIBILITY_REGULAR,
            "type" => Test::TYPE_CODE,
            "accessibility" => ATopEntity::ACCESS_PUBLIC
        ));
        $this->assertTrue($client->getResponse()->isSuccessful());
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals(0, $content["result"]);

        $session = new TestSession();
        $session->setTest(self::$testRepository->find(1));
        $session->setClientIp("192.168.0.100");
        $session->setClientBrowser("Gecko");
        $session->setDebug(false);
        $session->setParams(json_encode(array()));
        $session->setHash(sha1("secret1"));
        $session->setStatus(TestSessionService::STATUS_RUNNING);
        self::$entityManager->persist($session);
        self::$entityManager->flush();
    }

    public function testSubmitNotAuthorizedSession() {
        $client = self::createClient();
        $client->setServerParameter("REMOTE_ADDR", "192.168.0.1");
        $client->request("POST", "/test/session/abc123/submit", array(
            "test_node_hash" => "someHash"
        ));
        $this->assertEquals(403, $client->getResponse()->getStatusCode());
    }

    /**
     * Query and form parameters are passed on to the test as its parameters, including arrays.
     */
    public function testStartTestAcceptsArrayParameters() {
        $client = self::createClient();
        $client->request("GET", "/test/no_such_test");
        $expected = $client->getResponse()->getStatusCode();

        $client->request("GET", "/test/no_such_test?answers[]=a&answers[]=b");
        $this->assertNotEquals(400, $client->getResponse()->getStatusCode());
        $this->assertEquals($expected, $client->getResponse()->getStatusCode());

        $client->request("POST", "/test/no_such_test", array("answers" => array("a", "b")));
        $this->assertNotEquals(400, $client->getResponse()->getStatusCode());
        $this->assertEquals($expected, $client->getResponse()->getStatusCode());
    }

}
