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
    'format' => 'json', // Формат хранения всех документов. В дальнейшем нужно будет разделить (пока только JSON)
    'time_format' => 'Ymd_His', // Формат представления временного ключа миграции
    'local_version_file' => 'db/local.version', // Путь к файлу локальной версии
    'global_version_file' => 'db/global.version', // Путь к файлу глобальной версии

    /* Список таблиц, для которых необходимо импортировать данные */
    /* Функционал пока недоступен */
    'import_data_tables' => array(
        'site_templates',
    )
);
return $config;