# WP Backup Plugin

This is a WordPress backup plugin written in PHP. It provides functionality to backup both the database and files, compress them, and upload them to a remote server using FTP or instant Download and also able to Manage Backup History.

## Features

- **Database Backup:** Option to backup the WordPress database.
- **File Backup:** Backup WordPress files and folders.
- **Compression:** Compress files and database into a zip file.
- **FTP Upload:** Upload the backup file to a remote server via FTP.

### Screenshot
![alt text](https://github.com/AponAhmed/WP-Backup/blob/main/backup.png?raw=true)
![alt text](https://github.com/AponAhmed/WP-Backup/blob/main/backup-settings.png?raw=true)

### Backup

To initiate a backup, go to the `Backup` tab in the WordPress admin panel and click the "Backup" button. Ensure that the server execution time is sufficient for the backup process.

### Settings

- **Backup Type:** Choose between local download or FTP upload.
- **FTP Configuration:** Configure FTP settings if FTP upload is selected.
- **Backup Database:** Enable or disable the backup of the WordPress database.
- **Database File Name:** Set the name of the database backup file.
- **Exclude Folder:** Choose folders to exclude from the backup.

### Backup History

View the history of backups, including date, local file, remote file location, and options to remove backups.

## Installation

1. Clone the repository:
```bash
   git clone https://github.com/AponAhmed/WP-Backup.git
```
