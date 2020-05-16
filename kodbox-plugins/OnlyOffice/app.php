<?php

class OnlyOfficePlugin extends PluginBase {
    function __construct() {
        parent::__construct();
    }
    public function regist() {
        $this->hookRegist(array(
            'user.commonJs.insert' => 'OnlyOfficePlugin.echoJs'
        ));
    }
    public function echoJs() {
        $this->echoFile('static/main.js');

    }
    public function index() {
        $path = $this->filePath($this->in['path']);
        $localFile = $this->pluginLocalFile($this->in['path']);
        $fileUrl = $this->filePathLinkOut($this->in['path']);
        $fileName = $this->fileInfo['name'];
        $fileExt = get_path_ext($this->fileInfo['name']);

        $config = $this->getConfig();
        if (substr(APP_HOST,0,8) == 'https://') {
            $dsServer = $config['apiServer-https'];
            $http_header = 'https://';
        } else {
            $dsServer = $config['apiServer-http'];
            $http_header = 'http://';
        }

        $option = array(
            'apiServer' => $http_header.$dsServer, 
            'url' => $fileUrl,
            'callbackUrl' => "", 
            'key' => md5_file($localFile), 
            'time' => filemtime($localFile), 
            'fileType' => $this->fileTypeAlias($fileExt), 
            'title' => $fileName, 
            'compact' => false, 
            'documentType' => $this->getDocumentType($fileExt), 
            'user' => Session::get('kodUser.nickName').' ('.Session::get('kodUser.name').')', 
            'UID' => Session::get('kodUser.userID'), 
            'mode' => 'view', 
            'type' => 'desktop', 
            'lang' => I18n::getType(),
            'canDownload' => false, 
            'canEdit' => false, 
            'canPrint' => false,
            );

        // 设定未登录用户的文档信息
        if (Session::get('kodUser') == null) {
            $option['UID'] = 'guest';
            $option['user'] = 'guest';
            $option['canDownload'] = false;
            $option['canPrint'] = false;
        }
        
        //可读权限检测，可读则可下载及打印
        if (Action("explorer.auth")->fileCanRead($path)) {
            $option['canDownload'] = true;
            $option['canPrint'] = true;
        }

        //可写权限检测
        if (Action("explorer.auth")->fileCanWrite($path)) {
            $option['mode'] = 'edit';
            $option['canEdit'] = true;
            $option['key'] = md5($localFile.$option['time']);
            $option['callbackUrl'] = $this->pluginApi.'save&path='.rawurlencode($path);
        }
        //内部对话框打开时，使用紧凑显示
        if ($config['openWith'] == 'dialog') {
            $option['compact'] = true;
            $option['title'] = " ";
        }
        //匹配移动端
        if (is_wap()) {
            $option['type'] = 'mobile';
        }
        
        if (strlen($dsServer) > 0) {
            include($this->pluginPath.'/php/office.php');
        } else {
            $error_msg = "OnlyOffice Document Server is not available.<br/>".
                "The API of \"".$http_header."\" must be filled.";
            show_tips($error_msg);
        }
    }
    private function getDocumentType($ext) {
        $ExtsDoc = array("doc", "docm", "docx", "dot", "dotm", "dotx", "epub", "fodt", "htm", "html", "mht", "odt", "pdf", "rtf", "txt", "djvu", "xps");
        $ExtsPre = array("fodp", "odp", "pot", "potm", "potx", "pps", "ppsm", "ppsx", "ppt", "pptm", "pptx");
        $ExtsSheet = array("csv", "fods", "ods", "xls", "xlsm", "xlsx", "xlt", "xltm", "xltx");
        if (in_array($ext,$ExtsDoc)) {
            return "text";
        } elseif (in_array($ext,$ExtsPre)) {
            return "presentation";
        } elseif (in_array($ext,$ExtsSheet)) {
            return "spreadsheet";
        } else {
            return "unknown";
        }
    }
    private function fileTypeAlias($ext) {
        if (strpos(".docm.dotm.dot.wps.wpt",'.'.$ext) !== false) {
            $ext = 'doc';
        } else if (strpos(".xlt.xltx.xlsm.dotx.et.ett",'.'.$ext) !== false) {
            $ext = 'xls';
        } else if (strpos(".pot.potx.pptm.ppsm.potm.dps.dpt",'.'.$ext) !== false) {
            $ext = 'ppt';
        }
        return $ext;
    }
    public function save() {
        if (($body_stream = file_get_contents("php://input")) === FALSE) {
            echo "Bad Request";
        }
        $data = json_decode($body_stream, TRUE);
        if ($data["status"] == 2) {
            if (($new_office_content = file_get_contents($data["url"])) === FALSE) {
                echo "Bad Response";
            } else {
                    $this->pluginCacheFileSet($_GET['path'], $new_office_content);
            }
        }
        echo "{\"error\":0}";
    }
}
