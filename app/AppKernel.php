<?php

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;

class AppKernel extends Kernel
{
    public function registerBundles(): iterable
    {
        $bundles = [
            new Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new Symfony\Bundle\SecurityBundle\SecurityBundle(),
            new Symfony\Bundle\TwigBundle\TwigBundle(),
            new Symfony\Bundle\MonologBundle\MonologBundle(),
            new Doctrine\Bundle\DoctrineBundle\DoctrineBundle(),

            new League\Bundle\OAuth2ServerBundle\LeagueOAuth2ServerBundle(),
            new Cocur\Slugify\Bridge\Symfony\CocurSlugifyBundle(),
            new Lexik\Bundle\JWTAuthenticationBundle\LexikJWTAuthenticationBundle(),

            new Concerto\PanelBundle\ConcertoPanelBundle(),
            new Concerto\TestBundle\ConcertoTestBundle(),
            new Concerto\APIBundle\ConcertoAPIBundle(),
            new Scheb\TwoFactorBundle\SchebTwoFactorBundle()
        ];

        if (in_array($this->getEnvironment(), ['dev', 'test'], true)) {
            $bundles[] = new Symfony\Bundle\DebugBundle\DebugBundle();
            $bundles[] = new Symfony\Bundle\WebProfilerBundle\WebProfilerBundle();
        }

        return $bundles;
    }

    public function getRootDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return dirname(__DIR__) . '/var/cache/' . $this->getEnvironment();
    }

    public function getLogDir(): string
    {
        return dirname(__DIR__) . '/var/logs';
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container) {
            $container->setParameter('container.autowiring.strict_mode', true);
            $container->setParameter('.container.dumper.inline_class_loader', true);

            $container->addObjectResource($this);
        });
        $loader->load($this->getRootDir() . '/config/config_' . $this->getEnvironment() . '.yml');
    }

    public function initializeContainer(): void
    {
        parent::initializeContainer();

        $adminService = $this->container->get("Concerto\\PanelBundle\\Service\\AdministrationService");
        $sessionRunnerService = "PersistantSessionRunnerService";
        //DB will not always be available at this stage
        try {
            $sessionRunnerService = $adminService->getSessionRunnerService();
        } catch (Exception $ex) {
        }
        $this->container->set("Concerto\\TestBundle\\Service\\ASessionRunnerService", $this->container->get("Concerto\\TestBundle\\Service\\" . $sessionRunnerService));
    }
}
