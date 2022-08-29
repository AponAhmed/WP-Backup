<?php

namespace Aponahmed\Wpbackup;

/**
 *
 * @author Mahabub
 */
trait Option {

    public $optionString = "";
    public static $optionKey = 'siatex-backup-options';
    public static object $options;

    static function setOption() {
        
    }

    /**
     * Get Options
     */
    static function Option() {
        $opt = get_option(self::$optionKey);
        self::$options = (object) $opt;
    }

    //put your code here
}
