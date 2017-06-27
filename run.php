<?php
/**
 * Copyright (c) 2017.
 * Roman Gorbunov (hetzerok)
 * hetzerok@gmail.com
 */

// Подключение автозагрузчика
require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Console\Application;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Opencolour\Additions\Config;

// Подключение конфига
$config = Config::getInstance('config/');

// Создаем канал лога
$log = new Logger('name');
$log->pushHandler(new StreamHandler($config->getOption('log_path').$config->getOption('log_file'), Logger::DEBUG));

// Объявляем набор команд
$commandsArray = array(
    'Opencolour\\Migrations\\Commands\\InitializeCommand',
    'Opencolour\\Migrations\\Commands\\MigrateCommand',
    'Opencolour\\Migrations\\Commands\\GenerateCommand',
);

// Инициализируем команды
$application = new Application('SAM', 'v 0.0.5');
foreach ($commandsArray as $commandName) {
    $command = new $commandName($log);
    $application->add($command);
}
$application->run();