<?php

namespace Concerto\PanelBundle\Controller;

use Concerto\PanelBundle\Utils\RequestParameters;
use Concerto\PanelBundle\Service\FileService;
use Concerto\PanelBundle\Service\TestService;
use Twig\Environment;
use Concerto\PanelBundle\Service\TestWizardService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;
use Concerto\PanelBundle\Service\ImportService;
use Concerto\PanelBundle\Service\ExportService;
use Concerto\PanelBundle\Service\UserService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted(new Expression("is_granted('ROLE_WIZARD') or is_granted('ROLE_SUPER_ADMIN')"))]
class TestWizardController extends AExportableTabController
{

    const ENTITY_NAME = "TestWizard";
    const EXPORT_FILE_PREFIX = "TestWizard";

    private $testService;
    private $userService;

    public function __construct($environment, Environment $templating, TestWizardService $service, TranslatorInterface $translator, TestService $testService, ImportService $importService, ExportService $exportService, UserService $userService, FileService $fileService)
    {
        parent::__construct($environment, $templating, $service, $translator, $importService, $exportService, $fileService);

        $this->entityName = self::ENTITY_NAME;
        $this->exportFilePrefix = self::EXPORT_FILE_PREFIX;

        $this->testService = $testService;
        $this->userService = $userService;
    }

    /**
     * @param $object_id
     * @param string $format
     * @return Response
     */
    #[Route('/TestWizard/fetch/{object_id}/{format}', name: 'TestWizard_object', defaults: ['format' => 'json'])]
    public function objectAction($object_id, $format = "json")
    {
        return parent::objectAction($object_id, $format);
    }

    /**
     * @param string $format
     * @return Response
     */
    #[Route('/TestWizard/collection/{format}', name: 'TestWizard_collection', defaults: ['format' => 'json'])]
    public function collectionAction($format = "json")
    {
        return parent::collectionAction($format);
    }

    /**
     * @param Request $request
     * @param $object_id
     * @return Response
     */
    #[Route('/TestWizard/{object_id}/toggleLock', name: 'TestWizard_toggleLock')]
    public function toggleLock(Request $request, $object_id)
    {
        return parent::toggleLock($request, $object_id);
    }

    /**
     * @param string $action
     * @param array $params
     * @return Response
     */
    #[Route('/TestWizard/form/{action}', name: 'TestWizard_form', defaults: ['action' => 'edit'])]
    public function formAction($action = "edit", $params = array())
    {
        return parent::formAction($action, $params);
    }

    /**
     * @param Request $request
     * @param $object_id
     * @return Response
     */
    #[Route('/TestWizard/{object_id}/save', name: 'TestWizard_save', methods: ['POST'])]
    public function saveAction(Request $request, $object_id)
    {
        if (!$this->service->canBeModified($object_id, RequestParameters::get($request, "objectTimestamp"), $errorMessages)) {
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
            $this->testService->get(RequestParameters::get($request, "test")),
            RequestParameters::get($request, "serializedSteps")
        );
        return $this->getSaveResponse($result);
    }

    /**
     * @param Request $request
     * @param $object_id
     * @return Response
     */
    #[Route('/TestWizard/{object_id}/copy', name: 'TestWizard_copy', methods: ['POST'])]
    public function copyAction(Request $request, $object_id)
    {
        return parent::copyAction($request, $object_id);
    }

    /**
     * @param Request $request
     * @param string $object_ids
     * @return Response
     */
    #[Route('/TestWizard/{object_ids}/delete', name: 'TestWizard_delete', methods: ['POST'])]
    public function deleteAction(Request $request, $object_ids)
    {
        return parent::deleteAction($request, $object_ids);
    }

    /**
     * @param string $instructions
     * @param string $format
     * @return Response
     */
    #[Route('/TestWizard/{instructions}/export/{format}', name: 'TestWizard_export', defaults: ['format' => 'yml'])]
    public function exportAction($instructions, $format = "yml")
    {
        return parent::exportAction($instructions, $format);
    }

    /**
     * @param $object_ids
     * @return Response
     */
    #[Route('/TestWizard/{object_ids}/instructions/export', name: 'TestWizard_export_instructions')]
    public function exportInstructionsAction($object_ids)
    {
        return parent::exportInstructionsAction($object_ids);
    }

    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/TestWizard/import', name: 'TestWizard_import', methods: ['POST'])]
    public function scheduleImportAction(Request $request)
    {
        return parent::scheduleImportAction($request);
    }

    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/TestWizard/import/status', name: 'TestWizard_pre_import_status')]
    public function preImportStatusAction(Request $request)
    {
        return parent::preImportStatusAction($request);
    }
}
