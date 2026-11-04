<?php

namespace Concerto\PanelBundle\Controller;

use Twig\Environment;
use Concerto\PanelBundle\Service\TestService;
use Concerto\PanelBundle\Service\TestNodeService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted(new Expression("is_granted('ROLE_TEST') or is_granted('ROLE_SUPER_ADMIN')"))]
class TestNodeController extends ASectionController
{

    const ENTITY_NAME = "TestNode";

    private $testService;

    public function __construct(Environment $templating, TestNodeService $nodeService, TestService $testService, TranslatorInterface $translator)
    {
        parent::__construct($templating, $nodeService, $translator);

        $this->entityName = self::ENTITY_NAME;
        $this->testService = $testService;
    }

    /**
     * @param $object_id
     * @param string $format
     * @return Response
     */
    #[Route('/TestNode/fetch/{object_id}/{format}', name: 'TestNode_object', defaults: ['format' => 'json'])]
    public function objectAction($object_id, $format = "json")
    {
        return parent::objectAction($object_id, $format);
    }

    /**
     * @param string $format
     * @return Response
     */
    #[Route('/TestNode/collection/{format}', name: 'TestNode_collection', defaults: ['format' => 'json'])]
    public function collectionAction($format = "json")
    {
        return parent::collectionAction($format);
    }

    /**
     * @param $test_id
     * @return Response
     */
    #[Route('/TestNode/flow/{test_id}/collection', name: 'TestNode_collection_by_flow_test')]
    public function collectionByFlowTestAction($test_id)
    {
        return new Response($this->templating->render('@ConcertoPanel/collection.json.twig', array(
            'collection' => $this->service->getByFlowTest($test_id)
        )));
    }

    /**
     * @param Request $request
     * @param string $object_ids
     * @return Response
     */
    #[Route('/TestNode/{object_ids}/delete', name: 'TestNode_delete', methods: ['POST'])]
    public function deleteAction(Request $request, $object_ids)
    {
        return parent::deleteAction($request, $object_ids);
    }

    /**
     * @param Request $request
     * @param $object_id
     * @return Response
     */
    #[Route('/TestNode/{object_id}/save', name: 'TestNode_save', methods: ['POST'])]
    public function saveAction(Request $request, $object_id)
    {
        if (!$this->service->canBeModified($object_id, $request->get("objectTimestamp"), $errorMessages)) {
            $response = new Response(json_encode(array("result" => 1, "errors" => $this->trans($errorMessages))));
            $response->headers->set('Content-Type', 'application/json');
            return $response;
        }

        $result = $this->service->save(
            $object_id,
            $request->get("type"),
            $request->get("posX"),
            $request->get("posY"),
            $this->testService->get($request->get("flowTest")),
            $this->testService->get($request->get("sourceTest")),
            $request->get("title"));
        return $this->getSaveResponse($result);
    }

    /**
     * @param Request $request
     * @param $object_id
     * @return Response
     */
    #[Route('/TestNode/{object_id}/ports/expose', name: 'TestNode_expose_ports', methods: ['POST'])]
    public function exposePorts(Request $request, $object_id)
    {
        if (!$this->service->canBeModified($object_id, $request->get("objectTimestamp"), $errorMessages)) {
            $response = new Response(json_encode(array("result" => 1, "errors" => $this->trans($errorMessages))));
            $response->headers->set('Content-Type', 'application/json');
            return $response;
        }

        $this->service->exposePorts(
            json_decode($request->get("exposedPorts"), true)
        );
        $response = new Response(json_encode([
            "result" => 0,
            "objectTimestamp" => time()
        ]));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    /**
     * @param Request $request
     * @param $object_id
     * @param $type
     * @return Response
     */
    #[Route('/TestNode/{object_id}/port/{type}/add', name: 'TestNode_add_dynamic_port', methods: ['POST'])]
    public function addDynamicPort(Request $request, $object_id, $type)
    {
        if (!$this->service->canBeModified($object_id, $request->get("objectTimestamp"), $errorMessages)) {
            $response = new Response(json_encode(array("result" => 1, "errors" => $this->trans($errorMessages))));
            $response->headers->set('Content-Type', 'application/json');
            return $response;
        }

        $result = $this->service->addDynamicPort(
            $object_id,
            $request->get("name"),
            (int)$type
        );

        $errors = $this->trans($result["errors"]);
        $response = new Response(json_encode([
            "result" => count($errors) > 0 ? 1 : 0,
            "object" => $result["object"],
            "errors" => $errors,
            "objectTimestamp" => time()
        ]));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }
}
