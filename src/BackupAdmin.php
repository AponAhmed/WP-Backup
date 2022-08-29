<?php

namespace Aponahmed\Wpbackup;

use Aponahmed\Wpbackup\BackUp;

/**
 * Description of BackupAdmin
 *
 * @author Mahabub
 */
class BackupAdmin {

    use Option;

    private $BackUp;

    //put your code here
    public function __construct() {
        $this->BackUp = new BackUp();
        add_action('admin_menu', [$this, 'init'], 0); //resgister the function
        add_action('admin_enqueue_scripts', [$this, 'adminScript']);
    }

    /**
     * Admin Script Init
     */
    public function adminScript($hook) {
        //if (strpos($hook, 'cache') !== false) {
        wp_enqueue_style('backup-admin-style', __BACKUP_ASSETS . 'admin-style.css');

        wp_enqueue_script('backup-admin-script', __BACKUP_ASSETS . 'admin-script.js', array('jquery'), '1.0');
        wp_localize_script('backup-admin-script', 'backupJS', array('ajax_url' => admin_url('admin-ajax.php')));
        //}
    }

    function init() {
        //Admin Page Initialize 
        add_submenu_page(
                'tools.php',
                'Backup', 'Backup',
                'manage_options',
                'backup',
                [$this, 'optionPage'],
                1);
    }

    function optionPage() {
        ?>
        <div class="wp-backup-wrap">
            <h3>Backup</h3>
            <nav class="nav-tab-wrapper">
                <a href="#backupPanel" class="nav-tab">Backup</a>
                <a href="#backupOptions" class="nav-tab">Settings</a>
            </nav>
            <div class="wp-backup-tabs-wrap">
                <div id="backupPanel" class="wp-backup-tab">
                    --BAckup--
                </div>
                <div id="backupOptions" class="wp-backup-tab">
                    --Settings--
                </div>
            </div>
        </div>
        <?php
    }

}
