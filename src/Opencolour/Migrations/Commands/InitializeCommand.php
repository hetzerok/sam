<?php
/**
 * Copyright (c) 2017.
 * Roman Gorbunov (hetzerok)
 * hetzerok@gmail.com
 */

namespace Opencolour\Migrations\Commands;

use Opencolour\Additions\Config;
use Opencolour\Additions\Filesystem;
use Opencolour\Migrations\FormatCoder;
use Opencolour\Migrations\MigrationCollector;
use Opencolour\Migrations\StructureParser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Monolog\Logger;

/**
 * Class InitializeCommand
 * @package Opencolour\Migrations\Commands
 *
 * Команда инициализации системы миграций
 */
class InitializeCommand extends Command {

    /** @var Logger $log */
    protected $log;

    protected $config;

    protected $filesystem;

    public function __construct(Logger $log, $name = null)
    {
        $this->log = $log;
        $this->config = Config::getInstance();
        $this->filesystem = new Filesystem($this->log);
        parent::__construct($name);
    }

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
        $formatCoder = new FormatCoder($this->log);
        $structureParser = new StructureParser($this->log, $formatCoder, $output);
        $migrationCollector = new MigrationCollector($this->log, $formatCoder, $output, $structureParser);

        if($this->filesystem->createDirs()) {
            $structureParser->initializeSchema();
            $migrationCollector->writeMigration(true);
        } else {
            $output->writeln('<error>Cannot write in mapped dirs</error>');
        }

        $style = new OutputFormatterStyle('green', 'white', array('bold'));
        $output->getFormatter()->setStyle('end', $style);
        $output->writeln('<end>Initialization complete</end>');
    }
}