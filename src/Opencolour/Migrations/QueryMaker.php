<?php
/**
 * Copyright (c) 2017.
 * Roman Gorbunov (hetzerok)
 * hetzerok@gmail.com
 */

namespace Opencolour\Migrations;

use Opencolour\Additions\Config;
use Symfony\Component\Console\Output\OutputInterface;

class QueryMaker
{

    /* @var Config $config */
    protected $config = null;

    /* @var \PDO $pdo */
    protected $pdo = null;

    /* @var OutputInterface $output */
    protected $output = null;

    /**
     * QueryMaker constructor.
     */
    public function __construct(OutputInterface &$output)
    {
        $this->config = Config::getInstance();
        $this->pdo = $this->config->getConnection();
        $this->output = $output;
    }

    public function contentQuery($content)
    {
        $flag = true;

        $k_u = 0;
        $k_i = 0;
        $k_d = 0;
        $k_u_n = 0;
        $k_i_n = 0;
        $k_d_n = 0;
        foreach ($content as $key => $value) {

            // Полная очистка таблицы если необходимо
            if ($value['clear']) {
                $sql = "TRUNCATE `".$this->config->getOption('table_prefix').$key."`;";
                if (!$this->pdo->query($sql)) {
                    $flag = false;
                }
            }

            $insert = [];
            $update = [];
            $delete = [];
            if(array_key_exists('data', $value)) {
                foreach ($value['data'] as $rkey => $rvalue) {
                    if ($rvalue['action'] == 'remove') {
                        $delete[] = $rvalue['row'];
                    } else {
                        if ($rvalue['action'] == 'change') {
                            $update[] = $rvalue['row'];
                        } else {
                            $insert[] = $rvalue['row'];
                        }
                    }
                }
            }

            // Удаление строк
            if ($delete) {
                foreach ($delete as $drow) {
                    if ($where = $this->makeWhereExpression($drow, $value['columns'], $value['pk'])) {
                        $sql = "DELETE FROM `".$this->config->getOption('table_prefix').$key."` WHERE ".$where.";";
                        if ($this->pdo->query($sql)) {
                            $k_d++;
                        } else {
                            $k_d_n++;
                        }
                    } else {
                        $k_d_n++;
                    }
                }
            }

            // Обновление строк
            if ($update) {
                foreach ($update as $urow) {
                    if ($where = $this->makeWhereExpression($urow, $value['columns'], $value['pk'])) {
                        $set = $this->makeSetExpression($urow, $value['columns'], $value['pk']);
                        $sql = "UPDATE ".$this->config->getOption('table_prefix').$key." SET ".$set." WHERE ".$where.";";
                        if ($this->pdo->query($sql)) {
                            $k_u++;
                        } else {
                            $k_u_n++;
                        }
                    } else {
                        $k_u_n++;
                    }
                }
            }

            // Добавление новых строк
            if ($insert) {
                foreach($insert as $irow) {
                    if($ins = $this->makeInsertExpression($irow, $value['columns'])) {
                        $sql = "INSERT INTO `".$this->config->getOption('table_prefix').$key."` ".$ins.";";
                        if ($this->pdo->query($sql)) {
                            $k_i++;
                        } else {
                            $k_i_n++;
                        }
                    } else {
                        $k_i_n++;
                    }
                }
            }

            // Изменение автоинкремента
            if($value['ai']) {
                $sql = "ALTER TABLE `".$this->config->getOption('table_prefix').$key."`	AUTO_INCREMENT=".$value['ai'].";";
                $this->pdo->query($sql);
            }
        }

        $this->output->writeln('<comment>Deleted '.$k_d.' successfully and '.$k_d_n.' unsuccessfully.</comment>');
        $this->output->writeln('<comment>Updated '.$k_u.' successfully and '.$k_u_n.' unsuccessfully.</comment>');
        $this->output->writeln('<comment>Inserted '.$k_i.' successfully and '.$k_i_n.' unsuccessfully.</comment>');

        return $flag;
    }

