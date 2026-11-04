<?php

namespace Concerto\PanelBundle\Controller;

use Twig\Environment;
use Concerto\PanelBundle\Service\UserService;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted(new Expression("is_granted('ROLE_SUPER_ADMIN')"))]
class UserController extends ASectionController
{

    const ENTITY_NAME = "User";

    public function __construct(Environment $templating, UserService $service, TranslatorInterface $translator)
    {
        parent::__construct($templating, $service, $translator);

        $this->entityName = self::ENTITY_NAME;
    }

    /**
     * @param $object_id
     * @param string $format
     * @return Response
     */
    #[Route('/User/fetch/{object_id}/{format}', name: 'User_object', defaults: ['format' => 'json'])]
    public function objectAction($object_id, $format = "json")
    {
        return parent::objectAction($object_id, $format);
    }

    /**
     * @param string $format
     * @return Response
     */
    #[Route('/User/collection/{format}', name: 'User_collection', defaults: ['format' => 'json'])]
    public function collectionAction($format = "json")
    {
        return parent::collectionAction($format);
    }

    /**
     * @param string $action
     * @param array $params
     * @return Response
     */
    #[Route('/User/form/{action}', name: 'User_form', defaults: ['action' => 'edit'])]
    public function formAction($action = "edit", $params = array())
    {
        return parent::formAction($action, $params);
    }

    /**
     * @param Request $request
     * @param $object_id
     * @return Response
     */
    #[Route('/User/{object_id}/save', name: 'User_save', methods: ['POST'])]
    public function saveAction(Request $request, $object_id)
    {
        $result = $this->service->save(
            $object_id,
            $request->get("accessibility"),
            $request->get("archived") === "1",
            $this->service->get($request->get("owner")),
            $request->get("groups"),
            $request->get("email"),
            $request->get("username"),
            $request->get("password"),
            $request->get("passwordConfirmation"),
            $request->get("role_super_admin"),
            $request->get("role_test"),
            $request->get("role_template"),
            $request->get("role_table"),
            $request->get("role_file"),
            $request->get("role_wizard")
        );
        return $this->getSaveResponse($result);
    }

    /**
     * @param Request $request
     * @param $object_ids
     * @return Response
     */
    #[Route('/User/{object_ids}/delete', name: 'User_delete', methods: ['POST'])]
    public function deleteAction(Request $request, $object_ids)
    {
        return parent::deleteAction($request, $object_ids);
    }
}
