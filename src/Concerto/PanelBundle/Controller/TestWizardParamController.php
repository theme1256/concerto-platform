<?php

namespace Concerto\PanelBundle\Controller;

use Concerto\PanelBundle\Utils\RequestParameters;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Concerto\PanelBundle\Service\TestWizardService;
use Concerto\PanelBundle\Service\TestVariableService;
use Concerto\PanelBundle\Service\TestWizardParamService;
use Concerto\PanelBundle\Service\TestWizardStepService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted(new Expression("is_granted('ROLE_WIZARD') or is_granted('ROLE_SUPER_ADMIN')"))]
class TestWizardParamController extends ASectionController
{

    const ENTITY_NAME = "TestWizardParam";

    private $testWizardService;
    private $testWizardStepService;
    private $testVariableService;

    public function __construct(Environment $templating, TestWizardParamService $service, TranslatorInterface $translator, TestVariableService $testVariableServce, TestWizardStepService $testWizardStepService, TestWizardService $testWizardService)
    {
        parent::__construct($templating, $service, $translator);

        $this->entityName = self::ENTITY_NAME;
        $this->testWizardService = $testWizardService;
        $this->testWizardStepService = $testWizardStepService;
        $this->testVariableService = $testVariableServce;
    }

    /**
     * @param $object_id
     * @param string $format
     * @return Response
     */
    #[Route('/TestWizardParam/fetch/{object_id}/{format}', name: 'TestWizardParam_object', defaults: ['format' => 'json'])]
    public function objectAction($object_id, $format = "json")
    {
        return parent::objectAction($object_id, $format);
    }

    /**
     * @param string $format
     * @return Response
     */
    #[Route('/TestWizardParam/collection/{format}', name: 'TestWizardParam_collection', defaults: ['format' => 'json'])]
    public function collectionAction($format = "json")
    {
        return parent::collectionAction($format);
    }

    /**
     * @param $wizard_id
     * @return Response
     */
    #[Route('/TestWizardParam/TestWizard/{wizard_id}/collection', name: 'TestWizardParam_collection_by_wizard')]
    public function collectionByWizardAction($wizard_id)
    {
        return new Response($this->templating->render('@ConcertoPanel/collection.json.twig', array(
            'collection' => $this->service->getByTestWizard($wizard_id)
        )));
    }

    /**
     * @param $wizard_id
     * @param $type
     * @return Response
     */
    #[Route('/TestWizardParam/TestWizard/{wizard_id}/type/{type}/collection', name: 'TestWizardParam_collection_by_wizard_and_type')]
    public function collectionByWizardAndTypeAction($wizard_id, $type)
    {
        return new Response($this->templating->render('@ConcertoPanel/collection.json.twig', array(
            'collection' => $this->service->getByTestWizardAndType($wizard_id, $type)
        )));
    }

    /**
     * @param Request $request
     * @param string $object_ids
     * @return Response
     */
    #[Route('/TestWizardParam/{object_ids}/delete', name: 'TestWizardParam_delete', methods: ['POST'])]
    public function deleteAction(Request $request, $object_ids)
    {
        return parent::deleteAction($request, $object_ids);
    }

    /**
     * @param Request $request
     * @param $object_id
     * @return Response
     */
    #[Route('/TestWizardParam/{object_id}/save', name: 'TestWizardParam_save', methods: ['POST'])]
    public function saveAction(Request $request, $object_id)
    {
        if (!$this->service->canBeModified($object_id, RequestParameters::get($request, "objectTimestamp"), $errorMessages)) {
            $response = new Response(json_encode(array("result" => 1, "errors" => $this->trans($errorMessages))));
            $response->headers->set('Content-Type', 'application/json');
            return $response;
        }

        $result = $this->service->save(
            $object_id,
            $this->testVariableService->get(RequestParameters::get($request, "testVariable")),
            RequestParameters::get($request, "label"),
            RequestParameters::get($request, "type"),
            RequestParameters::get($request, "serializedDefinition"),
            RequestParameters::get($request, "hideCondition"),
            RequestParameters::get($request, "description"),
            RequestParameters::get($request, "passableThroughUrl"),
            RequestParameters::get($request, "value"),
            $this->testWizardStepService->get(RequestParameters::get($request, "wizardStep")),
            RequestParameters::get($request, "order"),
            $this->testWizardService->get(RequestParameters::get($request, "wizard")));
        return $this->getSaveResponse($result);
    }

    /**
     * @param Request $request
     * @param $wizard_id
     * @return Response
     */
    #[Route('/TestWizardParam/TestWizard/{wizard_id}/clear', name: 'TestWizardParam_clear', methods: ['POST'])]
    public function clearAction(Request $request, $wizard_id)
    {
        if (!$this->service->canBeModified($wizard_id, RequestParameters::get($request, "objectTimestamp"), $errorMessages)) {
            $response = new Response(json_encode(array("result" => 1, "errors" => $this->trans($errorMessages))));
            $response->headers->set('Content-Type', 'application/json');
            return $response;
        }

        $this->service->clear($wizard_id);
        $response = new Response(json_encode(array("result" => 0)));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

}
