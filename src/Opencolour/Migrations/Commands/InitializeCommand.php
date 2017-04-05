<?php
/**
 * Copyright (c) 2017.
 * Roman Gorbunov (hetzerok)
 * hetzerok@gmail.com
 */

namespace Opencolour\Migrations\Commands;

use Opencolour\Additions\Config;
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
        $start = 0;
        $stop = 100;

        $this->setName("migrations:initialize")
            ->setDescription("Initialize migrations. Make all needed starting files.")
            ->setDefinition(array(
                new InputOption('start', 's', InputOption::VALUE_OPTIONAL, 'Start number of the range of Fibonacci number', $start),
                new InputOption('stop', 'e', InputOption::VALUE_OPTIONAL, 'stop number of the range of Fibonacci number', $stop)
            ))
            ->setHelp(<<<EOT
Here is description
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $config = Config::getInstance();
        $structureParser = new StructureParser();
        $structureParser->generateLocalSchema();
        $header_style = new OutputFormatterStyle('white', 'green', array('bold'));
        $output->getFormatter()->setStyle('header', $header_style);

        $output->writeln('<header>FIts alive!</header>');
    }
}