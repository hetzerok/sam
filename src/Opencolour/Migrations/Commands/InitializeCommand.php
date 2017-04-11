<?php
/**
 * Copyright (c) 2017.
 * Roman Gorbunov (hetzerok)
 * hetzerok@gmail.com
 */

namespace Opencolour\Migrations\Commands;

use Opencolour\Additions\Config;
use Opencolour\Migrations\FormatCoder;
use Opencolour\Migrations\MigrationCollector;
use Opencolour\Migrations\StructureParser;
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
class InitializeCommand extends Command {

    protected function configure()
    {
        $this->setName("migrations:initialize")
            ->setDescription("Initialize migrations. Make all needed starting files.")
            ->setHelp(<<<EOT
Here is description
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $config = Config::getInstance();
        $formatCoder = new FormatCoder();
        $structureParser = new StructureParser($formatCoder, $output);
        $migrationCollector = new MigrationCollector($formatCoder, $output, $structureParser);

        $structureParser->initializeSchema();
        $migrationCollector->writeMigration(true);

        $output->writeln('<comment>Initialization complete</comment>');
    }
}