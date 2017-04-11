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
                $sql = "CREATE TABLE `".$this->config->getOption('table_prefix').$table['name']."`";

                // Добавляем столбцы к таблице
                $csql = $this->addQueryColumns($table['columns']);

                if (mb_strlen($csql) > 3) {
                    $csql .= ',';
                }
                $csql .= $this->addQueryIndexes($table['indexes']);

                // Оборачиваем в скобки добавленные столбцы
                if ($csql) {
                    $sql .= " (".$csql.")";
                }

                $sql .= " COMMENT='".$table['comment']
                    ."' COLLATE='".$table['collation']
                    ."' ENGINE='".$table['engine']."';";

                $this->pdo->query($sql);

                // Таблица уже создана, действуем через ALTER
            } else {
                if ($table['action']) {

                    // Изменяем опции и столбцы таблицы
                    if ($table['action'] == 'alter') {
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
                    } // Удаляем таблицу
                    else {
                        if ($table['action'] == 'drop') {
                            $sql = "DROP TABLE `".$this->config->getOption('table_prefix').$table_key."`;";
                            $this->pdo->query($sql);
                            unlink($schema[$table_key]);
                        }
                    }
                }
            }

            // Создаем необходимые pk
            // Создаем индексы
        }

        // Цикл для добавления constraints (чтобы быть уверенными в существовании столбцов)
        foreach ($schema as $table) {

        }
    }

    public function generateColumnQuery($col)
    {

        // NULL или NOT NULL
        if ($col['nullable']) {
            $null = ' NULL';
        } else {
            $null = ' NOT NULL';
        }

        // Значение по умолчанию
        if ($col['default'] === null) {
            if ($col['nullable']) {
                $default = " DEFAULT NULL";
            } else {
                $default = "";
            }
        } else {
            if ($col['default'] == 'CURRENT_TIMESTAMP') {
                $default = " DEFAULT CURRENT_TIMESTAMP";
            } else {
                $default = " DEFAULT '".$col['default']."'";
            }
        }

        // Auto Increment
        $ai = "";
        if ($col['autoIncrement']) {
            $ai = ' AUTO_INCREMENT';
        }

        // Комментарий если есть
        $comment = '';
        if ($col['comment']) {
            $comment = " COMMENT '".$col['comment']."'";
        }

        $sql = "`".$col['name']."` ".strtoupper($col['type']).$null.$default.$ai.$comment;

        return $sql;
    }

    /**
     * Действия над столбцами в запросе изменения таблицы
     *
     * @param array $columns
     * @return string
     */
    public function alterQueryColumns($columns)
    {
        $sql = ' ';
        $i = 0;
        foreach ($columns as $key => $col) {

            $gen = false;
            $action = '';
            if ($col['action']) {
                if ($col['action'] == 'drop') {
                    $action = "DROP COLUMN `".$key."``";
                } else {
                    if ($col['action'] == 'change') {
                        $action = "CHANGE `".$key."` ";
                        $gen = true;
                    }
                }
            } else {
                $action = "ADD COLUMN `".$key."` ";
                $gen = true;
            }

            // Верная расстановка запятых
            if ($i > 0) {
                $sql .= ", ";
            }
            $i++;

            if ($gen) {
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
    public function addQueryColumns($columns)
    {
        $sql = ' ';
        $i = 0;
        foreach ($columns as $col) {

            // Верная расстановка запятых
            if ($i > 0) {
                $sql .= ", ";
            }
            $i++;

            $sql .= $this->generateColumnQuery($col);
        }

        return $sql;
    }

    /**
     * Создание SQL кода вида индекса
     *
     * @param array $ind - массив одного индекса
     * @return string
     */
    public function getIndexVid($ind)
    {

        // Вид индекса
        if ($ind['name'] == 'PRIMARY') {
            $vid = 'PRIMARY KEY ';
        } else {
            if ($ind['type'] == 'FULLTEXT') {
                $vid = 'FULLTEXT INDEX `'.$ind['name'].'`';
            } else {
                $vid = 'INDEX `'.$ind['name'].'`';
            }
        }

        return $vid;
    }

    /**
     * Создание SQL записи для индекса
     *
     * @param array $ind - массив одного индекса
     * @return string
     */
    public function generateIndexQuery($ind)
    {

        $vid = $this->getIndexVid($ind).' ';

        // Столбцы
        $col = '';
        $i = 0;
        foreach ($ind['columns'] as $column) {

            // Верная расстановка запятых
            if ($i > 0) {
                $col .= ", ";
            }
            $i++;

            if ($column['sub_part']) {
                $col .= "`".$column['name']."`(".$column['sub_part'].")";
            } else {
                $col .= "`".$column['name']."`";
            }

        }
        if ($col) {
            $col = '('.$col.')';
        }

        $sql = $vid.$col;

        return $sql;
    }

    /**
     * Действия над столбцами в запросе изменения таблицы
     *
     * @param array $indexes - массив индексов
     * @return string
     */
    public function alterQueryIndexes($indexes)
    {
        $sql = ' ';
        $i = 0;
        foreach ($indexes as $ind) {

            // Верная расстановка запятых
            if ($i > 0) {
                $sql .= ", ";
            }
            $i++;

            if ($ind['action']) {
                $vid = $this->getIndexVid($ind);
                $action = 'DROP '.$vid;
                if ($ind['action'] = 'change') {
                    $action .= ', ADD '.$this->generateIndexQuery($ind);
                }
            } else {
                $action = "ADD ".$this->generateIndexQuery($ind);
            }

            $sql .= $action;
        }

        return $sql;
    }

    /**
     * Добавление индексов в запрос создания таблицы
     *
     * @param array $indexes -  массив индексов
     * @return string
     */
    public function addQueryIndexes($indexes)
    {
        $sql = ' ';
        $i = 0;
        foreach ($indexes as $ind) {

            // Верная расстановка запятых
            if ($i > 0) {
                $sql .= ", ";
            }
            $i++;

            $sql .= $this->generateIndexQuery($ind);
        }

        return $sql;
    }

}