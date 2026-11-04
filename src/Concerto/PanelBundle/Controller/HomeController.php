<?php

namespace Concerto\PanelBundle\Controller;

use Concerto\PanelBundle\Service\AdministrationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Twig\Environment;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    private $templating;
    private $administrationService;

    public function __construct(Environment $templating, AdministrationService $administrationService)
    {
        $this->templating = $templating;
        $this->administrationService = $administrationService;
    }

    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/', name: 'home')]
    public function indexAction(Request $request)
    {
        $test = $this->administrationService->getHomeTest();
        if ($test === null) return new Response("", Response::HTTP_NOT_FOUND);

        $params = [];
        foreach ($request->query->all() as $k => $v) {
            $params[$k] = $v;
        }

        return $this->forward("Concerto\TestBundle\Controller\TestRunnerController::startTestAction", [
            "test_slug" => $test->getSlug()
        ], $params);
    }

}
