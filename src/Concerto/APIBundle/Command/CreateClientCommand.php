<?php

namespace Concerto\APIBundle\Command;

use Concerto\APIBundle\Entity\Client;
use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use League\Bundle\OAuth2ServerBundle\ValueObject\Grant;
use League\Bundle\OAuth2ServerBundle\ValueObject\RedirectUri;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Creates an OAuth2 API client. Keeps the command name and options of the former
 * FOSOAuthServerBundle command, which the administration panel and scripts invoke.
 */
class CreateClientCommand extends Command
{
    /**
     * FOSOAuthServerBundle grant type names that differ from league/oauth2-server ones.
     */
    const LEGACY_GRANT_NAMES = array(
        'token' => 'implicit',
    );

    private $clientManager;

    public function __construct(ClientManagerInterface $clientManager)
    {
        $this->clientManager = $clientManager;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('oauth-server:client:create')
            ->setDescription('Creates a new client')
            ->addOption(
                'redirect-uri', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Sets redirect uri for client. Use this option multiple times to set multiple redirect URIs.', null
            )
            ->addOption(
                'grant-type', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Sets allowed grant type for client. Use this option multiple times to set multiple grant types.', null
            )
            ->setHelp(
                <<<EOT
                    The <info>%command.name%</info>command creates a new client.

<info>php %command.full_name% [--redirect-uri=...] [--grant-type=...]</info>

EOT
            );
    }

    public static function normalizeGrantName($grant)
    {
        return self::LEGACY_GRANT_NAMES[$grant] ?? $grant;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $identifier = bin2hex(random_bytes(16));
        $secret = bin2hex(random_bytes(32));

        $client = new Client('API client', $identifier, $secret);
        $client->setRedirectUris(...array_map(function ($uri) {
            return new RedirectUri($uri);
        }, $input->getOption('redirect-uri')));
        $client->setGrants(...array_map(function ($grant) {
            return new Grant(self::normalizeGrantName($grant));
        }, array_unique($input->getOption('grant-type'))));
        $this->clientManager->save($client);

        $output->writeln(
            sprintf(
                'Added a new client with public id <info>%s</info>, secret <info>%s</info>', $client->getIdentifier(), $client->getSecret()
            )
        );

        return 0;
    }
}
