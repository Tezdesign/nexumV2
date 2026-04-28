<?php

namespace App\Command;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'nexum:test-connections',
    description: 'Pings both local and remote databases to verify connectivity.'
)]
class TestConnectionsCommand extends Command
{
    private ManagerRegistry $doctrine;

    public function __construct(ManagerRegistry $doctrine)
    {
        parent::__construct();
        $this->doctrine = $doctrine;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Nexum Database Connection Tester');

        // 1. Test the Local XAMPP Connection
        $io->section('Testing Local Database (default)...');
        try {
            $localConn = $this->doctrine->getConnection('default');
            // Force a lightweight query to actually ping the database
            $localConn->executeQuery('SELECT 1');
            $io->success('SUCCESS: Connected to Local XAMPP Database.');
        } catch (\Exception $e) {
            $io->error('FAILED: Could not connect to Local Database.');
            $io->writeln($e->getMessage());
        }

        // 2. Test the Remote Server Connection
        $io->section('Testing Remote Database (remote)...');
        try {
            $remoteConn = $this->doctrine->getConnection('remote');
            // Force a lightweight query
            $remoteConn->executeQuery('SELECT 1');
            $io->success('SUCCESS: Connected to Remote Server Database.');
        } catch (\Exception $e) {
            $io->warning('FAILED: Could not connect to Remote Database.');
            $io->text('NOTE: This is expected if your live server or port forwarding is not set up yet!');
            $io->writeln($e->getMessage());
        }

        return Command::SUCCESS;
    }
}
