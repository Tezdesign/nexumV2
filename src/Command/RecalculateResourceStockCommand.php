<?php

namespace App\Command;

use App\Service\ResourcesManagement\ResourceStockService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:resources:recalculate-stock', description: 'Rewrites every resource\'s available quantity from its accepted, unreturned assignments.')]
final class RecalculateResourceStockCommand extends Command
{
    public function __construct(private readonly ResourceStockService $stock)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(sprintf('Corrected %d resource(s).', $this->stock->recalculateAll()));

        return Command::SUCCESS;
    }
}
