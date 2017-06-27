<?php

/**
 * Copyright (c) 2017.
 * Roman Gorbunov (hetzerok)
 * hetzerok@gmail.com
 */

namespace Opencolour\Additions;

class Config
{
    /* @var Config $instance */
    private static $instance = null;

    /* @var array $cfg */
    private $cfg = array();

    /* @var \PDO $dbConnection */
    private $dbConnection = null;

    private function __construct($dir)
    {
        $this->cfg = $this->getApplicationConfig($dir);

        // Установка параметров подключения к БД
        $connections = array(
            "dsn"      => '',
            "username"  => '',
            "password"  => '',
        );
        foreach ($connections as $key => $value) {
            if (array_key_exists($key, $this->cfg)) {
                $connections[$key] = $this->cfg[$key];
            }
        }

        // Попытка установки подключения
        try {
            $this->dbConnection = new \PDO(
                $connections['dsn'],
                $connections['username'],
                $connections['password'], array(
                    \PDO::ATTR_PERSISTENT => true,
                    \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
                )
            );
        } catch (\PDOException $e) {
            error_log($e->getMessage());
        }
    }

    private function __clone()
    {

    }

    private function __wakeup()
    {

    }

    public static function getInstance($dir = '')
    {
        if (!isset(static::$instance)) {
            self::$instance = new Config($dir);
        }

        return static::$instance;
    }

    /**
     * Подгрузка конфигов
     *
     * @param string $dir - адрес директории с конфигами
     * @return array
     */
    private function getApplicationConfig($dir = '')
    {
        $cfg = array();

        if ($dir) {
            $path = $dir;
        } else {
            $path = 'config/';
        }

        $dir = opendir($path);
        while ($file = readdir($dir)) {
            if (preg_match('/\.inc\.php/i', $file)) {
                $config = include $path.$file;
                $cfg = array_merge($cfg, $config);
            }
        }

        return $cfg;
    }


    /**
     * Получение значения переменной конфига по ключу
     *
     * @param $name - ключ получаемой опции
     * @return mixed|null
     */
    public function getOption($name)
    {
        $option = null;
        if (array_key_exists($name, $this->cfg)) {
            $option = $this->cfg[$name];
        }

        return $option;
    }

    /**
     * Получение инстанса соединения с БД
     *
     * @return \PDO/null
     */
    public function getConnection()
    {
        return $this->dbConnection;
    }
}