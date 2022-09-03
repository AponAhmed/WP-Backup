<?php

namespace Aponahmed\Wpbackup;

/**
 * Description of FTP
 *
 * @author Mahabub
 */
class FTP {

    use Option;

    public $conn;
    public $connected = false;
    public $mode = FTP_BINARY; //FTP_ASCII

    //put your code here

    /**
     * 
     * @param type $config
     *  -host
     *  -port
     *  -login
     *  -password
     *  -remote_dir
     * @return $this
     */
    public function __construct($config = []) {
        self::Option();
        $config_default = [
            'host' => '',
            'port' => 21,
            'login' => '',
            'password' => '',
            'remote_dir' => ''
        ];
        $this->config = (object) array_merge($config_default, $config);
        $this->conn = ftp_connect($this->config->host, $this->config->port);
        return $this;
    }

    public function login() {
        try {
            $this->connected = ftp_login($this->conn, $this->config->login, $this->config->password);
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
        return $this;
    }

    /**
     * 
     * @param type $src Resource File Path
     * @param type $remoteFile Remote File Name
     * @return boolean
     */
    public function fput($src, $remoteFile) {
        $fileStream = fopen($src, 'r');
        ftp_pasv($this->conn, true);
        if (ftp_fput($this->conn, $this->config->remote_dir . $remoteFile, $fileStream, $this->mode)) {//FTP_ASCII
            return true;
        } else {
            return false;
        }
        return $this;
    }

}
