<?php
    /**
     * Copyright (c) 2017.
     * Roman Gorbunov (hetzerok)
     * hetzerok@gmail.com
     */

namespace Opencolour\Migrations;

    use Opencolour\Additions\Config;

    /**
     * Парсер структуры существующей БД
     *
     * Class StructureParser
     * @package Opencolour\Migrations
     */
class StructureParser
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

    /**
     * Генерирует локальную и глобальную схемы
     * Используется во время инициализации и создания миграции
     */
    public function initializeSchema() {
        $schema = $this->getSchema();
        $schemaData = $this->prepareSchema($schema);
        $this->writeSchema($schemaData);
        $this->writeSchema($schemaData, 'global');
    }

    /**
     * Генерирует файл локальной схемы
     */
    public function generateLocalSchema()
    {
        $schema = $this->getSchema();
        $schemaData = $this->prepareSchema($schema);
        $this->writeSchema($schemaData);
    }

    /**
     * Генерирует файл глобальной схемы
     */
    public function generateGlobalSchema()
    {
        $schema = $this->getSchema();
        $schemaData = $this->prepareSchema($schema);
        $this->writeSchema($schemaData, 'global');
    }

    /**
     * Запись данных схемы в файл
     *
     * @param string $data - данные для записи в файл в одном из поддерживаемых форматов
     * @param string $type - тип файла (local - локальная схема, global - глобальная схема)
     * @param string $dataType - формат запизи (xml, json, php и т.д. для установки верного расширения)
     * @return bool
     */
    public function writeSchema($data, $type = 'local', $dataType = 'json') {
        $output = false;
        $path = $this->config->getOption('schema_path');
        $filePath = $path . $type . '.' . 'schema' . '.' . $dataType;
        if(file_put_contents($filePath, $data)) {
            $output = true;
        }
        return $output;
    }

    /**
     * Получение схемы текущей бд
     *
     * @return array
     */
    public function getSchema()
    {
        $schema = array();
        $tables = $this->pdo->query('SHOW TABLE STATUS')->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($tables as $tableRow) {

            // Работаем только с таблицами с указанным префиксом
            if(preg_match('/^' . $this->config->getOption('table_prefix') . '/', $tableRow['Name'] )) {

                $tableName = mb_substr($tableRow['Name'], mb_strlen($this->config->getOption('table_prefix')));

                // Создаем изначальную структуру элемента схемы
                $tableArray = [
                    'name' => $tableName,
                    'engine' => $tableRow['Engine'],
                    'collation' => $tableRow['Collation'],
                    'comment' => $tableRow['Comment'],
                    'primaryKey' => null,
                    'columns' => [],
                    'indexes' => [],
                    'foreignKeys' => [],
                ];

                // Добавляем структуру столбцов
                $tableArray = $this->getTableColumns($tableName, $tableArray);

                // Добавляем индексы
                if ($this->config->getOption('conside_indexes')) {
                    $tableArray = $this->getTableIndexes($tableName, $tableArray);
                }

                // Добавляем связи по внешним ключам
                if ($this->config->getOption('conside_foreign_keys')) {
                    $tableArray = $this->getTableConstraints($tableName, $tableArray);
                }

                $schema[$tableName] = $tableArray;
            }
        }

        return $schema;
    }

    /**
     * Добавление информации по столбцам к таблице
     *
     * @param string $tableName - название таблицы
     * @param array $tableArray - массив данных по таблице
     * @return array
     */
    public function getTableColumns($tableName, $tableArray)
    {
        $columns = $this->pdo->query('SHOW FULL COLUMNS FROM `' . $this->config->getOption('table_prefix') . $tableName . '`')->fetchAll(\PDO::FETCH_ASSOC);
        $colArray = [];
        foreach ($columns as $col) {
            $isPrimaryKey = strpos($col['Key'], 'PRI') !== false;
            $type = $this->prepareColType($col['Type']);
            $colArray[$col['Field']] = [
                'name' => $col['Field'],
                'type' => $type,
                'nullable' => $col['Null'] === 'YES',
                'default' => $col['Default'],
                'comment' => $col['Comment'],
                'isPrimaryKey' => $isPrimaryKey,
                'isForeignKey' => false,
                'relation' => [],
                'autoIncrement' => strpos(strtolower($col['Extra']), 'auto_increment') !== false,
            ];
            if ($isPrimaryKey) {
                if ($tableArray['primaryKey'] === null) {
                    $tableArray['primaryKey'] = $col['Field'];
                } elseif (is_string($tableArray['primaryKey'])) {
                    $tableArray['primaryKey'] = [$tableArray['primaryKey'], $col['Field']];
                } else {
                    $tableArray['primaryKey'][] = $col['Field'];
                }
            }
        }
        $tableArray['columns'] = $colArray;

        return $tableArray;
    }

    /**
     * Преобразует тип столбца для использования в MYSQL запросах
     *
     * @param string $type - тип стлобца в формате полученном через SHOW_COLUMNS
     * @return string
     */
    public function prepareColType($type) {
        switch($type) {
            case 'full_text':
                $type = 'fulltext';
                break;
            case 'medium_text':
                $type = 'mediumtext';
                break;
            case 'tiny_text':
                $type = 'tinytext';
                break;
        }

        return $type;
    }

    public function getTableIndexes($tableName, $tableArray)
    {
        $indexes = $this->pdo->query('SHOW INDEXES FROM `' . $this->config->getOption('table_prefix') . $tableName . '`')->fetchAll(\PDO::FETCH_ASSOC);
        $indArray = [];
        foreach ($indexes as $ind) {
            if(!array_key_exists($ind['Key_name'], $indArray)) {
                $indArray[$ind['Key_name']] = [
                    'name' => $ind['Key_name'],
                    'columns' => array($ind['Seq_in_index'] => array(
                        'name' => $ind['Column_name'],
                        'sub_part' => $ind['Sub_part'],
                    )),
                    'unique' => !$ind['Non_unique'],
                    'type' => $ind['Index_type'],
                    'comment' => $ind['Index_comment'],
                    'null' => $ind['NULL'],

                ];
            } else {
                $indArray[$ind['Key_name']]['columns'][$ind['Seq_in_index']] = array(
                    'name' => $ind['Column_name'],
                    'sub_part' => $ind['Sub_part'],
                );
            }
        }
        $tableArray['indexes'] = $indArray;

        return $tableArray;
    }

    public function getTableConstraints($tableName, $tableArray)
    {
        $constraints = $this->pdo->query('SHOW CREATE TABLE `' . $this->config->getOption('table_prefix') . $tableName . '`')->fetchAll(\PDO::FETCH_COLUMN, 1);
        $create_table = $constraints[0];
        $matches = array();
        $regexp = '/CONSTRAINT\s+([^\(^\s]+)\s+FOREIGN KEY\s+\(([^\)]+)\)\s+REFERENCES\s+([^\(^\s]+)\s*\(([^\)]+)\)/mi';
        preg_match_all($regexp, $create_table, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $name = str_replace(array('`', '"'), '', $match[1]);
            $foreign = str_replace(array('`', '"'), '', $match[3]);
            $keys = array_map('trim', explode(',', str_replace(array('`', '"'), '', $match[2])));
            $fks = array_map('trim', explode(',', str_replace(array('`', '"'), '', $match[4])));

            // Учитываем связи только для таблиц с заданным префиксом
            if(preg_match('/^' . $this->config->getOption('table_prefix') . '/', $foreign )) {
                $foreign = mb_substr($foreign, mb_strlen($this->config->getOption('table_prefix')));
                foreach ($keys as $k => $val) {
                    $tableArray['foreignKeys'][$name]['name'] = $name;
                    $tableArray['foreignKeys'][$name]['foreignTable'] = $foreign;
                    $tableArray['foreignKeys'][$name]['localKeys'] = $keys;
                    $tableArray['foreignKeys'][$name]['foreignKeys'] = $fks;
                    if (isset($tableArray['columns'][$name])) {
                        $tableArray['columns'][$name]['isForeignKey'] = true;
                    }
                }
            }
        }

        return $tableArray;
    }

