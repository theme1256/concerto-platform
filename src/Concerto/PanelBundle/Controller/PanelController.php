<?php

namespace Concerto\PanelBundle\Controller;

use Concerto\PanelBundle\Service\UserService;
use Twig\Environment;
use Concerto\PanelBundle\Service\PanelService;
use Concerto\PanelBundle\Service\FileService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

class PanelController
{

    private $templating;
    private $service;
    private $fileService;
    private $userService;
    private $security;

    public function __construct(Environment $templating, PanelService $service, FileService $fileService, UserService $userService, Security $security)
    {
        $this->templating = $templating;
        $this->service = $service;
        $this->fileService = $fileService;
        $this->userService = $userService;
        $this->security = $security;
    }

    /**
     * @return Response
     */
    #[Route('/admin', name: 'index')]
    public function indexAction()
    {
        return new Response($this->templating->render('@ConcertoPanel/Panel/index.html.twig'));
    }

    /**
     * @return Response
     */
    #[Route('/admin/breadcrumbs', name: 'breadcrumbs_template')]
    public function breadcrumbsAction()
    {
        return new Response($this->templating->render('@ConcertoPanel/breadcrumbs.html.twig'));
    }

    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/login', name: 'login')]
    public function loginAction(Request $request)
    {
        return new Response($this->templating->render('@ConcertoPanel/Panel/login.html.twig', array(
            'last_username' => $request->getSession()->get(SecurityRequestAttributes::LAST_USERNAME),
            'error' => $this->service->getLoginErrors($request->get(SecurityRequestAttributes::AUTHENTICATION_ERROR), $request->getSession()))));
    }

    /**
     * @param Request $request
     * @param string $locale
     * @return Response
     */
    #[Route('/admin/locale/{locale}', name: 'locale_change')]
    public function changeLocaleAction(Request $request, $locale)
    {
        $request->setLocale($locale);
        $request->setDefaultLocale($locale);
        $request->getSession()->set("_locale", $locale);

        return new RedirectResponse($request->getUriForPath("/admin"));
    }

    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/admin/disable_mfa', name: 'disable_mfa')]
    public function disableMFA(Request $request)
    {
        $user = $this->security->getUser();
        $this->userService->disableMFA($user);
        return new Response();
    }

    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/admin/enable_mfa', name: 'enable_mfa')]
    public function enableMFA(Request $request)
    {
        $user = $this->security->getUser();
        $content = $this->userService->enableMFA($user);
        return new Response(json_encode($content), 200, ["Content-Type" => "application/json"]);
    }
}
