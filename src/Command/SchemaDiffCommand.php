<?php

namespace App\Command;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:schema:diff-remote', description: 'Show exact physical schema differences between local and remote databases')]
class SchemaDiffCommand extends Command
{
    public function __construct(private ManagerRegistry $registry)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Analyzing Local vs Remote Database Schema');

        try {
            $localConn = $this->registry->getConnection('default');
            $remoteConn = $this->registry->getConnection('remote');

            $localSm = $localConn->createSchemaManager();
            $remoteSm = $remoteConn->createSchemaManager();

            // The tables we configured the sync engine to care about
            $tablesToCheck = ['expense_draft', 'transaction'];
            $differencesFound = false;

            foreach ($tablesToCheck as $tableName) {
                $io->section("Table: $tableName");

                if (!$localSm->tablesExist([$tableName])) {
                    $io->error("Missing entirely in LOCAL database.");
                    $differencesFound = true;
                    continue;
                }
                if (!$remoteSm->tablesExist([$tableName])) {
                    $io->error("Missing entirely in REMOTE database.");
                    $differencesFound = true;
                    continue;
                }

                $localColumns = $localSm->listTableColumns($tableName);
                $remoteColumns = $remoteSm->listTableColumns($tableName);

                // Check Local vs Remote
                foreach ($localColumns as $colName => $localCol) {
                    if (!isset($remoteColumns[$colName])) {
                        $io->warning("Column '$colName' exists locally but is MISSING on remote.");
                        $differencesFound = true;
                        continue;
                    }

                    $localType = get_class($localCol->getType());
                    $remoteType = get_class($remoteColumns[$colName]->getType());

                    if ($localType !== $remoteType) {
                        $io->warning("Type Mismatch on '$colName': Local is [$localType], Remote is [$remoteType].");
                        $differencesFound = true;
                    }
                }

                // Check Remote vs Local (for extra columns on remote)
                foreach ($remoteColumns as $colName => $remoteCol) {
                    if (!isset($localColumns[$colName])) {
                        $io->warning("Column '$colName' exists on remote but is MISSING locally.");
                        $differencesFound = true;
                    }
                }
                
                if (!$differencesFound) {
                    $io->text("Everything matches perfectly.");
                }
            }

            if (!$differencesFound) {
                $io->success("No schema mismatches found. Both databases are identical for synced tables.");
            } else {
                $io->error("Schema mismatches detected! Sync is blocked until fixed.");
            }

            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $io->error("Connection or execution failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
