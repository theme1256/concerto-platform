<?php

namespace Concerto\APIBundle;

use Concerto\APIBundle\DependencyInjection\Compiler\FosOAuthTemplatingPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class ConcertoAPIBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        $container->addCompilerPass(new FosOAuthTemplatingPass());
    }
}
