<?php

namespace Concerto\PanelBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Concerto\PanelBundle\Service\TestSessionLogService;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted(new Expression("is_granted('ROLE_TEST') or is_granted('ROLE_SUPER_ADMIN')"))]
class TestSessionLogController extends ASectionController
{

    const ENTITY_NAME = "TestSessionLog";

    public function __construct(Environment $templating, TestSessionLogService $service, TranslatorInterface $translator)
    {
        parent::__construct($templating, $service, $translator);

        $this->entityName = self::ENTITY_NAME;
    }

    /**
     * @param string $format
     * @return Response
     */
    #[Route('/TestSessionLog/collection', name: 'TestSessionLog_collection')]
    public function collectionAction($format = "json")
    {
        return parent::collectionAction($format);
    }

    /**
     * @param $test_id
     * @return Response
     */
    #[Route('/TestSessionLog/Test/{test_id}/collection', name: 'TestSessionLog_collection_by_test')]
    public function collectionByTestAction($test_id)
    {
        return new Response($this->templating->render('@ConcertoPanel/collection.json.twig', array(
                'collection' => $this->service->getLatestByTest($test_id)
            )
        ));
    }

    /**
     * @param Request $request
     * @param string $object_ids
     * @return Response
     */
    #[Route('/TestSessionLog/{object_ids}/delete', name: 'TestSessionLog_delete', methods: ['POST'])]
    public function deleteAction(Request $request, $object_ids)
    {
        return parent::deleteAction($request, $object_ids);
    }

    /**
     * @param $test_id
     * @return Response
     */
    #[Route('/TestSessionLog/Test/{test_id}/clear', name: 'TestSessionLog_clear', methods: ['POST'])]
    public function clearAction($test_id)
    {
        $this->service->clear($test_id);
        $response = new Response(json_encode(array("result" => 0)));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }
}