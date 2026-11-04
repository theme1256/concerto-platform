<?php

namespace Concerto\PanelBundle\Controller;

use Concerto\PanelBundle\Utils\RequestParameters;
use Concerto\PanelBundle\Entity\Test;
use Concerto\PanelBundle\Service\FileService;
use Concerto\PanelBundle\Service\TestService;
use Concerto\PanelBundle\Service\ViewTemplateService;
use Symfony\Component\HttpFoundation\Response;
use Concerto\PanelBundle\Service\TestWizardService;
use Twig\Environment;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;
use Concerto\PanelBundle\Service\ImportService;
use Concerto\PanelBundle\Service\ExportService;
use Concerto\PanelBundle\Service\UserService;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted(new Expression("is_granted('ROLE_TEST') or is_granted('ROLE_SUPER_ADMIN')"))]
class TestController extends AExportableTabController
{

    const ENTITY_NAME = "Test";
    const EXPORT_FILE_PREFIX = "Test";

    private $testWizardService;
    private $userService;
    private $viewTemplateService;

    public function __construct($environment, Environment $templating, TestService $service, TranslatorInterface $translator, TestWizardService $testWizardService, ImportService $importService, ExportService $exportService, UserService $userService, FileService $fileService, ViewTemplateService $viewTemplateService)
    {
        parent::__construct($environment, $templating, $service, $translator, $importService, $exportService, $fileService);

        $this->entityName = self::ENTITY_NAME;
        $this->exportFilePrefix = self::EXPORT_FILE_PREFIX;

        $this->testWizardService = $testWizardService;
        $this->userService = $userService;
        $this->viewTemplateService = $viewTemplateService;
    }

    /**
     * @param $object_id
     * @param string $format
     * @return Response
     */
    #[Route('/Test/fetch/{object_id}/{format}', name: 'Test_object', defaults: ['format' => 'json'])]
    public function objectAction($object_id, $format = "json")
    {
        return parent::objectAction($object_id, $format);
    }

    /**
     * @param string $format
     * @return Response
     */
    #[Route('/Test/collection/{format}', name: 'Test_collection', defaults: ['format' => 'json'])]
    public function collectionAction($format = "json")
    {
        $collection = $this->service->getAll();
        foreach ($collection as $test) {
            /**
             * @var Test $test
             */
            $test->clearNodes();
            $test->clearNodesConnections();
        }
        return new Response($this->templating->render("@ConcertoPanel/collection.$format.twig", array(
            'collection' => $collection
        )));
    }

    /**
     * @param Request $request
     * @param $object_id
     * @return Response
     */
    #[Route('/Test/{object_id}/toggleLock', name: 'Test_toggleLock')]
    public function toggleLock(Request $request, $object_id)
    {
        return parent::toggleLock($request, $object_id);
    }

    /**
     * @param string $action
     * @param array $params
     * @return Response
     */
    #[Route('/Test/form/{action}', name: 'Test_form', defaults: ['action' => 'edit'])]
    public function formAction($action = "edit", $params = array())
    {
        return parent::formAction($action, $params);
    }

    /**
     * @param Request $request
     * @param $object_id
     * @return Response
     */
    #[Route('/Test/{object_id}/save', name: 'Test_save', methods: ['POST'])]
    public function saveAction(Request $request, $object_id)
    {
        if (!$this->service->canBeModified($object_id, RequestParameters::get($request, "updatedOn"), $errorMessages)) {
            $response = new Response(json_encode(array("result" => 1, "errors" => $this->trans($errorMessages))));
            $response->headers->set('Content-Type', 'application/json');
            return $response;
        }

        $result = $this->service->save(
            $object_id,
            RequestParameters::get($request, "name"),
            RequestParameters::get($request, "description"),
            RequestParameters::get($request, "accessibility"),
            RequestParameters::get($request, "archived") === "1",
            $this->userService->get(RequestParameters::get($request, "owner")),
            RequestParameters::get($request, "groups"),
            RequestParameters::get($request, "visibility"),
            RequestParameters::get($request, "type"),
            RequestParameters::get($request, "code"),
            $this->testWizardService->get(RequestParameters::get($request, "sourceWizard"), false),
            RequestParameters::get($request, "slug"),
            RequestParameters::get($request, "serializedVariables"),
            $this->viewTemplateService->get(RequestParameters::get($request, "baseTemplate"), false),
            RequestParameters::get($request, "protected") === "1"
        );
        return $this->getSaveResponse($result);
    }

    /**
     * @param Request $request
     * @param $object_id
     * @return Response
     */
    #[Route('/Test/{object_id}/copy', name: 'Test_copy', methods: ['POST'])]
    public function copyAction(Request $request, $object_id)
    {
        return parent::copyAction($request, $object_id);
    }

