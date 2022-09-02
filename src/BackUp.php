<?php

namespace Aponahmed\Wpbackup;

use Ifsnop\Mysqldump as IMysqldump;
use Aponahmed\Wpbackup\FTP;

/**
 * Description of BackUp
 *
 * @author Mahabub
 */
class BackUp {

    use Option;

    private $backupRoot = false;

    public function __construct() {
        add_action('wp_ajax_backup-db', [$this, 'backup_db']);
        add_action('wp_ajax_backup-file', [$this, 'backup_file']);
        add_action('wp_ajax_backup-zip', [$this, 'backup_zip']);
        add_action('wp_ajax_backup-uploadftp', [$this, 'backup_uploadftp']);
    }

    /**
     * Create Backup Folder
     */
    function init($returnName = false) {
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);
        ini_set('max_execution_time', 3000);

        $location = __BACKUP_DIR;
        //Create Folder
        $folderNameTemplate = FILE_NAME;
        $siteName = get_bloginfo();
        $date = date('d-m-Y');
        $folderName = str_replace(
                ['[site]', '[date]']
                ,
                [$siteName, $date],
                $folderNameTemplate);
        if ($returnName) {
            return $folderName;
        }
        $folderPath = $location . $folderName;
        if (!is_dir($folderPath)) {
            mkdir($folderPath, 0777);
            chmod($folderPath, 0777);
            $this->backupRoot = $folderPath;
            //self::newHistory(true);
        } else {
            $this->backupRoot = $folderPath;
        }
    }

    //put your code here
    function backup_db() {
        $this->init();
        self::Option();
        try {
            $dump = new IMysqldump\Mysqldump('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASSWORD);
            $fileName = $this->backupRoot . '/' . self::$options->dbfName;
            if (empty(self::$options->dbfName)) {
                throw new \Exception("DB File Name Missing");
            }
            $dump->start($fileName);
            self::newHistory(['log' => 'Database Backup -> OK', 'key' => 'DB_DUMP']);
            ob_get_clean();
            echo 1;
        } catch (\Exception $e) {
            self::newHistory(['log' => 'Database Backup -> Error', 'key' => 'DB_DUMP']);
            ob_get_clean();
            echo 'mysqldump-php error: ' . $e->getMessage();
        }
        wp_die();
    }

    function backup_file() {
        $this->init();
        self::Option();
        //Root Dir 
        $root = _BACKUP_ROOT;
        //Pathinfo
        $rootPathInfo = pathinfo($root);
        $rootBase = $rootPathInfo['basename'];
        $NewBasname = pathinfo($this->backupRoot);
        //echo json_encode(['error' => 'Something Went Wrong']);
        //wp_die();
        try {
            self::recurse_copy($root, $this->backupRoot . "/$rootBase/", $rootBase);
            self::newHistory(['log' => 'File Backup to Local -> OK', 'key' => 'FILE_COPY']);
            ob_get_clean();
            echo json_encode(['error' => false, 'file' => $NewBasname['basename']]);
        } catch (\Exception $e) {
            self::newHistory(['log' => 'File Backup -> Error', 'key' => 'FILE_COPY']);
            ob_get_clean();
            echo json_encode(['error' => $e->getMessage()]);
        }
        wp_die();
    }

    static function recurse_copy($res, $dest, $baseRoot) {
        $skip = self::$folders;
        $dir = opendir($res);
        //Destination Create folder;
        if (!is_dir($dest)) {
            mkdir($dest, 0777, true);
            chmod($dest, 0777);
        }

        while (false !== ( $file = readdir($dir))) {
            if (( $file != '.' ) && ( $file != '..' )) {
                if (is_dir($res . '/' . $file)) {
                    $nRoot = $baseRoot . "-" . $file;
                    $k = self::nameFilter($nRoot);
                    if (isset($skip[$k]) && $skip[$k] == "1") {
                        //echo " skiped $file <br>";
                        continue;
                    }
                    //echo "Copied $file - $k <br>";
                    self::recurse_copy($res . '/' . $file, $dest . '/' . $file, $nRoot);
                } else {
                    \copy($res . '/' . $file, $dest . '/' . $file);
                }
            }
        }
        closedir($dir);
    }

    function backup_zip() {
        $this->init();
        self::Option();
        //Folder // ;
        // echo json_encode(['error' => 'Error with ZIP']);
        //wp_die();

        $outputFile = $this->init(true) . ".zip";
        if ($this->myZip($this->backupRoot)) {
            $this->rrmdir($this->backupRoot); //Delete Folder;
            self::newHistory(['log' => 'File & DB Compression -> OK', 'key' => 'FILE_COMPRES']);
            self::newHistory(['file_name' => $outputFile]);
            ob_get_clean();
            echo json_encode(['error' => false, 'file' => $outputFile]);
        } else {
            self::newHistory(['log' => 'File Compression -> Error', 'key' => 'FILE_COMPRES']);
            echo "Zip error !";
        }
        wp_die();
    }

    function myZip($rootPath) {
        // Get real path for our folder
        $rootPath = $rootPath;
        // Initialize archive object
        $zip = new \ZipArchive();
        $zip->open($rootPath . '.zip', \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        // Create recursive directory iterator
        /** @var SplFileInfo[] $files */
        $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($rootPath), \RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($files as $name => $file) {
            // Skip directories (they would be added automatically)
            if (!$file->isDir()) {
                // Get real and relative path for current file
                $filePath = $file->getRealPath();

                $relativePath = substr($filePath, strlen($rootPath));
                $relativePath = substr($relativePath, 1, strlen($relativePath) - 1);
                // Add current file to archive
                $zip->addFile($filePath, $relativePath);
            }
        }
        // Zip archive will be created only after closing object
        return $zip->close();
        //sleep(1);
    }

    function rrmdir($src) {
        $dir = opendir($src);
        while (false !== ( $file = readdir($dir))) {
            if (( $file != '.' ) && ( $file != '..' )) {
                $full = $src . '/' . $file;
                if (is_dir($full)) {
                    $this->rrmdir($full);
                } else {
                    @unlink($full);
                }
            }
        }
        closedir($dir);
        return @\rmdir($src);
    }

    function backup_uploadftp() {
        $this->init();
        self::Option();
        $file = $_POST['file'];

        $config = [
            'host' => self::$options->ftpHost,
            'port' => (int) self::$options->ftpPort,
            'login' => self::$options->ftpLogin,
            'password' => self::$options->ftpPassword,
            'remote_dir' => self::$options->ftpDir,
        ];
        $ftp = new FTP($config);
        $ftp->login();
        ob_get_clean();

        if ($ftp->connected) {
            $fileFullPAth = __BACKUP_DIR . $file;
            $res = $ftp->fput($fileFullPAth, $file);
            if ($res) {
                $this->rrmdir($this->backupRoot); //Delete Folder;

                self::newHistory(['log' => 'File Uploaded via FTP -> OK', 'key' => 'FTP_UPLOAD']);
                self::newHistory(['remote_location' => self::$options->ftpHost . " > " . self::$options->ftpDir . $file]);
                echo json_encode(['error' => false, 'msg' => "File uploaded to " . self::$options->ftpHost . " > " . self::$options->ftpDir]);
            }
        } else {
            self::newHistory(['log' => 'File Uploaded via FTP -> Error', 'key' => 'FTP_UPLOAD']);
            echo json_encode(['error' => 'FTP Server Connection Error']);
        }
        wp_die();
    }

}
