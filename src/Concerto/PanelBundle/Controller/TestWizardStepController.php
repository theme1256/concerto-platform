<?php

namespace Concerto\PanelBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Concerto\PanelBundle\Service\TestWizardService;
use Concerto\PanelBundle\Service\TestWizardStepService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted(new Expression("is_granted('ROLE_WIZARD') or is_granted('ROLE_SUPER_ADMIN')"))]
class TestWizardStepController extends ASectionController
{

    const ENTITY_NAME = "TestWizardStep";

    private $testWizardService;

    public function __construct(Environment $templating, TestWizardStepService $service, TranslatorInterface $translator, TestWizardService $testWizardService)
    {
        parent::__construct($templating, $service, $translator);

        $this->entityName = self::ENTITY_NAME;
        $this->testWizardService = $testWizardService;
    }

    /**
     * @param $object_id
     * @param string $format
     * @return Response
     */
    #[Route('/TestWizardStep/fetch/{object_id}/{format}', name: 'TestWizardStep_object', defaults: ['format' => 'json'])]
    public function objectAction($object_id, $format = "json")
    {
        return parent::objectAction($object_id, $format);
    }

    /**
     * @param $wizard_id
     * @param string $format
     * @return Response
     */
    #[Route('/TestWizardStep/TestWizard/{wizard_id}/collection/{format}', name: 'TestWizardStep_collection_by_wizard', defaults: ['format' => 'json'])]
    public function collectionByWizardAction($wizard_id, $format = "json")
    {
        return new Response($this->templating->render('@ConcertoPanel/collection.' . $format . '.twig', array(
            'collection' => $this->service->getByTestWizard($wizard_id)
        )));
    }

    /**
     * @param string $format
     * @return Response
     */
    #[Route('/TestWizardStep/collection/{format}', name: 'TestWizardStep_collection', defaults: ['format' => 'json'])]
    public function collectionAction($format = "json")
    {
        return parent::collectionAction($format);
    }

    /**
     * @param Request $request
     * @param string $object_ids
     * @return Response
     */
    #[Route('/TestWizardStep/{object_ids}/delete', name: 'TestWizardStep_delete', methods: ['POST'])]
    public function deleteAction(Request $request, $object_ids)
    {
        return parent::deleteAction($request, $object_ids);
    }

    /**
     * @param Request $request
     * @param $object_id
     * @return Response
     */
    #[Route('/TestWizardStep/{object_id}/save', name: 'TestWizardStep_save', methods: ['POST'])]
    public function saveAction(Request $request, $object_id)
    {
        if (!$this->service->canBeModified($object_id, $request->get("objectTimestamp"), $errorMessages)) {
            $response = new Response(json_encode(array("result" => 1, "errors" => $this->trans($errorMessages))));
            $response->headers->set('Content-Type', 'application/json');
            return $response;
        }

        $result = $this->service->save(
            $object_id,
            $request->get("title"),
            $request->get("description"),
            $request->get("orderNum"),
            $this->testWizardService->get($request->get("wizard")));
        return $this->getSaveResponse($result);
    }

    /**
     * @param Request $request
     * @param $wizard_id
     * @return Response
     */
    #[Route('/TestWizardStep/TestWizard/{wizard_id}/clear', name: 'TestWizardStep_clear', methods: ['POST'])]
    public function clearAction(Request $request, $wizard_id)
    {
        if (!$this->service->canBeModified($wizard_id, $request->get("objectTimestamp"), $errorMessages)) {
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
