<?php

namespace Concerto\APIBundle\Templating;

use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * Minimal "templating" service for FOSOAuthServerBundle 1.x, whose AuthorizeController
 * still depends on the Templating component (deprecated in Symfony 4.3, removed in 5.0).
 *
 * The rest of the application renders through Twig directly, so the framework
 * templating engine stays disabled. Delete this class together with FOSOAuthServerBundle.
 */
class FosOAuthTwigEngine implements EngineInterface
{
    private $twig;

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    public function render($name, array $parameters = [])
    {
        return $this->twig->render($this->toTwigName($name), $parameters);
    }

    public function renderResponse($view, array $parameters = [], Response $response = null)
    {
        if ($response === null) {
            $response = new Response();
        }
        $response->setContent($this->render($view, $parameters));

        return $response;
    }

    public function exists($name)
    {
        return $this->twig->getLoader()->exists($this->toTwigName($name));
    }

    public function supports($name)
    {
        return substr((string)$name, -5) === '.twig';
    }

    /**
     * "FOSOAuthServerBundle:Authorize:authorize.html.twig" -> "@FOSOAuthServer/Authorize/authorize.html.twig"
     */
    private function toTwigName($name)
    {
        $name = (string)$name;
        if (preg_match('/^(\w+)Bundle:([\w\/]*):(.+)$/', $name, $m)) {
            return '@' . $m[1] . '/' . ($m[2] !== '' ? $m[2] . '/' : '') . $m[3];
        }

        return $name;
    }
}
