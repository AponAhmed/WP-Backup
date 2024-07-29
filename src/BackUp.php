<?php

namespace Aponahmed\Wpbackup;

use Ifsnop\Mysqldump as IMysqldump;
use Aponahmed\Wpbackup\FTP;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RecursiveCallbackFilterIterator;
use FilesystemIterator;
use ZipArchive;

/**
 * Description of BackUp
 */
class BackUp
{
    use Option;

    private $backupRoot = false;

    public function __construct()
    {
        add_action('wp_ajax_backup-db', [$this, 'backup_db']);
        add_action('wp_ajax_backup-file', [$this, 'backup_file']);
        add_action('wp_ajax_backup-zip', [$this, 'backup_zip']);
        add_action('wp_ajax_backup-uploadftp', [$this, 'backup_uploadftp']);
    }

    /**
     * Create Backup Folder
     */
    function init($returnName = false)
    {
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);
        ini_set('max_execution_time', 3000);
        ini_set('memory_limit', '512M');

        $location = __BACKUP_DIR;
        // Create Folder
        $folderNameTemplate = FILE_NAME;
        $siteName = get_bloginfo();

        $siteUrl = site_url();
        $domain = str_replace(['http://', 'https://', 'www.', '/'], ["", "", "", "~"], $siteUrl);

        $date = date('d-m-Y');
        $folderName = str_replace(
            ['[site]', '[date]', '[domain]'],
            [$siteName, $date, $domain],
            $folderNameTemplate
        );
        if ($returnName) {
            return $folderName;
        }
        $folderPath = $location . $folderName;
        if (!is_dir($folderPath)) {
            mkdir($folderPath, 0777, true);
            chmod($folderPath, 0777);
            $this->backupRoot = $folderPath;
        } else {
            $this->backupRoot = $folderPath;
        }
    }

    function backup_db()
    {
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

    function backup_file()
    {
        $this->init();
        self::Option();
        $root = _BACKUP_ROOT;
        $backupDir = $this->backupRoot . '/' . basename($root);

        $skip = self::$folders;

        // Check if rsync is available
        $returnVar = 0;
        $cmd = "rsync -a --exclude='.*' $root $backupDir";
        if (function_exists('exec')) {
            exec($cmd, $output, $returnVar);
        }

        if ($returnVar !== 0) {
            // Fallback to PHP copy with filtering
            try {
                $this->recurse_copy($root, $backupDir, $skip);
                self::newHistory(['log' => 'File Backup to Local -> OK', 'key' => 'FILE_COPY']);
                echo json_encode(['error' => false, 'file' => basename($backupDir)]);
            } catch (\Exception $e) {
                self::newHistory(['log' => 'File Backup -> Error', 'key' => 'FILE_COPY']);
                echo json_encode(['error' => $e->getMessage()]);
            }
        } else {
            self::newHistory(['log' => 'File Backup to Local -> OK', 'key' => 'FILE_COPY']);
            echo json_encode(['error' => false, 'file' => basename($backupDir)]);
        }

        wp_die();
    }

    function recurse_copy($src, $dst, $skip)
    {
        $directory = new RecursiveDirectoryIterator($src, FilesystemIterator::FOLLOW_SYMLINKS);
        $filter = new RecursiveCallbackFilterIterator($directory, function ($current, $key, $iterator) use ($skip) {
            if ($current->getFilename()[0] === '.') {
                return false;
            }
            if ($current->isDir()) {
                $nRoot = basename($current->getPath()) . "-" . $current->getFilename();
                $k = self::nameFilter($nRoot);
                return !isset($skip[$k]) || $skip[$k] != "1";
            } else {
                return true;
            }
        });

        $iterator = new RecursiveIteratorIterator($filter);
        foreach ($iterator as $file) {
            $srcPath = $file->getPathname();
            $dstPath = $dst . '/' . $iterator->getSubPathName();
            if (!is_dir(dirname($dstPath))) {
                mkdir(dirname($dstPath), 0777, true);
                chmod(dirname($dstPath), 0777);
            }
            copy($srcPath, $dstPath);
        }
    }

    function backup_zip()
    {
        $this->init();
        self::Option();

        $outputFile = $this->init(true) . ".zip";
        if ($this->myZip($this->backupRoot)) {
            $this->rrmdir($this->backupRoot); // Delete Folder
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

    function myZip($rootPath)
    {
        $zipPath = $rootPath . '.zip';
        
        $returnVar = 0;
        $cmd = "7z a $zipPath $rootPath";
        if (function_exists('exec')) {
            exec($cmd, $output, $returnVar);
        }

        if ($returnVar !== 0) {
            return true;
        } else {
            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === TRUE) {
                $directory = new RecursiveDirectoryIterator($rootPath, FilesystemIterator::FOLLOW_SYMLINKS);
                $filter = new RecursiveCallbackFilterIterator($directory, function ($current, $key, $iterator) {
                    return $current->getFilename()[0] !== '.';
                });

                $files = new RecursiveIteratorIterator($filter);
                foreach ($files as $file) {
                    if (!$file->isDir()) {
                        $filePath = $file->getRealPath();
                        $relativePath = substr($filePath, strlen($rootPath) + 1);
                        $zip->addFile($filePath, $relativePath);
                    }
                }
                $zip->close();
                return true;
            } else {
                return false;
            }
        }
    }

    function rrmdir($src)
    {
        $dir = opendir($src);
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                $full = $src . '/' . $file;
                if (is_dir($full)) {
                    $this->rrmdir($full);
                } else {
                    @unlink($full);
                }
            }
        }
        closedir($dir);
        return @rmdir($src);
    }

    function backup_uploadftp()
    {
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
                $this->rrmdir($this->backupRoot); // Delete Folder

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
