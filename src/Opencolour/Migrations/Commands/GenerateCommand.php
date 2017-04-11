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
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

/**
 * Class InitializeCommand
 * @package Opencolour\Migrations\Commands
 *
 * Команда инициализации системы миграций
 */
class GenerateCommand extends Command {

    protected function configure()
    {
        $this->setName("migrations:generate")
            ->setDescription("Generate new migration file")
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

        if($migrationCollector->writeMigration()) {
            $structureParser->initializeSchema();
        }

        $output->writeln('<comment>Generating complete</comment>');
    }
}