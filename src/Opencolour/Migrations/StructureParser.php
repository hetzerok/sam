<?php
/**
 * Copyright (c) 2017.
 * Roman Gorbunov (hetzerok)
 * hetzerok@gmail.com
 */

namespace Opencolour\Migrations;

use Opencolour\Additions\Config;
use Opencolour\Migrations\FormatCoder;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Парсер структуры существующей БД
 *
 * Class StructureParser
 * @package Opencolour\Migrations
 */
class StructureParser
{
    private $log;

    /* @var Config $config */
    protected $config = null;

    /* @var FormatCoder $formatCoder */
    protected $formatCoder = null;

    /* @var OutputInterface $output */
    protected $output = null;

    /* @var \PDO $pdo */
    protected $pdo = null;

    /* @var array $currentSchema */
    protected $currentSchema = [];

    /**
     * StructureParser constructor.
     *
     * @param FormatCoder $formatCoder
     * @param OutputInterface $output
     */
    public function __construct($log, FormatCoder &$formatCoder, OutputInterface &$output)
    {
        $this->log = $log;
        $this->config = Config::getInstance();
        $this->formatCoder = $formatCoder;
        $this->output = $output;
        $this->pdo = $this->config->getConnection();
    }

    /**
     * Генерирует локальную и глобальную схемы
     * Используется во время инициализации и создания миграции
     */
    public function initializeSchema()
    {
        $schema = $this->getSchema();
        if($schemaData = $this->prepareSchema($schema)) {
            $this->writeSchema($schemaData, 'local');
            $this->writeSchema($schemaData, 'global');
        }

        // В случае включения функционала миграций данных создаем схемы данных
        if ($this->config->getOption('import_data')) {
            $content = $this->getContent($schema);
            if($contentData = $this->prepareContent($content)) {
                $this->writeContent($contentData, 'local');
                $this->writeContent($contentData, 'global');
            }
        }
    }

    /**
     * Генерирует файл локальной схемы
     */
    public function generateLocalSchema()
    {
        $schema = $this->getSchema(true);
        $schemaData = $this->prepareSchema($schema);
        $this->writeSchema($schemaData);
    }

    /**
     * Генерирует файл глобальной схемы
     */
    public function generateGlobalSchema()
    {
        $schema = $this->getSchema(true);
        $schemaData = $this->prepareSchema($schema);
        $this->writeSchema($schemaData, 'global');
    }

    /**
     * Генерирует файл локальных данных
     */
    public function generateLocalContent()
    {
        $schema = $this->getSchema(true);
        $content = $this->getContent($schema);
        $contentData = $this->prepareContent($content);
        $this->writeContent($contentData, 'local');
    }

    /**
     * Генерирует файл глобальных данных
     */
    public function generateGlobalContent()
    {
        $schema = $this->getSchema(true);
        $content = $this->getContent($schema);
        $contentData = $this->prepareContent($content);
        $this->writeContent($contentData, 'global');
    }

    /**
     * Запись данных схемы в файл
     *
     * @param string $data - данные для записи в файл в одном из поддерживаемых форматов
     * @param string $type - тип файла (local - локальная схема, global - глобальная схема)
     * @return bool
     */
    public function writeSchema($data, $type = 'local')
    {
        $output = false;
        $path = $this->config->getOption('schema_path');
        $dataType = $this->config->getOption('schema_format');
        $filePath = $path.$type.'.'.'schema'.'.'.$dataType;
        if (file_put_contents($filePath, $data)) {
            $output = true;
        }

        return $output;
    }

    /**
     * Запись данных содержимого БД в файл
     *
     * @param string $data - данные для записи в файл в одном из поддерживаемых форматов
     * @param string $type - тип файла (local - локальная схема, global - глобальная схема)
     * @return bool
     */
    public function writeContent($data, $type = 'local')
    {
        $output = false;
        $path = $this->config->getOption('schema_path');
        $dataType = $this->config->getOption('schema_format');
        $filePath = $path.$type.'.'.'content'.'.'.$dataType;
        if (file_put_contents($filePath, $data)) {
            $output = true;
        }

        return $output;
    }

