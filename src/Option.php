<?php

namespace Aponahmed\Wpbackup;

/**
 *
 * @author Mahabub
 */
trait Option {

    public $optionString = "";
    public static $optionKey = 'siatex-backup-options';
    public static $optionKeyFolder = 'siatex-backup-folders';
    public static object $options;
    public static $folders;
    public static $backupHistory = [];

    static function default() {
        return [
            'type' => 'local',
            'db' => '0',
            'dbfName' => 'dump.sql',
            'ftpHost' => '',
            'ftpPort' => 21,
            'ftpLogin' => '',
            'ftpPassword' => '',
            'ftpDir' => '',
            'test' => false,
        ];
    }

    static function setOption(array $options) {
        update_option(self::$optionKey, json_encode($options));

        self::$options = (object) $options;
    }

    private static Function SetBackupHistory() {
        //[date]=>['time','file_name','logs'=>[]]
        update_option('backupHistory', json_encode(self::$backupHistory));
    }

    private static Function GetBackupHistory() {
        $historyData = get_option('backupHistory');
        self::$backupHistory = json_decode($historyData, true);
    }

    static function newHistory($arr = false) {
        self::GetBackupHistory();
        $date = date('d-m-Y');
        $currentHistory = isset(self::$backupHistory[$date]) ? self::$backupHistory[$date] : ['time' => time(), 'logs' => [], 'file_name' => '', 'remote_location' => ''];
        $currentHistory['time'] = time();
        $logs = $currentHistory['logs'];
        if (isset($arr['file_name'])) {
            $currentHistory['file_name'] = $arr['file_name'];
        }
        if (isset($arr['log'])) {
            $logs[$arr['key']] = $arr['log'];
        }
        if (isset($arr['remote_location'])) {
            $currentHistory['remote_location'] = $arr['remote_location'];
        }
        $currentHistory['logs'] = $logs;

        self::$backupHistory[$date] = $currentHistory;
        self::SetBackupHistory();
    }

    static function backupHistory() {
        self::GetBackupHistory();
        return self::$backupHistory;
    }

    /**
     * Get Options
     */
    static function Option($options = []) {

        $opt = get_option(self::$optionKey);
        self::getFolders();
        $opt = json_decode($opt, true);
        $opt = array_filter(array_map('trim', $opt));
        self::$options = (object) array_merge(self::default(), $opt);
    }

    static function setFolders(array $folders) {
        update_option(self::$optionKeyFolder, json_encode($folders));
        self::$folders = $folders;
    }

    static function getFolders() {
        $folderStr = get_option(self::$optionKeyFolder);
        self::$folders = json_decode($folderStr, true);
    }

    static function nameFilter($str) {
        return str_replace(["/", ",", " "], "-", $str);
    }

//put your code here
}
