<?php
/**
 * App.php
 * 易聚合支付系统
 * =========================================================

 * ----------------------------------------------
 *
 *
 * 请尊重开发人员劳动成果，严禁使用本系统转卖、销售或二次开发后转卖、销售等商业行为。
 * 本源码仅供技术学习研究使用,请勿用于非法用途,如产生法律纠纷与作者无关。
 * =========================================================
 * @author : 666666@qq.com
 * @date : 2019-06-09
 */

namespace app\api\controller;

use app\common\controller\Api;
use think\Log;

class  Mq extends Api{

    protected $noNeedLogin = "*";


    /**
     * app检测
     */
    public function index()
    {
        $config = get_addon_config('mq');

        $api_url = $_GET['apiurl'];

        $key = $config['secretkey'];

        if(md5(md5($api_url).$key)!=$_GET['sign']){
            return "{ code: 0, msg: '秘钥不正确!', data: '', url: '', wait: 3 }";
        }
        return  "{ code: 1, msg: '配置成功!', data: '', url: '', wait: 3 }";
    }

    /**
     * 收到推送的通知
     */
    public function notify(){

        Log::write('收到免签通知:'.http_build_query($_REQUEST));

        $api = loadApi("mianqian");

        $result = $api->notify();

    }
}