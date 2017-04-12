<?php
/**
 * Copyright (c) 2017.
 * Roman Gorbunov (hetzerok)
 * hetzerok@gmail.com
 */

// Подключение автозагрузчика
require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Console\Application;

// Объявляем набор команд
$commandsArray = array(
    'Opencolour\\Migrations\\Commands\\InitializeCommand',
    'Opencolour\\Migrations\\Commands\\MigrateCommand',
    'Opencolour\\Migrations\\Commands\\GenerateCommand',
);

// Инициализируем команды
$application = new Application('MODX console', 'v 0.0.2');
foreach ($commandsArray as $commandName) {
    $command = new $commandName();
    $application->add($command);
}
$application->run();