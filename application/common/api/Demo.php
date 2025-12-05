<?php
/**
 * Demo.php
 * 易聚合支付系统
 * =========================================================
 * 请尊重开发人员劳动成果，严禁使用本系统转卖、销售或二次开发后转卖、销售等商业行为。
 * 本源码仅供技术学习研究使用,请勿用于非法用途,如产生法律纠纷与作者无关。
 * =========================================================
 * @author : 666666@qq.com
 * @date : 2019-05-12
 */

namespace app\common\api;

use app\common\model\Pay;
use think\Log;

class Demo extends Base
{

    public function pay($params)
    {
        return [1,'http://www.qq.com'];
    }
    /**
     * 代付接口
     * @param $params
     */
    public function repay($params)
    {

        $result = Pay::changePayStatus([
            'orderno' => $params['orderno'],
            'money' => $params['payData']['money'],
            'outorderno' => '测试单',
            'msg' => '测试代付成功1',
            'status' => '1'
        ]);

        return $result;
    }

    public function repaynotify()
    {
        Log::record('代付异步通知！','CHANNEL');
    }

    /**
     $params = array(
    'orderno' => $payOrderModel['orderno'],     //订单号
    'params' => $accountModel['params'],        //配置参数
    'payData' => $payModel->toArray()           //代付订单表数据
    );
     * 查询订单
     * @param $params
     */
    public function repayselect($params){

        //@todo 可根据查询结果来更新订单状态

//        $result = Pay::changePayStatus([
//            'orderno' => $params['orderno'],
//            'money' => $params['payData']['money'],
//            'outorderno' => '测试单',
//            'msg' => '测试代付成功',
//            'status' => '0'
//        ]);


        return [0,'请求失败'];
    }

}