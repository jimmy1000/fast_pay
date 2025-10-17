<?php

namespace addons\qrcode;

use think\Addons;
use think\Loader;

/**
 * 二维码生成
 */
class Qrcode extends Addons
{

    /**
     * 插件安装方法
     * @return bool
     */
    public function install()
    {
        return true;
    }

    /**
     * 插件卸载方法
     * @return bool
     */
    public function uninstall()
    {
        return true;
    }

    /**
     * 添加命名空间
     */
    public function appInit()
    {
        if (!class_exists('\Endroid\QrCode\QrCode')) {
            require_once __DIR__ . '/library/qr-code/vendor/autoload.php';
        }
    }

}