    public function makeInsertExpression($row, $cols) {
        $ins = '';
        $colmap = [];
        $valmap = [];
        foreach ($row as $k => $v) {
            $colmap[] = "`".$cols[$k]."`";
            $valmap[] = "'".$v."'";
        }
        $ins = "(".implode(', ', $colmap).") VALUES (".implode(', ', $valmap).")";

        return $ins;
    }

    public function makeSetExpression($row, $cols, $pk)
    {
        $set = '';
        $keymap = [];
        if ($pk) {
            foreach ($row as $k => $v) {
                $key = array_search($v, $pk);
                if ($key === false) {
                    $keymap[] = "`".$cols[$k]."`='".$row[$k]."'";
                }
            }
        } // В общем случае обновление без ключа не имеет смысла, но на всякий пусть будет
        else {
            foreach ($row as $k => $v) {
                $keymap[] = "`".$cols[$k]."`='".$v."'";
            }
        }
        $set = implode(', ', $keymap);

        return $set;
    }

    public function makeWhereExpression($row, $cols, $pk)
    {
        $where = '';
        $keymap = [];
        if ($pk) {
            foreach ($pk as $v) {
                $k = array_search($v, $cols);
                if ($k !== false) {
                    $keymap[] = "`".$cols[$k]."`='".$row[$k]."'";
                }
            }
        } else {
            foreach ($row as $k => $v) {
                $keymap[] = "`".$cols[$k]."`='".$v."'";
            }
        }
        $where = implode(' AND ', $keymap);

        return $where;
    }

    public function schemaQuery($schema)
    {
        $flag = true;

        // Цикл создания и заполнения таблиц
        foreach ($schema as $table_key => $table) {

            // Если все запросы до этого прошли удачно
            if ($flag) {

                // Необходимо создать таблицу
                if (!$table['action'] && $table['columns']) {
                    $sql = "CREATE TABLE `".$this->config->getOption('table_prefix').$table['name']."`";

                    // Добавляем столбцы к таблице
                    $csql = $this->addQueryColumns($table['columns']);

                    // Добавляем индексы к таблице
                    if ($table['indexes']) {
                        $csql .= ','.$this->addQueryIndexes($table['indexes']);
                    }

                    // Оборачиваем в скобки добавленные столбцы
                    if ($csql) {
                        $sql .= " (".$csql.")";
                    }

                    $sql .= " COMMENT='".$table['comment']
                        ."' COLLATE='".$table['collation']
                        ."' ENGINE='".$table['engine']."';";

                    if (!$this->pdo->query($sql)) {
                        $flag = false;
                    }

                    // Таблица уже создана, действуем через ALTER
                } else {

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

                        // Если заданы столбцы - изменяем их
                        if ($table['columns']) {

                            if ($i > 0) {
                                $sql .= ",";
                            }
                            $i++;

                            $sql .= $this->alterQueryColumns($table['columns']);
                        }

                        // Если заданы индексы - изменяем их
                        if ($table['indexes']) {

                            if ($i > 0) {
                                $sql .= ",";
                            }

                            $sql .= $this->alterQueryIndexes($table['indexes']);

                        }

                        $sql .= ';';

                        if (!$this->pdo->query($sql)) {
                            $flag = false;
                        }
                    } // Удаляем таблицу
                    else {
                        if ($table['action'] == 'drop') {
                            $sql = "DROP TABLE `".$this->config->getOption('table_prefix').$table_key."`;";
                            if (!$this->pdo->query($sql)) {
                                $flag = false;
                            }
                            unset($schema[$table_key]);
                        }
                    }
                }
            }
        }

        // Цикл для добавления constraints (чтобы быть уверенными в существовании столбцов)
        foreach ($schema as $table_key => $table) {

            if ($flag) {
                if ($table['foreignKeys']) {
                    $sql = "ALTER TABLE `".$this->config->getOption(
                            'table_prefix'
                        ).$table_key."`".$this->alterQueryForeignKeys($table['foreignKeys']).";";
                    if (!$this->pdo->query($sql)) {
                        $flag = false;
                    }
                }
            }
        }

        return $flag;
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

        $sql = strtoupper($col['type']).$null.$default.$ai.$comment;

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

            // Определяем новое имя столбца
            $name = $key;
            if ($col['name']) {
                $name = $col['name'];
            }