    /**
     * @param Request $request
     * @param $object_ids
     * @return Response
     */
    #[Route('/Test/{object_ids}/delete', name: 'Test_delete', methods: ['POST'])]
    public function deleteAction(Request $request, $object_ids)
    {
        return parent::deleteAction($request, $object_ids);
    }

    /**
     * @param string $instructions
     * @param string $format
     * @return Response
     */
    #[Route('/Test/{instructions}/export/{format}', name: 'Test_export', defaults: ['format' => 'yml'])]
    public function exportAction($instructions, $format = "yml")
    {
        return parent::exportAction($instructions, $format);
    }

    /**
     * @param $object_ids
     * @return Response
     */
    #[Route('/Test/{object_ids}/instructions/export', name: 'Test_export_instructions')]
    public function exportInstructionsAction($object_ids)
    {
        return parent::exportInstructionsAction($object_ids);
    }

    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/Test/import', name: 'Test_import', methods: ['POST'])]
    public function scheduleImportAction(Request $request)
    {
        return parent::scheduleImportAction($request);
    }

    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/Test/import/status', name: 'Test_pre_import_status')]
    public function preImportStatusAction(Request $request)
    {
        return parent::preImportStatusAction($request);
    }

    /**
     * @param Request $request
     * @param $object_id
     * @return Response
     */
    #[Route('/Test/{object_id}/node/add', name: 'Test_add_node', methods: ['POST'])]
    public function addNodeAction(Request $request, $object_id)
    {
        if (!$this->service->canBeModified($object_id, RequestParameters::get($request, "objectTimestamp"), $errorMessages)) {
            $response = new Response(json_encode(array("result" => 1, "errors" => $this->trans($errorMessages))));
            $response->headers->set('Content-Type', 'application/json');
            return $response;
        }

        $result = $this->service->addFlowNode(
            RequestParameters::get($request, "type"),
            RequestParameters::get($request, "posX"),
            RequestParameters::get($request, "posY"),
            $this->service->get(RequestParameters::get($request, "flowTest")),
            $this->service->get(RequestParameters::get($request, "sourceTest")),
            "",
            false);
        return $this->getSaveResponse($result);
    }

    /**
     * @param Request $request
     * @param $object_id
     * @return Response
     */
    #[Route('/Test/{object_id}/connection/add', name: 'Test_add_connection', methods: ['POST'])]
    public function addNodeConnectionAction(Request $request, $object_id)
    {
        if (!$this->service->canBeModified($object_id, RequestParameters::get($request, "objectTimestamp"), $errorMessages)) {
            $response = new Response(json_encode(array("result" => 1, "errors" => $this->trans($errorMessages))));
            $response->headers->set('Content-Type', 'application/json');
            return $response;
        }

        $result = $this->service->addFlowConnection(
            $this->service->get(RequestParameters::get($request, "flowTest")),
            RequestParameters::get($request, "sourceNode"),
            RequestParameters::get($request, "sourcePort") ? RequestParameters::get($request, "sourcePort") : null,
            RequestParameters::get($request, "destinationNode"),
            RequestParameters::get($request, "destinationPort") ? RequestParameters::get($request, "destinationPort") : null,
            RequestParameters::get($request, "returnFunction"),
            RequestParameters::get($request, "default") == "1",
            false);
        return $this->getSaveResponse($result);
    }

    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/Test/node/move', name: 'Test_move_node', methods: ['POST'])]
    public function moveNodeAction(Request $request)
    {
        if (!$this->service->canBeModified(RequestParameters::get($request, "object_id"), RequestParameters::get($request, "objectTimestamp"), $errorMessages)) {
            $response = new Response(json_encode(array("result" => 1, "errors" => $this->trans($errorMessages))));
            $response->headers->set('Content-Type', 'application/json');
            return $response;
        }

        $nodes = json_decode(RequestParameters::get($request, "nodes"), true);
        $this->service->moveFlowNode($nodes);

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
     * @return Response
     */
    #[Route('/Test/{object_id}/node/paste', name: 'Test_paste_nodes', methods: ['POST'])]
    public function pasteNodesAction(Request $request, $object_id)
    {
        if (!$this->service->canBeModified($object_id, RequestParameters::get($request, "objectTimestamp"), $errorMessages)) {
            $response = new Response(json_encode(array("result" => 1, "errors" => $this->trans($errorMessages))));
            $response->headers->set('Content-Type', 'application/json');
            return $response;
        }

        $result = $this->service->pasteNodes(
            $this->service->get($object_id),
            json_decode(RequestParameters::get($request, "nodes"), true),
            false);
        return $this->getSaveResponse($result);
    }
}
