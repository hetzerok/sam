<?php
/**
 * Copyright (c) 2017.
 * Roman Gorbunov (hetzerok)
 * hetzerok@gmail.com
 */

namespace Opencolour\Migrations;

use Opencolour\Additions\Config;

/**
 * Класс с методами кодировния/раскодирования массива в нужный строковый формат
 * Доступные форматы: json
 *
 * Class FormatCoder
 * @package Opencolour\Migrations
 */
class FormatCoder
{

    private $log;

    /* @var Config $config */
    protected $config = null;

    /**
     * FormatCoder constructor.
     */
    public function __construct($log)
    {
        $this->log = $log;
        $this->config = Config::getInstance();
    }

    public function encodeData(array $data, $type = 'json')
    {
        $output = '';

        switch ($type) {
            case 'json':
                $output = $this->encodeJsonData($data);
                break;
            default:
                $output = $this->encodeJsonData($data);
        }

        return $output;
    }

    public function decodeData($data, $type = 'json')
    {
        $output = [];

        switch ($type) {
            case 'json':
                $output = $this->decodeJsonData($data);
                break;
            default:
                $output = $this->decodeJsonData($data);
        }

        return $output;
    }

    protected function encodeJsonData(array $data)
    {
        $output = json_encode($data);

        return $output;
    }

    protected function decodeJsonData($data)
    {
        $output = json_decode($data, true);

        return $output;
    }
}