//    public function normalize(&$schema)
//    {
//        foreach ($schema as &$table) {
//            if (count($table['foreignKeys']) > 0) {
//                foreach ($table['foreignKeys'] as $k => $v) {
//                    list($targetTable, $targetColumn) = $v;
//                    $table['columns'][$k]['relation'] = [
//                        'type' => 'belongs-to-one',
//                        'table' => $targetTable,
//                    ];
//                    //update other side of relation
//                    $schema[$targetTable]['relations'][$table['name']] = [
//                        'type' => 'has-many',
//                        'table' => $table['name'],
//                        'column' => $k,
//                    ];
//                }
//            }
//        }
//        //working out many-to-many connector tables
//        foreach ($schema as &$table) {
//            $isRelationTable = true;
//            if (
//                count($table['foreignKeys']) > 0 && //if it has foreign keys
//                count($table['foreignKeys']) == 2 && // only 2 foreign keys
//                is_array($table['primaryKey']) && // has more than one primary key
//                count($table['primaryKey']) >= 2 // at least 2 primary keys
//            ) {
//                //if all it's foreign keys are also primary keys
//                foreach ($table['foreignKeys'] as $k => $fk) {
//                    if (!in_array($k, $table['primaryKey'])) {
//                        $isRelationTable = false;
//                        break;
//                    }
//                }
//            } else {
//                $isRelationTable = false;
//            }
//            if ($isRelationTable) {
//                $i = 0;
//                $referencedTables = [];
//                $connections = [];
//                foreach ($table['foreignKeys'] as $k => $fk) {
//                    $referencedTables[$i] = $fk[0];
//                    $connections[$i] = $k;
//                    $i++;
//                }
//                unset($schema[$referencedTables[0]]['relations'][$table['name']]);
//                unset($schema[$referencedTables[1]]['relations'][$table['name']]);
//                $schema[$referencedTables[1]]['relations'][$referencedTables[0]] = [
//                    'type' => 'has-many',
//                    'table' => $referencedTables[0],
//                    'column' => $connections[1],
//                    'via' => $table['name'],
//                    'selfColumn' => $connections[0],
//                ];
//                $schema[$referencedTables[0]]['relations'][$referencedTables[1]] = [
//                    'type' => 'has-many',
//                    'table' => $referencedTables[1],
//                    'column' => $connections[0],
//                    'via' => $table['name'],
//                    'selfColumn' => $connections[1],
//                ];
//                foreach ($schema[$table['name']]['columns'] as $k => &$v) {
//                    unset($v['relation']);
//                }
//                $schema[$table['name']]['is_connector_table'] = true;
//            }
//        }
//    }

    public function prepareSchema($schema)
    {
        $schemaData = json_encode($schema);
        return $schemaData;
    }
}