<?php

class OnlyOfficePlugin extends PluginBase {
    function __construct() {
        parent::__construct();
    }
    public function regiest() {
        $this->hookRegiest(array(
            'user.commonJs.insert' => 'OnlyOfficePlugin.echoJs'
        ));
    }
    public function echoJs($st,$act) {
        if ($this->isFileExtence($st,$act)) {
            $this->echoFile('static/main.js');
        }
    }
    public function index() {
        if (substr($this->in['path'],0,4) == 'http') {
            $path = $fileUrl = $this->in['path'];
        } else {
            $path = _DIR($this->in['path']);
            $fileUrl = _make_file_proxy($path);
            if (!file_exists($path)) {
                show_tips(LNG('not_exists'.$path));
            }
        }
        $parentDir = get_path_father($path);
	    $fileName = get_path_this(rawurldecode($this->in['path']));
        $fileExt = get_path_ext($path);
        
        
        //mkdir($parentDir.'/.hist-'.$fileName);
        //mkfile($parentDir.'/.hist-'.$fileName.'/histdata.txt');
        //explorer::mkfile($parentDir.'/histdata.txt');
        
        $config = $this->getConfig();
        if (substr(APP_HOST,0,8) == 'https://') {
            $dsServer = $config['apiServer-https'];
            $http_header = 'https://';
        } else {
            $dsServer = $config['apiServer-http'];
            $http_header = 'http://';
        }
        
        //https://api.onlyoffice.com/editors/callback
        //https://api.onlyoffice.com/editors/history
        //https://blog.51cto.com/8200238/2085279
        //https://github.com/ONLYOFFICE/document-server-integration/blob/master/web/documentserver-example/php/webeditor-ajax.php
        $option = array(
            'apiServer' => $http_header.$dsServer, 
            'url' => $fileUrl,
            'callbackUrl' => "", 
            'key' => file_hash_simple($path), 
            'fileType' => $this->fileTypeAlias($fileExt), 
            'title' => $fileName, 
            'compact' => false, 
            'documentType' => $this->getDocumentType($fileExt), 
            'user' => $_SESSION['kodUser']['nickName'].' ('.$_SESSION['kodUser']['name'].')', 
            'UID' => $_SESSION['kodUser']['userID'], 
            'mode' => 'view',
            'type' => 'desktop', 
            'lang' => I18n::getType(),
            'canDownload' => true, 
            'canEdit' => false, 
            'canPrint' => true,
            );
            
        // 设定未登录用户的文档信息
        if (!isset($_SESSION['kodUser'])) {
            $option['UID'] = 'guest';
            $option['user'] = 'guest';
            $option['canDownload'] = false;
            $option['canPrint'] = false;
        }
        
        if (!$GLOBALS['isRoot']) {
            /** * 下载&打印&导出:权限取决于文件是否可以下载;(管理员无视所有权限拦截) * 1. 当前用户是否允许下载 * 2. 所在群组文件，用户在群组内的权限是否可下载 * 3. 文件分享,限制了下载 */
            if ($GLOBALS['auth'] && !$GLOBALS['auth']['explorer.fileDownload']) {
                $option['canDownload'] = false;
                $option['canPrint'] = false;
            }
            if ($GLOBALS['kodShareInfo'] && $GLOBALS['kodShareInfo']['notDownload'] == '1') {
                $option['canDownload'] = false;
                $option['canPrint'] = false;
            }
            if ($GLOBALS['kodPathRoleGroupAuth'] && !$GLOBALS['kodPathRoleGroupAuth']['explorer.fileDownload']) {
                $option['canDownload'] = false;
                $option['canPrint'] = false;
            }
        }
        //可写权限检测
        if (!$config['previewMode'] && check_file_writable_user($path)) {
            $option['mode'] = 'edit';
            $option['canEdit'] = true;
            $option['callbackUrl'] = $this->pluginHost.'php/save.php?path='.rawurlencode($path);
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
}
