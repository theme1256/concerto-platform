<?php

namespace Concerto\PanelBundle\Command;

use Concerto\PanelBundle\Service\MaintenanceService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConcertoSessionsClearCommand extends Command
{

    private $maintenanceService;

    public function __construct(MaintenanceService $maintenanceService)
    {
        parent::__construct();

        $this->maintenanceService = $maintenanceService;
    }

    protected function configure(): void
    {
        $this->setName("concerto:sessions:clear")->setDescription("Clearing session files.");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->maintenanceService->deleteOldSessions();

        return 0;
    }

}
