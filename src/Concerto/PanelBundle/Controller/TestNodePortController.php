<?php

namespace Concerto\PanelBundle\Controller;

use Concerto\PanelBundle\Utils\RequestParameters;
use Twig\Environment;
use Concerto\PanelBundle\Service\TestNodeService;
use Concerto\PanelBundle\Service\TestNodePortService;
use Concerto\PanelBundle\Service\TestVariableService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted(new Expression("is_granted('ROLE_TEST') or is_granted('ROLE_SUPER_ADMIN')"))]
class TestNodePortController extends ASectionController
{

    const ENTITY_NAME = "TestNodePort";

    private $testNodeService;
    private $testVariableService;

    public function __construct(Environment $templating, TestNodePortService $portService, TestNodeService $nodeService, TestVariableService $variableService, TranslatorInterface $translator)
    {
        parent::__construct($templating, $portService, $translator);

        $this->entityName = self::ENTITY_NAME;

        $this->testNodeService = $nodeService;
        $this->testVariableService = $variableService;
    }

    /**
     * @param $object_id
     * @param string $format
     * @return Response
     */
    #[Route('/TestNodePort/fetch/{object_id}/{format}', name: 'TestNodePort_object', defaults: ['format' => 'json'])]
    public function objectAction($object_id, $format = "json")
    {
        return parent::objectAction($object_id, $format);
    }

    /**
     * @param string $format
     * @return Response
     */
    #[Route('/TestNodePort/collection/{format}', name: 'TestNodePort_collection', defaults: ['format' => 'json'])]
    public function collectionAction($format = "json")
    {
        return parent::collectionAction($format);
    }

    /**
     * @param Request $request
     * @param string $object_ids
     * @return Response
     */
    #[Route('/TestNodePort/{object_ids}/delete', name: 'TestNodePort_delete', methods: ['POST'])]
    public function deleteAction(Request $request, $object_ids)
    {
        return parent::deleteAction($request, $object_ids);
    }

    /**
     * @param Request $request
     * @param $object_id
     * @return Response
     */
    #[Route('/TestNodePort/{object_id}/save', name: 'TestNodePort_save', methods: ['POST'])]
    public function saveAction(Request $request, $object_id)
    {
        if (!$this->service->canBeModified($object_id, RequestParameters::get($request, "objectTimestamp"), $errorMessages)) {
            $response = new Response(json_encode(array("result" => 1, "errors" => $this->trans($errorMessages))));
            $response->headers->set('Content-Type', 'application/json');
            return $response;
        }

        $node = $this->testNodeService->get(RequestParameters::get($request, "node"));
        $variable = $this->testVariableService->get(RequestParameters::get($request, "variable"));
        $default = RequestParameters::get($request, "default") === "1";
        $value = RequestParameters::get($request, "value");
        $string = RequestParameters::get($request, "string") === "1";
        $type = RequestParameters::get($request, "type");
        $dynamic = RequestParameters::get($request, "dynamic") === "1";
        $exposed = RequestParameters::get($request, "exposed") === "1";
        $name = RequestParameters::get($request, "name");
        $pointer = RequestParameters::get($request, "pointer") === "1";
        $pointerVariable = RequestParameters::get($request, "pointerVariable");

        $result = $this->service->save(
            $object_id,
            $node,
            $variable,
            $default,
            $value,
            $string,
            $type,
            $dynamic,
            $exposed,
            $name,
            $pointer,
            $pointerVariable
        );
        return $this->getSaveResponse($result);
    }

    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/TestNodePort/save', name: 'TestNodePort_save_collection', methods: ['POST'])]
    public function saveCollectionAction(Request $request)
    {
        if (!$this->testNodeService->canBeModified(RequestParameters::get($request, "node_id"), RequestParameters::get($request, "objectTimestamp"), $errorMessages)) {
            $response = new Response(json_encode(array("result" => 1, "errors" => $this->trans($errorMessages))));
            $response->headers->set('Content-Type', 'application/json');
            return $response;
        }

        $result = $this->service->saveCollection(
            RequestParameters::get($request, "serializedCollection")
        );

        $errors = $this->trans($result["errors"]);
        $response = new Response(json_encode([
            "result" => count($errors) > 0 ? 1 : 0,
            "errors" => $errors,
            "objectTimestamp" => time()
        ]));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    /**
     * @param Request $request
     * @param integer $object_id
     * @return Response
     */
    #[Route('/TestNodePort/{object_id}/hide', name: 'TestNodePort_hide', methods: ['POST'])]
    public function hideAction(Request $request, $object_id)
    {
        if (!$this->service->canBeModified($object_id, RequestParameters::get($request, "objectTimestamp"), $errorMessages)) {
            $response = new Response(json_encode(array("result" => 1, "errors" => $this->trans($errorMessages))));
            $response->headers->set('Content-Type', 'application/json');
            return $response;
        }

        $this->service->hide($object_id);
        $response = new Response(json_encode(array("result" => 0)));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }
}
