<?php

namespace Aponahmed\Wpbackup;

use Aponahmed\Wpbackup\BackUp;
use Aponahmed\Wpbackup\FTP;

/**
 * Description of BackupAdmin
 *
 * @author Mahabub
 */
class BackupAdmin
{

    use Option;

    private $BackUp;

    //put your code here
    public function __construct()
    {
        self::Option();
        $this->BackUp = new BackUp();
        add_action('admin_menu', [$this, 'init'], 0); //resgister the function
        add_action('admin_enqueue_scripts', [$this, 'adminScript']);
        add_action('wp_ajax_backupOptionStore', [$this, 'backupOptionStore']);
        add_action('wp_ajax_backup-history-remove', [$this, 'backupHistoryRemove']);
    }

    /**
     * Admin Script Init
     */
    public function adminScript($hook)
    {
        wp_enqueue_style('backup-admin-style', __BACKUP_ASSETS . 'admin-style.css');

        wp_enqueue_script('backup-admin-script', __BACKUP_ASSETS . 'admin-script.js', array('jquery'), '1.0');
        wp_localize_script(
            'backup-admin-script',
            'backupJS',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'siteUrl' => site_url()
            )
        );
        //}
    }

    function init()
    {
        //Admin Page Initialize 
        add_submenu_page(
            'tools.php',
            'Backup',
            'Backup',
            'manage_options',
            'backup',
            [$this, 'optionPage'],
            1
        );
    }

    function backupHistoryRemove()
    {
        self::GetBackupHistory();
        $id = $_POST['historyID'];
        $curHis = self::$backupHistory[$id];
        unset(self::$backupHistory[$id]);

        $fileName = $curHis['file_name'];
        $localFile = ABSPATH . $fileName;

        $msg = "";
        if (file_exists($localFile)) {
            unlink($localFile);
            $msg = "Local File Deleted";
        } else {
            $msg = "Local File Missing";
        }
        $del = true;
        if ($del) {
            self::SetBackupHistory();
            echo json_encode(['error' => false, 'msg' => $msg]);
        } else {
            echo json_encode(['error' => true]);
        }
        wp_die();
    }

    /**
     * 
     * @param string $dir
     * @param string $relDir 
     * @param int $step Maximum Stem recursion
     * @return type
     */
    static function folderData($dir, $relDir, $step)
    {
        $step--;
        $folders = [];
        if ($step > 0) {
            foreach (new \DirectoryIterator($dir) as $fileInfo) {
                if ($fileInfo->isDot())
                    continue;
                //$cDir = $relDir . "/" . $fileInfo->getFilename();
                $cDir = "$relDir" . "/" . $fileInfo->getFilename();

                if ($fileInfo->getFilename() == 'uploads') {
                    $step++;
                }
                if ($fileInfo->isDir()) {
                    $folder = [
                        'name' => $fileInfo->getFilename(),
                        'sub' => self::folderData($dir . "/" . $fileInfo->getFilename(), $cDir, $step)
                    ];
                    $folders[$cDir] = $folder;
                }
            }
        }
        return $folders;
    }

    static function folderSelector()
    {
        $dir = _BACKUP_ROOT;
        $pathInfo = pathinfo($dir);
        $relDir = $pathInfo['basename'];
        $folders = [
            $relDir => [
                'name' => $relDir,
                'sub' => self::folderData($dir, $relDir, _BACKUP_DIR_DEPTH)
            ]
        ];
        echo self::folderHtml($folders);
    }

    static function folderHtml($folders)
    {
        self::Option();
        $foldersOpt = self::$folders;
        $html = "<ul>";
        foreach ($folders as $k => $folder) {
            //$checkName = md5($k);
            $checkName = self::nameFilter($k); //str_replace("/", "-", $k);
            $chk = "";
            if (isset($foldersOpt[$checkName]) && $foldersOpt[$checkName] == '1') {
                $chk = "checked";
            }

            $childs = "";
            $hasChild = "";
            if (isset($folder['sub']) && is_array($folder['sub']) && count($folder['sub']) > 0) {
                $childs = self::folderHtml($folder['sub']);
                $hasChild = "has-child";
            }
            $html .= "<li class='folder-item $hasChild'>";
            // $html .= "<input type='hidden' name='backup-folders[$checkName]' value='0'>";
            $html .= "<div class='folder-item-control'><input $chk data-folder='$k' name='backup-folders[$checkName]' value='1' type='checkbox'>";
            $html .= "<label>$folder[name]</label></div>$childs</li>";
        }
        $html .= "</ul>";
        return $html;
    }

    function backupOptionStore()
    {
        $BackupOptionData = [];
        parse_str($_POST['formdata'], $BackupOptionData);
        $options = $BackupOptionData['options'];
        $folderData = [];
        if (isset($BackupOptionData['backup-folders'])) {
            $folderData = $BackupOptionData['backup-folders'];
        }
        //Update Option
        self::setOption($options);
        self::setFolders($folderData);
        echo 1;
        wp_die();
    }

    function backupHistoryData()
    {
        echo json_encode(self::backupHistory());
    }

    function optionPage()
    {
        self::Option();
        $foldersOpt = self::$folders;

        $options = self::$options;
        // Check if ZipArchive is enabled
        $zipEnabled = class_exists('ZipArchive');
        //echo "<pre>";
        //var_dump($options);
        //echo "</pre>";
?>
        <div class="wrap wp-backup-wrap">
            <h1 class="wp-heading-inline">Backup</h1>
            <hr>
            <div class="backup-tab">
                <nav class="nav-tab-wrapper">
                    <a href="#backupPanel" class="nav-tab  nav-tab-active">Backup</a>
                    <a href="#backupOptions" class="nav-tab">Settings</a>
                    <a href="#backupHistory" class="nav-tab">Backup History</a>
                </nav>
                <div class="wp-backup-tabs-wrap">
                    <div id="backupPanel" class="wp-backup-tab show ">
                        <div class="backup-option-section">
                            <button type="button" class="button button-primary" id="backupStart" onclick="startBackup(this)">Backup</button>
                            <span class="description" style="margin-left: 25px;margin-top: 6px;display: inline-block;color: #999;">Server Execution time should be longer then normal time</span>
                        </div>
                        <div class="backup-option-section" style="border-bottom: 0">
                            <div class="backup-status hide"></div>
                        </div>
                    </div>
                    <div id="backupHistory" class="wp-backup-tab ">
                        <table class="wp-list-table widefat fixed striped table-view-list backup-history">
                            <thead>
                                <tr>
                                    <th width='120'>Date</th>
                                    <th width='150'>Local File</th>
                                    <th>Remote File location</th>
                                    <th width="40"></th>
                                </tr>
                            </thead>
                            <tbody id="backupHistoryData">
                                <?php
                                foreach (self::backupHistory() as $key => $value) {
                                    //var_dump($value);
                                ?>
                                    <tr>
                                        <td width='100'><?php echo date('d-m-y h:i a', $value['time']) ?></td>
                                        <td width='150'><?php
                                                        $filePath = __BACKUP_DIR . $value['file_name'];
                                                        $fileUrl = site_url() . "/" . $value['file_name'];
                                                        if (file_exists($filePath)) {
                                                            echo $value['file_name'] . "<a href='$fileUrl'><span class=\"dashicons dashicons-download\"></span></a>";
                                                        } else {
                                                            echo "<span style='color:#f00' title='Local File Missing'>" . $value['file_name'] . '</span>';
                                                        }
                                                        ?></td>
                                        <td><?php echo $value['remote_location'] ?></td>
                                        <td>
                                            <div class="backup-history-control">
                                                <span onclick='deleteBackupHistory("<?php echo $key ?>", this)'><span class="dashicons dashicons-trash"></span></span>
                                                <span onclick='showDetailsBackupHistory(this)' data-info='<?php echo json_encode($value['logs']) ?>'><span class="dashicons dashicons-media-text"></span></span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                    <div id="backupOptions" class="wp-backup-tab ">
                        <form id="backUpOption">
                            <div class="backup-option-section">
                                <div class="backup-option-wrap">
                                    <label>Backup Type</label>
                                    <select name="options[type]" id="backupType" class="custom-select custom-select-sm">
                                        <option value="local" <?php echo $options->type == 'local' ? 'selected' : "" ?>>Download</option>
                                        <option value="ftp" <?php echo $options->type == 'ftp' ? 'selected' : "" ?>>FTP</option>
                                        <option disabled="" <?php echo $options->type == 's3' ? 'selected' : "" ?> title="Comming soon" value="s3">S3</option>
                                    </select>
                                </div>
                                <div class="backupTypeConfig ftpConfig <?php echo $options->type == 'ftp' ? 'config-show' : "" ?> ">
                                    <div class="backup-option-wrap">
                                        <label>FTP Config</label>
                                        <div class="input-area">
                                            <div class="bflex">
                                                <input type="text" value="<?php echo $options->ftpHost ?>" name="options[ftpHost]" placeholder="Host" title="Host">
                                                <input type="text" value="<?php echo $options->ftpPort ?>" name="options[ftpPort]" placeholder="Port" title="Port" style="max-width: 79px;">
                                            </div>
                                            <div class="bflex">
                                                <input type="text" value="<?php echo $options->ftpLogin ?>" name="options[ftpLogin]" placeholder="Login" title="Login">
                                                <input type="password" value="<?php echo $options->ftpPassword ?>" name="options[ftpPassword]" placeholder="Password" title="Password">
                                            </div>
                                            <div class="bflex">
                                                <input type="text" value="<?php echo $options->ftpDir ?>" name="options[ftpDir]" placeholder="Directory" title="Directory">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!--Another Option Will be here -->
                            </div>
                            <div class="backup-option-section">
                                <div class="backup-option-wrap">
                                    <label>Backup DB</label>
                                    <div class="input-area">
                                        <input type="hidden" name="options[db]" value="0">
                                        <label><input type="checkbox" <?php echo $options->db == '1' ? "checked" : "" ?> id="dbBackup" name="options[db]" value="1">&nbsp;Enable</label>
                                    </div>
                                </div>
                                <div class="backup-option-wrap">
                                    <label>DB File Name</label>
                                    <div class="input-area">
                                        <input type="text" class="form-control" name="options[dbfName]" value="<?php echo $options->dbfName ?>" placeholder="dump.sql" title='File Name of Database'>
                                    </div>
                                </div>
                            </div>
                            <div class="backup-option-section">
                                <div class="backup-option-wrap" id="folderSelect">
                                    <label>Exclude Folder</label>
                                    <div class="folderSelector">
                                        <?php self::folderSelector() ?>
                                    </div>
                                </div>
                                <hr>
                                <div class="backup-option-wrap">
                                    <label></label>
                                    <div class="input-area">
                                        <button type="submit" id="backupUpdateOptions" class="button button-default">Update</button>
                                    </div>
                                </div>
                            </div>

                        </form>
                    </div>
                    <?php if (!$zipEnabled) : ?>
                        <div class="notice notice-error is-dismissible">
                            <p><strong>Attention:</strong> The <code>ZipArchive</code> extension is not enabled on your server. backup features may not work correctly.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
<?php
    }
}