    /**
     * Формирование массива схемы текущей БД
     *
     * @param boolean $up - флаг принудительного обновления схемы
     * @return array
     */
    public function getSchema($up = false)
    {
        $schema = [];
        if ($this->currentSchema && !$up) {
            $schema = $this->currentSchema;
        } else {
            $tables = $this->pdo->query('SHOW TABLE STATUS')->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($tables as $tableRow) {

                // Работаем только с таблицами с указанным префиксом
                if (preg_match('/^'.$this->config->getOption('table_prefix').'/', $tableRow['Name'])) {

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
            $this->currentSchema = $schema;
        }

        return $schema;
    }

    /**
     * Формирование массива содержимого текущей БД
     * @param array $schema - схема этой БД
     * @return array
     */
    public function getContent($schema)
    {
        $content = [];

        // Работем только с таблицами, указанныме в конфиге в параметре import_data_tables
        $tables = $this->config->getOption('import_data_tables');
        if (is_array($tables)) {

            // Получим реальные таблицы из БД чтобы мзбежать запросов к несуществующим таблицам
            $realTables = $this->pdo->query('SHOW TABLE STATUS')->fetchAll(\PDO::FETCH_ASSOC);
            $tableArray = [];
            foreach ($realTables as $tableRow) {

                // Работаем только с таблицами с указанным префиксом
                if (preg_match('/^'.$this->config->getOption('table_prefix').'/', $tableRow['Name'])) {
                    $tableArray[] = mb_substr($tableRow['Name'], mb_strlen($this->config->getOption('table_prefix')));
                }
            }

            // Получаем содержимое для каждой указанной валидной таблицы
            foreach ($tables as $tableName) {
                if(array_search($tableName, $tableArray) !== false) {
                    $index = $schema[$tableName]['indexes']['PRIMARY'];
                    $tableData = $this->getTableData($tableName, $index);
                    $content[$tableName] = $tableData;
                }
            }
        }
        return $content;
    }

    /**
     * Формирование массива данных для таблицы
     *
     * @param $tableName - имя таблицы (без префикса)
     * @param $index - массив первичного ключа
     * @return array
     */
    public function getTableData($tableName, $index)
    {
        $tableData = [];

        // Заполняем массив названий столбцов
        $columns = $this->pdo->query(
            'SHOW FULL COLUMNS FROM `'.$this->config->getOption('table_prefix').$tableName.'`'
        )->fetchAll(\PDO::FETCH_ASSOC);
        $cols = [];
        foreach ($columns as $col) {
            $cols[] = $col['Field'];
        }

        // Если столбцы имеются - получаем данные
        if ($cols) {
            $tableData['columns'] = $cols;

            // Если есть индекс - создаем запись о его столбцах
            $pk = [];
            if ($index && is_array($index)) {
                $cols = $index['columns'];
                if (is_array($cols)) {
                    foreach ($cols as $col) {
                        if (array_search($col['name'], $tableData['columns']) !== false) {
                            $pk[] = $col['name'];
                        }
                    }
                }
            }
            $tableData['pk'] = $pk;

            $rawData = $this->pdo->query(
                'SELECT * FROM `'.$this->config->getOption('table_prefix').$tableName.'`'
            )->fetchAll(\PDO::FETCH_NUM);
            $tableData['data'] = [];
            if ($tableData['pk']) {
                foreach ($rawData as $row) {
                    $keymap = [];
                    foreach ($tableData['pk'] as $v) {
                        $k = array_search($v, $tableData['columns']);
                        if ($k !== false) {
                            $keymap[] = $row[$k];
                        }
                    }
                    $key = implode('_', $keymap);
                    $tableData['data'][$key]['row'] = $row;
                }
            } else {
                foreach ($rawData as $row) {
                    $tableData['data'][]['row'] = $row;
                }
            }
        }

        // Если есть значение автоинкремента то записываем и его
        $ai = 0;
        $state = $this->pdo->query(
            "SHOW TABLE STATUS like '".$this->config->getOption('table_prefix').$tableName."';"
        )->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($state as $stat) {
            $ai = $stat['Auto_increment'];
        }
        if ($ai) {
            $tableData['ai'] = $ai;
        }
        return $tableData;
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
        $columns = $this->pdo->query(
            'SHOW FULL COLUMNS FROM `'.$this->config->getOption('table_prefix').$tableName.'`'
        )->fetchAll(\PDO::FETCH_ASSOC);
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
    public function prepareColType($type)
    {
        switch ($type) {
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

    /**
     * Получает структуру индексов таблицы
     *
     * @param string $tableName - имя таблицы (без префикса)
     * @param array $tableArray - массив структуры таблицы
     * @return mixed
     */
    public function getTableIndexes($tableName, $tableArray)
    {
        $indexes = $this->pdo->query(
            'SHOW INDEXES FROM `'.$this->config->getOption('table_prefix').$tableName.'`'
        )->fetchAll(\PDO::FETCH_ASSOC);
        $indArray = [];
        foreach ($indexes as $ind) {
            if (!array_key_exists($ind['Key_name'], $indArray)) {
                $indArray[$ind['Key_name']] = [
                    'name' => $ind['Key_name'],
                    'columns' => array(
                        $ind['Seq_in_index'] => array(
                            'name' => $ind['Column_name'],
                            'sub_part' => $ind['Sub_part'],
                        ),
                    ),
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

    /**
     * Получает структуру внешних ключей таблицы
     *
     * @param string $tableName - имя таблицы (без префикса)
     * @param array $tableArray - массив структуры таблицы
     * @return mixed
     */
    public function getTableConstraints($tableName, $tableArray)
    {
        $constraints = $this->pdo->query(
            'SHOW CREATE TABLE `'.$this->config->getOption('table_prefix').$tableName.'`'
        )->fetchAll(\PDO::FETCH_COLUMN, 1);
        $create_table = $constraints[0];
        $matches = array();
        //$regexp = '/CONSTRAINT\s+([^\(^\s]+)\s+FOREIGN KEY\s+\(([^\)]+)\)\s+REFERENCES\s+([^\(^\s]+)\s*\(([^\)]+)\)/mi';
        $regexp = '/CONSTRAINT\s+([^\(^\s]+)\s+FOREIGN KEY\s+\(([^\)]+)\)\s+REFERENCES\s+([^\(^\s]+)\s*\(([^\)]+)\)\s*(.*)(\;|\,|\s\))/mi';
        preg_match_all($regexp, $create_table, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $name = str_replace(array('`', '"'), '', $match[1]);
            $foreign = str_replace(array('`', '"'), '', $match[3]);
            $keys = array_map('trim', explode(',', str_replace(array('`', '"'), '', $match[2])));
            $fks = array_map('trim', explode(',', str_replace(array('`', '"'), '', $match[4])));
            $add = trim($match[5]);

            // Учитываем связи только для таблиц с заданным префиксом
            if (preg_match('/^'.$this->config->getOption('table_prefix').'/', $foreign)) {
                $foreign = mb_substr($foreign, mb_strlen($this->config->getOption('table_prefix')));
                foreach ($keys as $k => $val) {
                    $tableArray['foreignKeys'][$name]['name'] = $name;
                    $tableArray['foreignKeys'][$name]['foreignTable'] = $foreign;
                    $tableArray['foreignKeys'][$name]['localKeys'] = $keys;
                    $tableArray['foreignKeys'][$name]['foreignKeys'] = $fks;
                    $tableArray['foreignKeys'][$name]['add'] = $add;
                    if (isset($tableArray['columns'][$name])) {
                        $tableArray['columns'][$name]['isForeignKey'] = true;
                    }
                }
            }
        }

        return $tableArray;
    }

    /**
     * Преобразует данные схемы в заданный через конфиг формат
     *
     * @param array $schema - массив схемы
     * @return string
     */
    public function prepareSchema($schema)
    {
        $schemaData = $this->formatCoder->encodeData($schema, $this->config->getOption('schema_format'));

        return $schemaData;
    }

    /**
     * Преобразует данные содержимого БД в заданный через конфиг формат
     *
     * @param array $content - массив содержимого
     * @return string
     */
    public function prepareContent($content)
    {
        $contentData = $this->formatCoder->encodeData($content, $this->config->getOption('schema_format'));

        return $contentData;
    }
}