const Q = jQuery;
let statusCount = 0;

//Custom Dialog Popup
class DialogBox {
    constructor( { ...options }) {
        this.title = options.title || "Title Here";
        this.body = options.body || "Dialog Body Here";
        this.position = options.position || "center";
        this.actions = options.actions || [
            {
                label: "Ok",
                class: "btn-primary",
                callback: function (_this) {
                    _this.close();
                }
            }
        ];
        this.bind();
        return this;
    }
    build() {
        //build element 
        let dialogBox = document.createElement("div");
        dialogBox.classList.add("dialog-box");
        //build header
        let header = document.createElement("div");
        header.classList.add("header");
        //Title wrap 
        let titleWrap = document.createElement("div");
        titleWrap.classList.add("title-wrap");
        titleWrap.innerHTML = this.title;
        header.appendChild(titleWrap);
        //close button
        let closeButton = document.createElement("div");
        closeButton.classList.add("close-button");
        closeButton.innerHTML = "&times;";
        closeButton.addEventListener("click", () => {
            dialogBox.remove();
        });
        header.appendChild(closeButton);
        dialogBox.appendChild(header);
        //build body
        let body = document.createElement("div");
        body.classList.add("body");
        body.innerHTML = this.body;
        dialogBox.appendChild(body);
        //build actions
        if (this.actions.length > 0) {
            let actions = document.createElement("div");
            actions.classList.add("actions");
            this.actions.forEach((el) => {
                let action = document.createElement("div");
                action.classList.add("action");
                action.classList.add(el.className);
                action.innerHTML = el.label;
                action.addEventListener("click", () => {
                    el.callback(this);
                });
                actions.appendChild(action);
            });
            dialogBox.appendChild(actions);
        }
        this.dialogBox = dialogBox;
        return dialogBox;
    }
    bind() {
        //append dialog
        document.body.appendChild(this.build());
        //position dialog
        if (this.position == "center") {
            this.dialogBox.style.top = "50%";
            this.dialogBox.style.left = "50%";
            this.dialogBox.style.transform = "translate(-50%, -50%)";
        } else {
            //check position object or not
            //console.log(this.dialogBox.clientHeight);
            if (typeof this.position == "object") {
                this.dialogBox.style.top = (this.position.top - this.dialogBox.clientHeight) + "px";
                this.dialogBox.style.left = this.position.left + "px";
            }
        }
    }
    close() {
        this.dialogBox.remove();
    }
}


(function (_) {
    _(document).ready(function () {
        //Tab Init
        _(".backup-tab").each(function () {
            let t = _(this);
            t.find('.nav-tab').click(function (e) {
                e.preventDefault();
                let a = _(this);

                t.find('.nav-tab').removeClass('nav-tab-active');
                a.addClass('nav-tab-active');

                t.find('.wp-backup-tab').removeClass('show');
                t.find(a.attr('href')).addClass('show');
            });
        });
        //Collapse Folders
        _('.folder-item-control label').click(function () {
            _(this).closest('.folder-item').find(' > ul').toggle();
            _(this).closest('.folder-item').toggleClass('open');
        });
        //Option Save
        _("#backUpOption").on('submit', function (e) {
            _('#backupUpdateOptions').html('<span class="dashicons dashicons-update loading"></span> Updating');
            e.preventDefault();
            let formData = _(e.target).serialize();
            _.post(backupJS.ajax_url, {action: 'backupOptionStore', formdata: formData}, function (response) {
                _('#backupUpdateOptions').html('<span class="dashicons dashicons-saved"></span> Updated');
                setTimeout(function () {
                    _('#backupUpdateOptions').html('Update');

                }, 2000);
                console.log(response);
            });
        });
        //Backup type Config
        _("#backupType").on('change', function () {
            _('.backupTypeConfig').removeClass('config-show');
            let v = _(this).val();
            _("." + v + "Config").addClass('config-show');
        });
    });
})(Q);

const fileBackup = function (param, _this) {
    addStatus('File Backup added in Queue', 'info');
    addStatus('File coping to Backuup', 'process');
    //Create Folder and Dump DB 
    Q.post(backupJS.ajax_url, {action: 'backup-file'}, function (response) {
        response = JSON.parse(response);
        if (!response.error) {
            addStatus('File copied done into > "' + response.file + '" Folder', 'success');
            param.callback(param.param, _this, response.file);
        } else {
            addStatus('File Backup Error :: ' + response.error, 'error');
            param.callback(param.param, _this);
        }
    });

}
const filezip = function (param, _this, file) {
    addStatus('File Zip Request in processing', 'process');
    //Create Folder and Dump DB 
    Q.post(backupJS.ajax_url, {action: 'backup-zip', file: file}, function (response) {
        response = JSON.parse(response);
        if (!response.error) {
            addStatus('File Compression complete > ' + response.file, 'success');
            param.callback(response);
        } else {
            addStatus('File Compression  Error :: ' + response.error, 'error');
        }
    });

}

