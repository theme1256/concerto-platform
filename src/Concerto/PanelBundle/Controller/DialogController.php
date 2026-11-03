<?php

namespace Concerto\PanelBundle\Controller;

use Concerto\PanelBundle\Service\AdministrationService;
use Twig\Environment;
use Concerto\PanelBundle\Service\PanelService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin")
 */
class DialogController
{
    private $templating;
    private $service;
    private $rootDir;

    public function __construct(Environment $templating, PanelService $service, $rootDir)
    {
        $this->templating = $templating;
        $this->service = $service;
        $this->rootDir = $rootDir;
    }

    /**
     * Extended action with added custom parameters.
     *
     * @Route("/dialog/r_documentation_generation_help.html", name="Dialog_rdoc")
     * @return Response
     */
    public function rDocumentationWindowAction()
    {
        return $this->genericWindowAction(
            'r_documentation_generation_help.html', array(
                'root_dir' => $this->rootDir,
                'is_win' => AdministrationService::getOS() == AdministrationService::OS_WIN
            )
        );
    }

    /**
     * Used to display generic modal windows which don't need any data in their templates.
     *
     * @Route("/dialog/{template_name}", name="Dialog_generic")
     * @Route("/dialog/", name="Dialog_root")
     * @param string $template_name
     * @param array $params
     * @return Response
     */
    public function genericWindowAction($template_name, $params = array())
    {
        $filtered_template_name = filter_var(basename($template_name), FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);

        $template = "@ConcertoPanel/Dialog/$filtered_template_name.twig";

        try {
            return new Response($this->templating->render($template, $params));
        } catch (\InvalidArgumentException | \Twig\Error\LoaderError $exc) {
            throw new NotFoundHttpException("404 - Not found: $filtered_template_name.");
        }
    }

}
