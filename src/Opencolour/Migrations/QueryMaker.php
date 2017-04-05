<?php
/**
 * Copyright (c) 2017.
 * Roman Gorbunov (hetzerok)
 * hetzerok@gmail.com
 */

namespace Opencolour\Migrations;

use Opencolour\Additions\Config;

class QueryMaker
{

    /* @var Config $config */
    protected $config = null;

    /* @var \PDO $pdo */
    protected $pdo = null;

    /**
     * StructureParser constructor.
     */
    public function __construct()
    {
        $this->config = Config::getInstance();
        $this->pdo = $this->config->getConnection();
    }

    public function schemaQuery($schema)
    {

        // Цикл создания и заполнения таблиц
        foreach ($schema as $table_key => $table) {

            // Необходимо создать таблицу
            if (!$table['exist'] && $table['columns']) {
                $sql = "CREATE TABLE `" . $this->config->getOption('table_prefix') . $table['name'] . "`";

                // Добавляем столбцы к таблице
                $csql = $this->addQueryColumns($table['columns']);

                // Добавляем PK
                if($table['primaryKey']) {
                    if(is_array($table['primaryKey'])) {
                        foreach($table['primaryKey'] as $key => $value) {
                            $table['primaryKey'][$key] = "`" . $value . "`";
                        }
                        $pk = implode(', ', $table['primaryKey']);
                    } else {
                        $pk = "`" . $table['primaryKey'] . "`";
                    }
                    $csql .= ", PRIMARY KEY (" . $pk . ")";
                }

                // Оборачиваем в скобки добавленные столбцы
                if($csql) {
                    $sql .= " (" . $csql . ")";
                }

                $sql .= " COMMENT='" . $table['comment']
                    . "' COLLATE='" . $table['collation']
                    . "' ENGINE='" . $table['engine']. "';";

                $this->pdo->query($sql);

            // Таблица уже создана, действуем через ALTER
            } else if ($table['exist']){
                $sql = "ALTER TABLE `" . $this->config->getOption('table_prefix') . $table_key . "` ";

                // Если задано имя - переименовываем
                if($table['name']) {
                    $sql .= "RENAME TO `" . $this->config->getOption('table_prefix') . $table['name'] . "`";
                }

                $this->pdo->query($sql);
            }

            // Создаем необходимые столбцы

            // Создаем индексы
        }

        // Цикл для добавления constraints (чтобы быть уверенными в существовании столбцов)
        foreach ($schema as $table) {

        }
    }

    public function addQueryColumns($columns) {
        $sql = '';
        $i = 0;
        foreach($columns as $col) {

            // NULL или NOT NULL
            if($col['nullable']) {
                $null = ' NULL';
            } else {
                $null = ' NOT NULL';
            }

            // Значение по умолчанию
            if($col['default'] === null) {
                if($col['nullable']) {
                    $default = " DEFAULT NULL";
                } else {
                    $default = "";
                }
            } else {
                $default = " DEFAULT '" . $col['default'] . "'";
            }

            // Auto Increment
            $ai = "";
            if($col['autoIncrement']) {
                $ai = ' AUTO_INCREMENT';
            }

            // Комментарий если есть
            $comment = '';
            if($col['comment']) {
                $comment = " COMMENT '" . $col['comment'] . "'";
            }

            // Верная расстановка запятых
            if($i > 0) {
                $sql .= ",";
            }
            $i++;

            $sql .= " `" . $col['name'] . "` " . strtoupper($col['type']) . $null . $default . $ai . $comment;
        }
        return $sql;
    }

}