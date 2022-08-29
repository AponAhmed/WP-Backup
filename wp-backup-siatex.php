<?php

/**
 * Plugin Name: WP Backup
 * Plugin URI: https://www.siatex.com
 * Description: Backup WP web-site, Content Folder and Data  
 * Author: SiATEX
 * Author URI: https://www.siatex.com
 * Version: 1.0 
 */

namespace Aponahmed\Wpbackup;

use Aponahmed\Wpbackup\BackupAdmin;

require_once 'vendor/autoload.php';
//Backup folder where Write backup file
define('__BACKUP_DIR', ABSPATH);
//Static Assets 
define('__BACKUP_ASSETS', plugin_dir_url(__FILE__) . "assets/");

class WpBackup {

    use Option;

    private object $admin;

    public function __construct() {
        self::Option();
        if (is_admin()) {
            $this->admin = new BackupAdmin();
        }
    }

}

$backup = new WpBackup(__BACKUP_DIR);

