<?php

namespace Concerto\APIBundle\DependencyInjection\Compiler;

use Concerto\APIBundle\Templating\FosOAuthTwigEngine;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Feeds FOSOAuthServerBundle's AuthorizeController a Twig-backed engine instead of the
 * removed "templating" service. A service with the id "templating" must not exist,
 * otherwise TwigBundle falls back to its deprecated templating-based loader.
 * Delete together with FOSOAuthServerBundle.
 */
class FosOAuthTemplatingPass implements CompilerPassInterface
{
    const CONTROLLER = 'fos_oauth_server.controller.authorize';
    const TEMPLATING_ARGUMENT = 4;

    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition(self::CONTROLLER)) {
            return;
        }
        $container->getDefinition(self::CONTROLLER)
            ->replaceArgument(self::TEMPLATING_ARGUMENT, new Reference(FosOAuthTwigEngine::class));
    }
}
