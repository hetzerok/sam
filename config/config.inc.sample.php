<?php
/**
 * Copyright (c) 2017.
 * Roman Gorbunov (hetzerok)
 * hetzerok@gmail.com
 */

$config = array(

    /* Подключение к БД */
    'username' => 'test', // Имя пользователя БД
    'password' => 'test', // Пароль пользователя БД
    'dsn' => 'mysql:host=localhost:3306;dbname=test', // Строка подключения для PDO

    /* Параметры миграций */
    'import_data' => 1,  // Нужно ли создавать миграции для данных
    'table_prefix' => 'modx_', // Префикс для таблиц
    'conside_foreign_keys' => 1, // Нужно ли учитывать в обработке внешние ключи
    'conside_indexes' => 1, // Нужно ли учитывать в обработке индексы

    'migration_path' => 'db/migrations/', // Путь к списку миграций
    'schema_path' => 'db/schemas/', // Путь к хранящимся файлам схем
    'data_path' => 'db/datas/', // Путь к файлам данных БД
    'version_format' => 'json', // Формат файлов версий
    'schema_format' => 'json', // Формат схем
    'migration_format' => 'json', // Формат файлов миграций
    'time_format' => 'Ymd_His', // Формат представления временного ключа миграции
    'local_version_file' => 'db/local.version', // Путь к файлу локальной версии
    'global_version_file' => 'db/global.version', // Путь к файлу глобальной версии
    'log_path' => 'db/logs/sam.log', // Путь к файлу логов

    /* Список таблиц, для которых необходимо импортировать данные */
    'import_data_tables' => array(

    ),
);
return $config;
