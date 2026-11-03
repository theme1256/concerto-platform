<?php

namespace Concerto\PanelBundle\Controller;

use Concerto\PanelBundle\Service\TestSessionService;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class TestSessionController {

    private $templating;
    private $service;
    private $translator;

    public function __construct(Environment $templating, TestSessionService $service, TranslatorInterface $translator) {
        $this->templating = $templating;
        $this->service = $service;
        $this->translator = $translator;
    }
}
