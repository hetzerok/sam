<?php
/**
 * Copyright (c) 2017.
 * Roman Gorbunov (hetzerok)
 * hetzerok@gmail.com
 */

namespace Opencolour\Migrations\Commands;

use Opencolour\Additions\Config;
use Opencolour\Migrations\MigrationCollector;
use Opencolour\Migrations\QueryMaker;
use Opencolour\Migrations\StructureParser;
use Opencolour\Migrations\FormatCoder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

/**
 * Class InitializeCommand
 * @package Opencolour\Migrations\Commands
 *
 * Команда инициализации системы миграций
 */
class MigrateCommand extends Command {

    protected function configure()
    {
        $start = 0;
        $stop = 100;

        $this->setName("migrations:migrate")
            ->setDescription("Applying of migrations")
            ->setDefinition(array(
                new InputOption('last', 'l', InputOption::VALUE_REQUIRED, 'Last version of applyed migration', $start),
            ))
            ->setHelp(<<<EOT
Here is description of applying
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $config = Config::getInstance();
        $formatCoder = new FormatCoder();
        $structureParser = new StructureParser($formatCoder, $output);
        $migrationCollector = new MigrationCollector($formatCoder, $output, $structureParser);
        $queryMaker = new QueryMaker($output);

        // Обрабатываем миграции по очереди
        $migrations = $migrationCollector->getCurrentMigrations($input->getOption('last'));
        if(!empty($migrations)) {
            foreach ($migrations as $key => $migration) {
                if ($queryMaker->schemaQuery($migration)) {
                    $output->writeln('<info>Migration '.$key.' applied successfull.</info>');
                    $structureParser->generateLocalSchema();
                    $migrationCollector->upVersion($key, 'local');
                } else {
                    $output->writeln('<error>Migration '.$key.' not applied.</error>');
                    break;
                }

                // В случае если установлена опция import_data проводим миграции данных
                $contentMigrations = $migrationCollector->getCurrentContentMigrations($key, $migrations);
                if(!empty($contentMigrations)) {
                    foreach ($contentMigrations as $ckey => $cmigration) {
                        if($queryMaker->contentQuery($cmigration)) {
                            $output->writeln('<info>Content migration '.$ckey.' applied successfull.</info>');
                            $structureParser->generateLocalContent();
                            $migrationCollector->upVersion($ckey, 'local');
                        } else {
                            $output->writeln('<error>Content migration '.$ckey.' not applied.</error>');
                            break;
                        }
                    }
                } else {
                    $output->writeln('<error>Cannot find content migrations.</error>');
                }
            }
        } else {
            $output->writeln('<error>Cannot find migrations.</error>');
        }

        $style = new OutputFormatterStyle('green', 'white', array('bold'));
        $output->getFormatter()->setStyle('end', $style);
        $output->writeln('<end>Migration applying complete</end>');
    }
}