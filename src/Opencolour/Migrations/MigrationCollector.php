<?php
/**
 * Copyright (c) 2017.
 * Roman Gorbunov (hetzerok)
 * hetzerok@gmail.com
 */

namespace Opencolour\Migrations;

use Opencolour\Additions\Config;

class MigrationCollector
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

}