<?php

namespace Concerto\APIBundle\Controller;

use Concerto\APIBundle\Service\CheckService;
use Concerto\PanelBundle\Service\AdministrationService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/check')]
class ChecksController
{
    private $service;
    private $administrationService;

    public function __construct(AdministrationService $administrationService, CheckService $service)
    {
        $this->administrationService = $administrationService;
        $this->service = $service;
    }

    /**
     * @return Response
     */
    #[Route('/health', methods: ['GET'])]
    public function healthAction()
    {
        if (!$this->administrationService->isApiEnabled())
            return new Response("API disabled", Response::HTTP_FORBIDDEN);


        $healthCheck = $this->service->healthCheck();
        return new Response("", $healthCheck ? 200 : 500);
    }

    /**
     * @return Response
     */
    #[Route('/session_count', methods: ['GET'])]
    public function sessionCountAction()
    {
        if (!$this->administrationService->isApiEnabled())
            return new Response("API disabled", Response::HTTP_FORBIDDEN);

        return new Response($this->service->getSessionCount());
    }

    /**
     * @return Response
     */
    #[Route('/prometheus', methods: ['GET'])]
    public function prometheusAction()
    {
        if (!$this->administrationService->isApiEnabled())
            return new Response("API disabled", Response::HTTP_FORBIDDEN);

        $sessionCount = $this->service->getSessionCount();
        $healthCheck = $this->service->healthCheck() ? 1 : 0;

        $prom = "";
        $prom .= $this->service->promLine("session_count", "counter", $sessionCount, "Estimated session count");
        $prom .= $this->service->promLine("health", "counter", $healthCheck, "Health check");

        return new Response($prom, 200, array(
            "Content-Type" => "text/plain"
        ));
    }
}