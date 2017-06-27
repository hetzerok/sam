<?php

/**
 * Copyright (c) 2017.
 * Roman Gorbunov (hetzerok)
 * hetzerok@gmail.com
 */

namespace Opencolour\Additions;

use Monolog\Logger;

/**
 * Класс для работы с файловой системой
 *
 * Class Filesystem
 * @package Opencolour\Additions
 */
class Filesystem {

    /** @var Logger $log */
    protected $log;

    public function __construct(Logger $log) {
        $this->log = $log;
        $this->config = Config::getInstance();
    }

    public function createDirs() {
        $result = true;

        if(!$this->createLogDir($this->config->getOption('log_path'))) {
            $this->log->error('Не удалось создать директорию для логов или она нелоступна для записи', ['path' => $this->config->getOption('log_path')]);
        }

        if(!$this->createSchemaDir($this->config->getOption('schema_path'))) {
            $this->log->crit('Не удалось создать директорию для схем или она нелоступна для записи', ['path' => $this->config->getOption('schema_path')]);
            $result = false;
        }
        if(!$this->createMigrationDir($this->config->getOption('migration_path'))) {
            $this->log->crit('Не удалось создать директорию для структурных миграций или она нелоступна для записи', ['path' => $this->config->getOption('miogration_path')]);
            $result = false;
        }
        if(!$this->createDataDir($this->config->getOption('data_path'))) {
            $this->log->crit('Не удалось создать директорию для миграций данных или она нелоступна для записи', ['path' => $this->config->getOption('data_path')]);
            $result = false;
        }

        return $result;
    }

    public function createDir($path) {
        if(file_exists($path)) {
            if(is_writable($path)) {
                $result = true;
            } else {
                $result = chmod($path, 0755);
            }
        } else {
            $result = mkdir($path, 0755, true);
        }

        return $result;
    }

    public function createSchemaDir($path) {

        return $this->createDir($path);
    }

    public function createMigrationDir($path) {

        return $this->createDir($path);
    }

    public function createDataDir($path) {

        return $this->createDir($path);
    }

    public function createLogDir($path) {

        return $this->createDir($path);
    }
}