function startBackup(_this) {
    resetStatus();
    addStatus('Backup Initializing...', 'info');
    Q(_this).html('<span class="dashicons dashicons-update loading"></span> Updating');
    let dbBackup = false;
    if (Q("#dbBackup").is(":checked")) {
        dbBackup = true;
        addStatus('DB Backup added into Queue.', 'info');
        dbBackupExe({calback: fileBackup,
            param: {callback: filezip,
                param: {
                    callback: function (res) {
                        addStatus('Backup Process Done, Now You can download <a href="' + backupJS.siteUrl + '/' + res.file + '">here</a>', 'success');
                        Q("#backupStart").after('<a class="backupDown button button-default" href="' + backupJS.siteUrl + '/' + res.file + '"><span class="dashicons dashicons-download"></span>Download</a>');

                        if (Q('#backupType').val() == 'ftp') {
                            addStatus('Requested to Upload via FTP', 'info');
                            uploadViaFTP(res, _this);
                        } else {
                            Q(_this).html('<span class="dashicons dashicons-saved"></span> Complete');
                        }

                    }
                }
            }
        }, _this);
    } else {
        fileBackup({callback: filezip,
            param: {
                callback: function (res) {
                    addStatus('Backup Process Done, Now You can download <a href="' + backupJS.siteUrl + '/' + res.file + '">here</a>', 'success');
                    Q("#backupStart").after('<a class="backupDown button button-default" href="' + backupJS.siteUrl + '/' + res.file + '"><span class="dashicons dashicons-download"></span>Download</a>');

                    if (Q('#backupType').val() == 'ftp') {
                        addStatus('Requested to Upload via FTP', 'info');
                        uploadViaFTP(res, _this);
                    } else {
                        Q(_this).html('<span class="dashicons dashicons-saved"></span> Complete');
                    }
                }
            }
        }, _this)
    }
    //Step 1 DB Backup
    //Step 2 File backup into Folder
    //Step 3 Fils Zip
    //Q(_this).html('<span class="dashicons dashicons-saved"></span> Done');
}

const uploadViaFTP = function (res, _this) {
    addStatus('Upload in Process', 'process');
    Q.post(backupJS.ajax_url, {action: 'backup-uploadftp', file: res.file}, function (response) {
        response = JSON.parse(response);
        if (!response.error) {
            if (response.msg) {
                addStatus(response.msg, 'success');
            }
            addStatus('Uploaded complete', 'success');
            Q(_this).html('<span class="dashicons dashicons-saved"></span> Complete');
        } else {
            addStatus('Remote server Upload Error via FTP :: ' + response.error, 'error');
            Q(_this).html('<span class="dashicons dashicons-no-alt"></span> Upload Error');
        }
    });
}

function dbBackupExe(param, _this) {
    //Create Folder and Dump DB  
    addStatus('DB Backup in Processing', 'process');
    Q.post(backupJS.ajax_url, {action: 'backup-db'}, function (response) {
        if (response == '1') {
            addStatus('DB Backup complete', 'success');
            param.calback(param.param, _this);
        } else {
            addStatus('DB Backup Error :: ' + response, 'error');
            Q(_this).html('<span class="dashicons dashicons-no-alt"></span> Try Again');
        }
    });
}

function addStatus(msg, type) {
    statusCount++
    Q(".statusProcess").removeClass('loading');
    let wrp = Q('.backup-status');
    let stIcon = "";
    if (type == 'error') {
        stIcon = '<span class="dashicons dashicons-dismiss"></span>';
    } else if (type == 'success') {
        stIcon = '<span class="dashicons dashicons-yes-alt"></span>';
    } else if (type == 'process') {
        stIcon = '<span class="dashicons dashicons-update loading statusProcess"></span>';
    } else {
        stIcon = '<span class="dashicons dashicons-arrow-right-alt"></span>';
    }
    let nS = "<div class='status-item " + type + "'><span>" + statusCount + "</span>" + stIcon + msg + "</div>";
    wrp.prepend(nS);
}
function resetStatus() {
    statusCount = 0;
    let wrp = Q('.backup-status');
    Q('.backupDown').remove();
    wrp.html('');
    wrp.css('display', 'flex');
}

function showDetailsBackupHistory(_this) {
    let inf = JSON.parse(Q(_this).attr('data-info'));
    let bodyHtm = "";
    let c = 0;
    for (const i in inf) {
        let itm = inf[i];
        c++;
        let stIcon = "";
        let type = 'error';
        if (itm.indexOf('OK') != -1) {
            type = 'success';
        }
        if (type == 'error') {
            stIcon = '<span class="dashicons dashicons-dismiss"></span>';
        } else {
            stIcon = '<span class="dashicons dashicons-yes-alt"></span>';
        }

        bodyHtm += "<div class='status-item " + type + "'><span>" + c + "</span>" + stIcon + itm + "</div>";

    }
    let dbox = new DialogBox({title: 'Backup Details log', body: "<div class=\"backup-status\">" + bodyHtm + "</div>"});

}