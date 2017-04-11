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
     * Преобразование строкового представления ключа миграции в timestamp
     *
     * @param string $strKey
     * @return bool|int
     */
    public function getIntKey($strKey) {
        $output = false;

        if($date = \DateTime::createFromFormat($this->config->getOption('time_format'), $strKey)) {
            $output = $date->getTimestamp();
        }

        return $output;
    }

    /**
     * Получает массив миграций в той последовательности, которой должно идти применение.
     * Возвращаются миграции начиная со следующей от той, которая указана в локальной версии.
     * Если передан параметр $last - последняя миграция будет с данным ключем или меньше него
     * (в случае если миграции с таким ключем не существует).
     * Если нет, то последняя миграция будет браться из глобальной версии.
     *
     * @param string $last - ключ последней миграции, до которой идет применение
     * @return array
     */
    public function getCurrentMigrations($last = '') {

        $output = [];

        // Чтение информации о локальной версии миграций
        $localVersion = $this->getVersion('local');

        //  Чтение информации о глобальной версии файла
        $globalVersion = $this->getVersion('global');

        if($globalVersion) {
            if(array_key_exists('version', $globalVersion)) {

                $initVersion = '00000000_000000';
                if($localVersion) {
                    if(array_key_exists('version', $localVersion)) {
                        $initVersion = $localVersion['version'];
                    }
                }

                if($this->getIntKey($initVersion) <= $this->getIntKey($globalVersion['version'])) {

                    $dir = opendir($this->config->getOption('migration_path'));
                    $files = [];
                    while ($file = readdir($dir)) {
                        if (preg_match('/\.migration\.' . $this->config->getOption('format') . '/i', $file)) {
                            $files[] = $file;
                        }
                    }

                    // Формирование последовательного массива миграций
                    $max = $globalVersion['version'];
                    if($last) {
                        if($this->getIntKey($last) <= $this->getIntKey($globalVersion['version'])) {
                            $max = $last;
                        }
                    }
                    $output = $this->getMigrationsArray($files, $initVersion, $max);

                } else {
                    $this->output->writeln('<error>Global and local versions are idential.</error>');
                }
            }
        }

        return $output;
    }

    /**
     * Формирует массив миграций, упорядоченный по порядку применения
     * Берутся мирации, чьи ключи находятся между $min и $max значениями
     *
     * @param array $files - массив названий файлов миграций
     * @param string $min - минимальный ключ миграции
     * @param string $max - максимальный ключ миграции
     * @return array
     */
    public function getMigrationsArray($files, $min, $max) {

        $output = [];

        $keyMin = $this->getIntKey($min);
        $keyMax = $this->getIntKey($max);
        foreach($files as $file) {
            $strKey = explode('.', $file)[0];
            $key = $this->getIntKey($strKey);
            if($key >= $keyMin && $key <= $keyMax) {
                if($migration = file_get_contents($this->config->getOption('migration_path').$file)) {
                    $output[$key] = $this->formatCoder->decodeData($migration, $this->config->getOption('format'));
                }
            }
        }

        if(!empty($output)) {
            ksort($output);
        }

        return $output;
    }


    /**
     * Создание новой миграции
     *
     * @var bool $init - маркер инициализирующей миграции
     * @return bool
     */
    public function writeMigration($init = false)
    {
        $output = false;

        // Чтение информации о локальной версии миграций
        $localVersion = $this->getVersion('local');

        //  Чтение информации о глобальной версии файла
        $globalVersion = $this->getVersion('global');

        // Если локальная версия не совпадает с глобальной - выводим сообщение о необходимости апдейта
        if($localVersion != $globalVersion) {
            $this->output->writeln('<error>Global and local versions are different. You need to use migrations:migrate to apply latest version to DB.</error>');
        }

        // Если ещё не создано ни локальной ни глобальной версии - создаем стартовую миграцию или просим вызвать initialize
        else if (!$localVersion && !$globalVersion) {
            if($init) {
                $this->output->writeln('<info>No migrations created. Creating start migration.</info>');
                $this->writeInitMigration();
            } else {
                $this->output->writeln('<error>No migrations created. You need to use migrations:initialize to create first migration!</error>');
            }
        }

        // Если версии существуют и они равны - пытаемся содать новую миграцию
        else {
            if($init) {
                $this->output->writeln('<error>Migrations already exists. No need to initialize!</error>');
            } else {
                $this->output->writeln('<info>Try to generate new migration</info>');
                if($this->generateMigration()) {
                    $output = true;
                }
            }
        }

        return $output;
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
        $output = false;

        $newSchema = $this->structureParser->getSchema();
        $startSchema = $this->getInitMigration();
        if($newSchema && $startSchema) {
            $migration = $this->getSchemaDiff($startSchema, $newSchema);
            if($migration) {
                if($this->createMigration($migration)) {
                    $this->output->writeln('<info>Migration created successfully.</info>');
                    $output = true;
                }
            } else {
                $this->output->writeln('<error>No differences between DB and locsl schema!</error>');
            }
        } else {
            $this->output->writeln('<error>Cannot generate new migration!</error>');
        }

        return $output;
    }

    /**
     * Запись миграции в файл
     *
     * @param array $migration - сгенерированный массив миграции
     * @return bool
     */
    public function createMigration($migration) {
        $output = false;

        $name = date($this->config->getOption('time_format'));
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
     * Получение массива версии
     *
     * @param string $type - тип версии (local/global)
     * @return array
     */
    public function getVersion($type = 'local') {
        $version = array();
        if(file_exists($this->config->getOption($type.'_version_file').".".$this->config->getOption('format'))) {
            $localVersion = file_get_contents($this->config->getOption($type.'_version_file').".".$this->config->getOption('format'));
            if($localVersion) {
                $version = $this->formatCoder->decodeData($localVersion, $this->config->getOption('format'));
            }
        }

        return $version;
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
            else if($tableKey = $this->searchComment($tvalue['comment'], $newSchema)) {
                if($sameTable = $this->getTableDiff($tvalue, $newSchema[$tableKey])) {
                    $diff[$tkey] = $sameTable;
                }
                unset($newSchema[$tableKey]);
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

        // Сравнение по столбцам
        foreach($startTable['columns'] as $ckey => $cvalue) {

            // Если есть столбец с таким же названием
            if(array_key_exists($ckey, $newTable['columns'])) {
                if($sameColumn = $this->getColumnDiff($cvalue, $newTable['columns'][$ckey])) {
                    $diff['columns'][$ckey] = $sameColumn;
                }
                unset($newTable['columns'][$ckey]);
            }

            // Если есть столбец с таким же комментарием
            else if($columnKey = $this->searchComment($cvalue['comment'], $newTable['columns'])) {
                if($sameColumn = $this->getColumnDiff($cvalue, $newTable['columns'][$columnKey])) {
                    $diff['columns'][$ckey] = $sameColumn;
                }
                unset($newTable['columns'][$columnKey]);
            }

            // Столбец удаляется
            else {
                $diff['columns'][$ckey]['action'] = 'drop';
            }
        }

        // Дальше учтем вновь созданные столбцы
        if($newTable['columns']) {
            if(!$diff['columns']) {
                $diff['columns'] = array();
            }
            $diff['columns'] = array_merge($diff['columns'], $newTable['columns']);
        }

        // Сравнение по индексам
        foreach ($startTable['indexes'] as $ikey => $ivalue) {

            // Если есть индекс с таким же названием
            if(array_key_exists($ikey, $newTable['indexes'])) {
                if($sameIndex = $this->getIndexDiff($ivalue, $newTable['indexes'][$ikey])) {
                    $diff['indexes'][$ikey] = $sameIndex;
                }
            }

            // Иначе индекс удаляется
            else {
                $diff['indexes'][$ikey]['action'] = 'drop';
            }
        }

        // Дальше учтем вновь созданные индексы
        if($newTable['indexes']) {
            if(!$diff['indexes']) {
                $diff['indexes'] = array();
            }
            $diff['indexes'] = array_merge($diff['indexes'], $newTable['indexes']);
        }

        // Сравнение по внешним ключам
        foreach ($startTable['foreignKeys'] as $fkey => $fvalue) {

            // Если есть внешний ключ с таким же названием
            if(array_key_exists($fkey, $newTable['foreignKeys'])) {
                if($sameFK = $this->getForeignKeyDiff($fvalue, $newTable['foreignKeys'][$fkey])) {
                    $diff['foreignKeys'][$fkey] = $sameFK;
                }
            }

            // иначе внешний ключ удаляется
            else {
                $diff['foreignKeys'][$fkey]['action'] = 'drop';
            }
        }

        // Дальше учтем вновь созданные внешние ключи
        if($newTable['foreignKeys']) {
            if(!$diff['foreignKeys']) {
                $diff['foreignKeys'] = array();
            }
            $diff['foreignKeys'] = array_merge($diff['foreignKeys'], $newTable['foreignKeys']);
        }

        // Если разница в таблицах существуем присваиваем действие alter
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
     * Получает различия между массивами индекса
     *
     * @param array $startIndex - начальный вариант массива индекса
     * @param array $newIndex - измененный вариант массива индекса
     * @return array
     */
    public function getIndexDiff($startIndex, $newIndex) {
        $diff = array();

        if(array_diff_assoc($newIndex, $startIndex)) {
            $diff = $newIndex;
            $diff['action'] = 'change';
        } else {
            if($newIndex['columns'] && $startIndex['columns']) {
                if($this->isIndexColumnsDifferent($startIndex['columns'], $newIndex['columns'])) {
                    $diff = $newIndex;
                    $diff['action'] = 'change';
                }
            }
        }

        return $diff;
    }

    /**
     * Получает различия между массивами внешнего ключа
     *
     * @param array $startKey
     * @param array $newKey
     * @return array
     */
    public function getForeignKeyDiff($startKey, $newKey) {
        $diff = array();

        if(array_diff_assoc($newKey, $startKey)) {
            $diff = $newKey;
            $diff['action'] = 'change';
        } else {
            $hasDiff = false;
            if($newKey['localKeys'] && $startKey['localKeys']) {
                $hasDiff = array_diff_assoc($newKey['localKeys'], $startKey['localKeys']);
            }
            if(!$hasDiff) {
                if($newKey['foreignKeys'] && $startKey['foreignKeys']) {
                    $hasDiff = array_diff_assoc($newKey['foreignKeys'], $startKey['foreignKeys']);
                }
            }
            if($hasDiff) {
                $diff = $newKey;
                $diff['action'] = 'change';
            }
        }

        return $diff;
    }

    /**
     * Проверяет на наличие различий в списках столбцов (Индексов или Внешних ключей)
     *
     * @param array $startColumns - начальный вариант массива списка столбцов
     * @param array $newColumns - измененный вариант массива списка столбцов
     * @return bool
     */
    public function isIndexColumnsDifferent($startColumns, $newColumns) {
        $diff = false;

        // Проверим каждый столбец из массивов на равенство
        foreach($startColumns as $key => $col) {
            if(array_key_exists($key, $newColumns)) {
                if(array_diff_assoc($newColumns[$key], $col)) {
                    $diff = true;
                    break;
                } else {
                    unset($newColumns[$key]);
                }
            } else {
                $diff = true;
                break;
            }
        }

        // Если в новом массиве что-то осталось то они не равны
        if(!$diff) {
            if ($newColumns) {
                $diff = true;
            }
        }

        return $diff;
    }

    /**
     * Поиск в масииве такого же комментария.
     * Возвращает ключ массива
     *
     * @param string $comment - строка комментария
     * @param array $haystack - массив для поиска
     * @return string
     */
    public function searchComment($comment, $haystack) {
        $output = '';

        foreach($haystack as $k => $v) {
            if(array_key_exists('comment', $v)) {
                if($v['comment'] == $comment) {
                    $output = $k;
                }
            }
        }

        return $output;
    }

}