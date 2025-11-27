<?php
/**
 * Mq.php
 * 易聚合支付系统
 * =========================================================
 * 请尊重开发人员劳动成果，严禁使用本系统转卖、销售或二次开发后转卖、销售等商业行为。
 * 本源码仅供技术学习研究使用,请勿用于非法用途,如产生法律纠纷与作者无关。
 * =========================================================
 * @author : 666666@qq.com
 * @date : 2019-06-09
 */

namespace app\common\api;

use addons\mq\library\Mq;
use app\common\model\Order;
use app\common\model\RepayOrder;
use fast\Http;
use think\Log;

class Mianqian extends Base
{


    public function pay($params)
    {
        //$type = ($params['channel'] == 'zfbewm' || $params['channel'] == '101') ? 'alipay' : 'wechat';
        $channel = $params['channel'];
        try {

            $url = \addons\mq\library\Mq::create($params['sys_orderno'], $params['total_money'], $channel);
            return [1, $url];
        } catch (\Exception $e) {
            return [0, $e->getMessage()];
        }
    }

    public function backurl($orderno = '')
    {
        return parent::backurl();
    }

    /**
     * 回调处理
     */
    public function notify()
    {

        $price = $_REQUEST['price'];
        $channel = $_REQUEST['channel'];
        $sign = $_REQUEST['sign'];
        $utr = $_REQUEST['utr'];
        //   $r = $_REQUEST['r'];
        $addon_config = get_addon_config('mq');
        $key = $addon_config['secretkey'];
        //验证签名
        if(md5(md5($price.$channel).$key) !== $sign ){
            $data = [
                'code'=>1,
                'msg'=>'处理订单失败，秘钥有误',
                'data'=>'',
                'url'=>'/api/notify.html',
                'wait'=>3
            ];
            exit(json_encode($data,JSON_UNESCAPED_UNICODE));
        }

        //找到是否有这个订单
        $orderNo = Mq::findOrderno($price,$channel);
        if(!$orderNo){
            Log::write('警告：收到订单外的收款信息，收到来自' .$channel . '金额' .$price . '但不是收款系统订单中的付款信息!','CHANNEL');
            $data = [
                'code'=>1,
                'msg'=>'收到了系统订单外的收款',
                'data'=>'',
                'utr'=>$utr??'',
                'url'=>'/api/notify.html',
                'wait'=>3
            ];
            exit(json_encode($data,JSON_UNESCAPED_UNICODE));
        }

        $orderModel = Order::get(['sys_orderno'=>$orderNo]);
        //同一时刻 同一用户只能处理一个
        $redislock = redisLocker();
        $resource = $redislock->lock('pay.' . $orderModel['merchant_id'], 3000);   //单位毫秒

        if(!$resource){
            sleep(1);
            $resource = $redislock->lock('pay.' . $orderModel['merchant_id'], 3000);   //单位毫秒
        }
        if($resource){
            try {
                //更新订单状态
                $params = [
                    'orderno' =>$orderNo,    //系统订单号
                    'up_orderno' => '公司直营',   //上游单号
                    'utr' => $utr,
                    'amount' => $orderModel['total_money'] / 1       //金额
                ];
                $result = $this->orderFinish($params);
                //在这里也要更新一下免签订单中的状态
                Mq::orderFinish($orderNo);
            } catch (\Exception $e) {

            } finally {
                $redislock->unlock(['resource' =>'pay.' . $orderModel['merchant_id'], 'token' => $resource['token']]);
            }
        }else{
            Log::write('获取锁失败！订单号:'.$orderNo,'error');

            $data = [
                'code'=>1,
                'msg'=>'获取锁失败',
                'data'=>'',
                'url'=>'/api/notify.html',
                'wait'=>3
            ];
            exit(json_encode($data,JSON_UNESCAPED_UNICODE));

        }

        $data = [
            'code'=>1,
            'msg'=>'系统回调成功',
            'data'=>'',
            'url'=>'/api/notify.html',
            'wait'=>3
        ];

        exit(json_encode($data,JSON_UNESCAPED_UNICODE));


    }
    public function repay($params)
    {
//        if (in_array($result['code'], ['200'])) {
        if (1){
            $result = RepayOrder::changePayStatus([
                'orderno' => $params['orderno'],//订单号
                'money' => $params['payData']['money'],//金额
                'outorderno' => 1231231,//上游订单
                'msg' => '代付申请成功',
                'status' => '2',
            ]);
            return $result;
        }
        Log::write('CCpay代付提单失败: ' . 123123, 'error');
        return [0,'123123'];
    }

}