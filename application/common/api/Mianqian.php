<?php
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
        $channel = $params['channel'] ?? '';
        try {
            $url = Mq::create($params['sys_orderno'], $params['total_money'], $channel);
            return [1, $url];
        } catch (\Exception $e) {
            Log::write('免签支付创建订单失败，订单号：' . ($params['sys_orderno'] ?? '') . '，错误：' . $e->getMessage(), 'error');
            return [0, $e->getMessage()];
        }
    }

    public function backurl($orderno = '')
    {
        return parent::backurl($orderno);
    }

    /**
     * 回调处理
     */
    public function notify()
    {
        $price = $_REQUEST['price'] ?? '';
        $channel = $_REQUEST['channel'] ?? '';
        $sign = $_REQUEST['sign'] ?? '';
        $utr = $_REQUEST['utr'] ?? '';
        
        $addon_config = get_addon_config('mq');
        $key = $addon_config['secretkey'] ?? '';
        
        //验证签名
        if(md5(md5($price.$channel).$key) !== $sign ){
            Log::write('免签支付回调签名验证失败，金额：' . $price . '，渠道：' . $channel, 'PAY_CHANNEL');
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
        $orderNo = Mq::findOrderno($price, $channel);
        if(!$orderNo){
            Log::write('警告：收到订单外的收款信息，收到来自' . $channel . '金额' . $price . '但不是收款系统订单中的付款信息!', 'PAY_CHANNEL');
            $data = [
                'code'=>1,
                'msg'=>'收到了系统订单外的收款',
                'data'=>'',
                'utr'=>$utr,
                'url'=>'/api/notify.html',
                'wait'=>3
            ];
            exit(json_encode($data,JSON_UNESCAPED_UNICODE));
        }
        
        $orderModel = Order::get(['sys_orderno'=>$orderNo]);
        if(is_null($orderModel)){
            Log::write('免签支付回调：订单不存在，订单号：' . $orderNo, 'error');
            $data = [
                'code'=>1,
                'msg'=>'订单不存在',
                'data'=>'',
                'url'=>'/api/notify.html',
                'wait'=>3
            ];
            exit(json_encode($data,JSON_UNESCAPED_UNICODE));
        }
        //同一时刻 同一用户只能处理一个
        $redislock = redisLocker();
        $lockKey = 'pay.' . $orderModel['merchant_id'];
        $resource = $redislock->lock($lockKey, 3000);   //单位毫秒
        
        if($resource){

            try {
                //更新订单状态
                $params = [
                    'orderno' => $orderNo,    //系统订单号
                    'up_orderno' => '公司直营',   //上游单号
                    'utr' => $utr,
                    'amount' => $orderModel['total_money'] / 1       //金额
                ];
               
                $result = $this->orderFinish($params);
                //检查订单处理结果
                if($result[0] != 1){
                    Log::write('免签支付回调：订单处理失败，订单号：' . $orderNo . '，错误：' . $result[1], 'error');
                }
                
                //在这里也要更新一下免签订单中的状态
                Mq::orderFinish($orderNo);
            } catch (\Exception $e) {
                Log::write('免签支付回调处理异常，订单号：' . $orderNo . '，错误：' . $e->getMessage(), 'error');
            } finally {
                $redislock->unlock(['resource' => $lockKey, 'token' => $resource['token']]);
            }
        } else {
            Log::write('获取锁失败！订单号:'.$orderNo, 'error');
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