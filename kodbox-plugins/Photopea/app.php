<?php

class PhotopeaPlugin extends PluginBase {
    function __construct() {
        parent::__construct();
    }
    public function regist() {
        $this->hookRegist(array(
            'user.commonJs.insert' => 'PhotopeaPlugin.echoJs'
        ));
    }
    public function echoJs() {
        $this->echoFile('static/main.js');
    }
    public function index() {
        $path = $this->filePath($this->in['path']);
        $localFile = $this->pluginLocalFile($path);
        $fileName = $this->fileInfo['name'];
        $fileExt = get_path_ext($this->fileInfo['name']);

        $fileUrl = $this->filePathLinkOut($this->in['path']).'&name=/'.$fileName;
        
        //可写权限检测
        $saveUrl = $this->pluginApi.'saveImg&unwritable=1';
        if (Action("explorer.auth")->fileCanWrite($path)) {
            $saveUrl = $this->pluginApi.'saveImg&cache='.rawurlencode($localFile).'&path='.rawurlencode($path);
        }
        
        $config = $this->getConfig();
        
        $theme = $config['theme'];
        $lang = I18n::getType();
        $fullUri = '{"files":["'.$fileUrl.'"],"resources":[],"server":{"version":1,"url":"'.$saveUrl.'","formats":["'.$fileExt.'"]},"environment":{"theme":'.$theme.',"lang":"'.$lang.'"},"script":""}';

        //        show_tips($this->in['file']);
        header('Location:'.$this->pluginHost.'static/photopea/#'.($fullUri));
        //header('Location:https://www.photopea.com/#'.($fullUri));

    }
    public function saveImg() {
        if (isset($_GET['unwritable'])) {
            echo '{"message": "Unwritable!"}';
        } else {
            header('Content-type: application/json; charset="utf-8"');

            $fi = fopen("php://input", "rb");
            $p = JSON_decode(fread($fi, 2000));
            $fo = fopen($_GET['cache'],"wb");
            while ($buf = fread($fi,50000)) {
                fwrite($fo,$buf);
            }
            fclose($fi);
            fclose($fo);
            $this->pluginCacheFileSet($_GET['path'], file_get_contents($_GET['cache']));
            del_file($_GET['cache']);
            echo '{"message": "Saved!"}';
        }
    }
}