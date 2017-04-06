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

class MigrationCollector
{

    /* @var Config $config */
    protected $config = null;

    /* @var FormatCoder $formatCoder */
    protected $formatCoder = null;

    /* @var OutputInterface $output */
    protected $output = null;

    /* @var StructureParser $structureParser */
    protected $structureParser = null;

    /**
     * MigrationCollector constructor.
     * @param FormatCoder $formatCoder
     * @param OutputInterface $output
     * @param StructureParser $structureParser
     */
    public function __construct(FormatCoder &$formatCoder, OutputInterface &$output, StructureParser &$structureParser)
    {
        $this->config = Config::getInstance();
        $this->formatCoder = $formatCoder;
        $this->output = $output;
        $this->structureParser = $structureParser;
    }

    /**
     * @return bool|mixed
     */
    public function getMigrationData()
    {
        //TODO здесь также применяем разбор из разных типов
        $output = false;
        $path = $this->config->getOption('schema_path');
        $filePath = $path . 'local' . '.' . 'schema' . '.' . 'json';
        $output = json_decode(file_get_contents($filePath), true);
        return $output;
    }

    /**
     * Создание новой миграции
     *
     * @var bool $init - маркер инициализирующей миграции
     */
    public function writeMigration($init = false)
    {
        $localVersion = $globalVersion = null;

        // Чтение информации о локальной версии миграций
        if(file_exists($this->config->getOption('local_version_file').".".$this->config->getOption('format'))) {
            $localVersion = file_get_contents($this->config->getOption('local_version_file').".".$this->config->getOption('format'));
            if($localVersion) {
                $localVersion = $this->formatCoder->decodeData($localVersion, $this->config->getOption('format'));
            }
        }

        //  Чтение информации о глобальной версии файла
        if(file_exists($this->config->getOption('global_version_file').".".$this->config->getOption('format'))) {
            $globalVersion = file_get_contents($this->config->getOption('global_version_file').".".$this->config->getOption('format'));
            if($globalVersion) {
                $globalVersion = $this->formatCoder->decodeData($globalVersion, $this->config->getOption('format'));
            }
        }

        // Если локальная версия не совпадает с глобальной - выводим сообщение о необходимости апдейта
        if($localVersion != $globalVersion) {
            $this->output->writeln('<error>Global ang local versions are different. You need to use migration:migrate to apply latest version to DB.</error>');
        }

        // Если ещё не создано ни локальной ни глобальной версии - создаем стартовую миграцию
        else if (!$localVersion && !$globalVersion) {
            $this->output->writeln('<info>No migrations created. Creating start migration.</info>');
            $this->writeInitMigration();
        }

        // Если версии существуют и они равны - пытаемся содать новую миграцию
        else {
            if($init) {
                $this->output->writeln('<error>Migrations already exists. No need to initialize!</error>');
            } else {
                $this->output->writeln('<info>Try to generate new migration</info>');
                $this->generateMigration();
            }
        }
    }

    /**
     * Создание стартовой миграции
     */
    public function writeInitMigration() {
        $migration = $this->getInitMigration();
        if($migration) {
            if($this->createMigration($migration)) {
                $this->output->writeln('<info>Migration created successfully.</info>');
            }
        }
    }

    public function generateMigration() {
        $newSchema = $this->structureParser->getSchema();
        $startSchema = $this->getInitMigration();
        if($newSchema && $startSchema) {
            $migration = $this->getSchemaDiff($startSchema, $newSchema);
            if($migration) {
                if($this->createMigration($migration)) {
                    $this->output->writeln('<info>Migration created successfully.</info>');
                }
            } else {
                $this->output->writeln('<error>No differences between DB and locsl schema!</error>');
            }
        } else {
            $this->output->writeln('<error>Cannot generate new migration!</error>');
        }
    }

    /**
     * Запись миграции в файл
     *
     * @param array $migration - сгенерированный массив миграции
     * @return bool
     */
    public function createMigration($migration) {
        $output = false;

        $name = strftime($this->config->getOption('time_format'));
        $path = $this->config->getOption('migration_path').$name.'.migration.'.$this->config->getOption('format');
        $migrationString = $this->formatCoder->encodeData($migration, $this->config->getOption('format'));
        if(file_put_contents($path, $migrationString)) {
            if($this->upVersion($name, 'local') && $this->upVersion($name, 'global')) {
                $output = true;
            } else {
                $this->output->writeln('<error>Versions cannot be upated. Need to make it manually!</error>');
            }
        } else {
            $this->output->writeln('<error>Migration '.$name.' not created.</error>');
        }

        return $output;
    }

