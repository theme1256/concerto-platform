<?php

namespace Concerto\PanelBundle\Controller;

use Doctrine\Common\Collections\ArrayCollection;
use Twig\Environment;
use Symfony\Contracts\Translation\TranslatorInterface;
use Concerto\PanelBundle\Service\TestVariableService;
use Symfony\Component\HttpFoundation\Request;
use Concerto\PanelBundle\Service\TestService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted(new Expression("is_granted('ROLE_TEST') or is_granted('ROLE_SUPER_ADMIN')"))]
class TestVariableController extends ASectionController
{

    const ENTITY_NAME = "TestVariable";

    private $testService;

    public function __construct(Environment $templating, TestVariableService $service, TranslatorInterface $translator, TestService $testService)
    {
        parent::__construct($templating, $service, $translator);

        $this->entityName = self::ENTITY_NAME;
        $this->testService = $testService;
    }

    /**
     * @param $object_id
     * @param string $format
     * @return Response
     */
    #[Route('/TestVariable/fetch/{object_id}/{format}', name: 'TestVariable_object', defaults: ['format' => 'json'])]
    public function objectAction($object_id, $format = "json")
    {
        return parent::objectAction($object_id, $format);
    }

    /**
     * @param $test_id
     * @return Response
     */
    #[Route('/TestVariable/Test/{test_id}/collection', name: 'TestVariable_by_test_collection')]
    public function collectionByTestAction($test_id)
    {
        $collection = $this->service->getAllVariables($test_id);
        return new Response($this->templating->render("@ConcertoPanel/collection.json.twig", array("collection" => $collection)));
    }

    /**
     * @param $test_id
     * @return Response
     */
    #[Route('/TestVariable/Test/{test_id}/parameters/collection', name: 'TestVariable_parameters_collection')]
    public function parametersCollectionAction($test_id)
    {
        $collection = $this->service->getParameters($test_id);
        return new Response($this->templating->render("@ConcertoPanel/collection.json.twig", array("collection" => $collection)));
    }

    /**
     * @param $test_id
     * @return Response
     */
    #[Route('/TestVariable/Test/{test_id}/returns/collection', name: 'TestVariable_returns_collection')]
    public function returnsCollectionAction($test_id)
    {
        $collection = $this->service->getReturns($test_id);
        return new Response($this->templating->render("@ConcertoPanel/collection.json.twig", array("collection" => $collection)));
    }

    /**
     * @param $test_id
     * @return Response
     */
    #[Route('/TestVariable/Test/{test_id}/branches/collection', name: 'TestVariable_branches_collection')]
    public function branchesCollectionAction($test_id)
    {
        $collection = $this->service->getBranches($test_id);
        return new Response($this->templating->render("@ConcertoPanel/collection.json.twig", array("collection" => $collection)));
    }

    /**
     * @param Request $request
     * @param $object_id
     * @return Response
     */
    #[Route('/TestVariable/{object_id}/save', name: 'TestVariable_save', methods: ['POST'])]
    public function saveAction(Request $request, $object_id)
    {
        if (!$this->service->canBeModified($object_id, $request->get("objectTimestamp"), $errorMessages)) {
            $response = new Response(json_encode(array("result" => 1, "errors" => $this->trans($errorMessages))));
            $response->headers->set('Content-Type', 'application/json');
            return $response;
        }

        $result = $this->service->save(
            $object_id,
            $request->get("name"),
            $request->get("type"),
            $request->get("description"),
            $request->get("passableThroughUrl"),
            $request->get("value"),
            $this->testService->get($request->get("test"))
        );
        return $this->getSaveResponse($result);
    }

    /**
     * @param Request $request
     * @param string $object_ids
     * @return Response
     */
    #[Route('/TestVariable/{object_ids}/delete', name: 'TestVariable_delete', methods: ['POST'])]
    public function deleteAction(Request $request, $object_ids)
    {
        return parent::deleteAction($request, $object_ids);
    }
}