            $gen = false;
            $action = '';
            if ($col['action']) {
                if ($col['action'] == 'drop') {
                    $action = "DROP COLUMN `".$key."``";
                } else {
                    if ($col['action'] == 'change') {
                        $action = "CHANGE COLUMN `".$key."` `".$name."` ";
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

            $sql .= "`".$col['name']."` ".$this->generateColumnQuery($col);
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
            $vid = 'PRIMARY KEY';
        } else {
            if ($ind['type'] == 'FULLTEXT') {
                $vid = 'FULLTEXT INDEX `'.$ind['name'].'`';
            } else {
                if ($ind['type'] == 'SPATIAL') {
                    $vid = 'SPATIAL INDEX `'.$ind['name'].'`';
                } else {
                    if ($ind['unique']) {
                        $vid = 'UNIQUE INDEX `'.$ind['name'].'`';
                    } else {
                        $vid = 'INDEX `'.$ind['name'].'`';
                    }
                }
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
     * Действия над индексами в запросе изменения таблицы
     *
     * @param array $indexes - массив индексов
     * @return string
     */
    public function alterQueryIndexes($indexes)
    {
        $sql = ' ';
        $i = 0;
        foreach ($indexes as $key => $ind) {

            // Добавляем имя из ключа если нужно
            if (!array_key_exists('name', $ind)) {
                $ind['name'] = $key;
            }

            // Верная расстановка запятых
            if ($i > 0) {
                $sql .= ", ";
            }
            $i++;

            if ($ind['action']) {
                $vid = $this->getIndexVid($ind);
                $action = 'DROP '.$vid;
                if ($ind['action'] == 'change') {
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

    /**
     * Создание SQL кода вида внешнего ключа
     *
     * @param array $key - массив одного ключа
     * @return string
     */
    public function getForeignKeyVid($key)
    {

        $vid = 'CONSTRAINT `'.$key['name'].'`';

        return $vid;
    }

    /**
     * Создание SQL записи для внешнего ключа
     *
     * @param array $key - массив одного внешнего ключа
     * @return string
     */
    public function generateForeignKeyQuery($key)
    {

        $vid = $this->getForeignKeyVid($key).' ';

        // Локальные столбцы
        $local = '';
        $i = 0;
        foreach ($key['localKeys'] as $value) {

            // Верная расстановка запятых
            if ($i > 0) {
                $local .= ", ";
            }
            $i++;

            $local .= "`".$value."`";
        }
        if ($local) {
            $local = 'FOREIGN KEY ('.$local.')';
        }

        // Связанные столбцы
        $foreign = '';
        $i = 0;
        foreach ($key['foreignKeys'] as $value) {

            // Верная расстановка запятых
            if ($i > 0) {
                $local .= ", ";
            }
            $i++;

            $foreign .= "`".$value."`";
        }
        if ($foreign) {
            $foreign = 'REFERENCES `'.$this->config->getOption('table_prefix').$key['foreignTable'].'` ('.$foreign.')';
        }

        $sql = $vid.$local.' '.$foreign;

        // Дополднительное
        if ($key['add']) {
            $sql .= ' '.$key['add'];
        }

        return $sql;
    }

    /**
     * Действия над внешними ключами
     *
     * @param array $keys - массив внешних ключей
     * @return string
     */
    public function alterQueryForeignKeys($keys)
    {
        $sql = ' ';
        $i = 0;
        foreach ($keys as $k => $key) {

            // Добавляем имя из ключа если нужно
            if (!array_key_exists('name', $key)) {
                $key['name'] = $k;
            }

            // Верная расстановка запятых
            if ($i > 0) {
                $sql .= ", ";
            }
            $i++;

            if ($key['action']) {
                $vid = $this->getForeignKeyVid($key);
                $action = 'DROP '.$vid;
                if ($key['action'] == 'change') {
                    $action .= ', ADD '.$this->generateForeignKeyQuery($key);
                }
            } else {
                $action = "ADD ".$this->generateForeignKeyQuery($key);
            }

            $sql .= $action;
        }

        return $sql;
    }

}