    /**
     * Обновление информации о версии
     *
     * @param string $version - строка версии
     * @param string $type - тип local ли global
     * @return bool
     */
    public function upVersion($version, $type = 'local') {
        $output = false;

        $versionArray = array('version' => $version);
        $versionString = $this->formatCoder->encodeData($versionArray, $this->config->getOption('format'));
        if($versionString) {
            $path = $this->config->getOption($type.'_version_file').".".$this->config->getOption('format');
            if(file_put_contents($path, $versionString)) {
                $output = true;
            }
        }

        return $output;
    }

    /**
     * Получаем стартовую миграцию
     *
     * @return array
     */
    public function getInitMigration() {
        $migration = array();

        $localSchemaPath = $this->config->getOption('schema_path').'local.schema.'.$this->config->getOption('format');
        if(file_exists($localSchemaPath)) {
            $migrationString = file_get_contents($localSchemaPath);
            if($migrationString) {
                $migration = $this->formatCoder->decodeData($migrationString, $this->config->getOption('format'));
            }
        }

        return $migration;
    }

    /**
     * Получает различия между схемами
     *
     * @param array $startSchenma - начальная схема
     * @param array $newSchema - измененная схема
     * @return array
     */
    public function getSchemaDiff($startSchenma, $newSchema) {
        $diff = array();
        foreach($startSchenma as $tkey => $tvalue) {

            // Если есть таблица с таким же названием
            if(array_key_exists($tkey, $newSchema)) {
                if($sameTable = $this->getTableDiff($tvalue, $newSchema[$tkey])) {
                    $diff[$tkey] = $sameTable;
                }
                unset($newSchema[$tkey]);
            }

            // Если есть таблица с таким же комментарием
            else if($table = $this->searchComment($tvalue['comment'], $newSchema)) {
                if($sameTable = $this->getTableDiff($tvalue, $table)) {
                    $diff[$tkey] = $sameTable;
                }
                unset($newSchema[$tkey]);
            }

            // Таблица удаляется
            else {
                $diff[$tkey] = 'drop';
            }
        }

        // Дальше учтем вновь созданные таблицы
        $diff = array_merge($diff, $newSchema);

        return $diff;
    }

    /**
     * Получает различия между массивами таблиц
     *
     * @param array $startTable - начальный вариант массива таблицы
     * @param array $newTable - измененный вариант массива таблицы
     * @return array
     */
    public function getTableDiff($startTable, $newTable) {
        $diff = array();

        $diff = array_diff_assoc($newTable, $startTable);

        foreach($startTable['columns'] as $ckey => $cvalue) {

            // Если есть столбец с таким же названием
            if(array_key_exists($ckey, $newTable['columns'])) {
                if($sameColumn = $this->getColumnDiff($cvalue, $newTable['columns'][$ckey])) {
                    $diff[$ckey] = $sameColumn;
                }
                unset($newTable['columns'][$ckey]);
            }

            // Если есть таблица с таким же комментарием
            else if($column = $this->searchComment($cvalue['comment'], $newTable['columns'])) {
                if($sameColumn = $this->getColumnDiff($cvalue, $column)) {
                    $diff[$ckey] = $sameColumn;
                }
                unset($newTable['columns'][$ckey]);
            }

            // Таблица удаляется
            else {
                $diff[$ckey]['action'] = 'drop';
            }
        }

        // Дальше учтем вновь созданные столбцы
        $diff = array_merge($diff, $newTable['columns']);

        if($diff) {
            $diff['action'] = 'alter';
        }

        return $diff;
    }

    /**
     * Получает различия между массивами столбца
     *
     * @param array $startColumn - начальный вариант массива столбца
     * @param array $newColumn - измененный вариант массива столбца
     * @return array
     */
    public function getColumnDiff($startColumn, $newColumn) {
        $diff = array();

        if($diff = array_diff_assoc($newColumn, $startColumn)) {
            $diff['action'] = 'change';
        }

        return $diff;
    }

    /**
     * Поиск в масииве такого же комментария
     *
     * @param string $comment - строка комментария
     * @param array $haystack - массив для поиска
     * @return array
     */
    public function searchComment($comment, $haystack) {
        $output = array();

        foreach($haystack as $k => $v) {
            if(array_key_exists('comment', $v)) {
                if($v['comment'] == $comment) {
                    $output = $v;
                }
            }
        }

        return $output;
    }

}