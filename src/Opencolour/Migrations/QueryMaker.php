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
//                if($table['primaryKey']) {
//                    if(is_array($table['primaryKey'])) {
//                        foreach($table['primaryKey'] as $key => $value) {
//                            $table['primaryKey'][$key] = "`" . $value . "`";
//                        }
//                        $pk = implode(', ', $table['primaryKey']);
//                    } else {
//                        $pk = "`" . $table['primaryKey'] . "`";
//                    }
//                    $csql .= ", PRIMARY KEY (" . $pk . ")";
//                }

                if(mb_strlen($csql) > 3) {
                    $csql .= ',';
                }
                $csql .= $this->addQueryIndexes($table['indexes']);

                // Оборачиваем в скобки добавленные столбцы
                if($csql) {
                    $sql .= " (" . $csql . ")";
                }

                $sql .= " COMMENT='" . $table['comment']
                    . "' COLLATE='" . $table['collation']
                    . "' ENGINE='" . $table['engine']. "';";

                $this->pdo->query($sql);

            // Таблица уже создана, действуем через ALTER
            } else if ($table['action']){

                // Изменяем опции и столбцы таблицы
                if($table['action'] == 'alter') {
                    $sql = "ALTER TABLE `".$this->config->getOption('table_prefix').$table_key."`";
                    $i = 0;

                    // Если задано имя - переименовываем
                    if ($table['name']) {
                        $sql .= " RENAME TO `".$this->config->getOption('table_prefix').$table['name']."`";
                        $i++;
                    }

                    // Если задан движок - изменяем его
                    if ($table['engine']) {

                        // Верная расстановка запятых
                        if ($i > 0) {
                            $sql .= ",";
                        }
                        $i++;

                        $sql .= " ENGINE `".$table['engine']."`";
                    }

                    // Если задан комментарий - изменяем его
                    if ($table['comment']) {

                        // Верная расстановка запятых
                        if ($i > 0) {
                            $sql .= ",";
                        }
                        $i++;

                        $sql .= " COMMENT '".$table['comment']."'";
                    }

                    if ($table['columns']) {

                        // Верная расстановка запятых
                        if ($i > 0) {
                            $sql .= ",";
                        }
                        //$i++;

                        $sql .= $this->alterQueryColumns($table['columns']);
                    }

                    $sql .= ';';

                    $this->pdo->query($sql);
                }

                // Удаляем таблицу
                else if ($table['action'] == 'drop') {
                    $sql = "DROP TABLE `" . $this->config->getOption('table_prefix').$table_key . "`;";
                    $this->pdo->query($sql);
                    unlink($schema[$table_key]);
                }
            }

            // Создаем необходимые pk
            // Создаем индексы
        }

        // Цикл для добавления constraints (чтобы быть уверенными в существовании столбцов)
        foreach ($schema as $table) {

        }
    }

    public function generateColumnQuery($col) {

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

        $sql = "`" . $col['name'] . "` " . strtoupper($col['type']) . $null . $default . $ai . $comment;

        return $sql;
    }

    /**
     * Действия над столбцами в запросе изменения таблицы
     *
     * @param array $columns
     * @return string
     */
    public function alterQueryColumns($columns) {
        $sql = ' ';
        $i = 0;
        foreach($columns as $key => $col) {

            $gen = false;
            $action = '';
            if($col['action']) {
                if($col['action'] == 'drop') {
                    $action = "DROP COLUMN `" . $key . "``";
                } else if ($col['action'] == 'change') {
                    $action = "CHANGE `".$key."` ";
                    $gen = true;
                }
            } else {
                $action = "ADD COLUMN `".$key."` ";
                $gen = true;
            }

            // Верная расстановка запятых
            if($i > 0) {
                $sql .= ", ";
            }
            $i++;

            if($gen) {
                $sql .= $action.$this->generateColumnQuery($col);
            } else {
                $sql .= $action;
            }
        }
        return $sql;
    }

    /**
     * Добавление столлбцов в запрос создания таблицы
     *
     * @param array $columns
     * @return string
     */
    public function addQueryColumns($columns) {
        $sql = ' ';
        $i = 0;
        foreach($columns as $col) {

            // Верная расстановка запятых
            if($i > 0) {
                $sql .= ", ";
            }
            $i++;

            $sql .= $this->generateColumnQuery($col);
        }
        return $sql;
    }

    public function addQueryIndexes($indexes) {
        $sql = ' ';
        $i = 0;
        foreach($indexes as $ind) {

            // Верная расстановка запятых
            if($i > 0) {
                $sql .= ", ";
            }
            $i++;

            $sql .= $this->generateIndexQuery($ind);
        }
        return $sql;
    }

}