<?php
namespace app\common\api;

use app\common\model\Order;
use app\common\model\RepayOrder;
use fast\Http;
use think\Log;

class Xspay extends Base
{



    public function pay($params)
    {
        $config = $params['config'];
        $Xspay_mchNo = $config['mchId'];
        $Xspay_private_key= $config['secretKey'];
        $request_data = array();
        $request_data['mchId'] = $Xspay_mchNo;//商户号
        $request_data['productId'] = $params['channel'];//支付产品ID，固定值
        $request_data['amount'] = $params['total_money']*100;//金额
        $request_data['clientIp'] = $params['ip'];//金额
        $request_data['mchOrderNo'] =$params['sys_orderno'];//订单
        $request_data['notifyUrl']=$params['notify_url'];//异步地址
        $request_data['sign']=$this->_sign($request_data, $Xspay_private_key);//签名
        $pay_url = $params['pay_url'];//下单接口
        $resp = Http::post($pay_url, $request_data);
        $result = json_decode($resp, true);
        if ($result['retCode'] == 'SUCCESS') {
            $pay_url = $result['payUrl'];//支付URL，直接跳转或者转为二维码
            return [1,$pay_url];
        }
        return [0,$result['retMsg']];

    }


    public function notify()
    {
        Log::write('印度支付Xspay回调信息:' . http_build_query($_REQUEST), 'PAY_CHANNEL');
        $data = $_REQUEST;
        $response_data = array();
        $response_data['transaction_id'] = $data['transaction_id']; //平台订单号
        $response_data['memberid'] = $data['memberid']; //通道id
        $response_data['orderid'] = $data['orderid'];//商户订单号
        $response_data['amount'] = $data['amount'];//入账金额
        $response_data['datetime'] = $data['datetime'];//实际金额
        $response_data['returncode'] = $data['returncode'];//金额
        $response_data['msg'] = $data['msg'];//状态 支付状态，0-支付中，1-已完成，3-已超时，5或7-驳回
        $response_data['sign'] = $data['sign'];//支付成功时间
        $ddh = $response_data['orderid']; //获取商户订单号

        if(1) {
            $orderModel = Order::get(['sys_orderno' => $ddh]);
            //同一时刻 同一用户只能处理一个
            $redislock = redisLocker();
            $resource = $redislock->lock('pay.' . $orderModel['merchant_id'], 3000);   //单位毫秒  pay.商户id
            if ($resource) {
                try {
                    //更新订单状态
                    $params = [
                        'orderno' => $ddh,    //系统订单号
                        'up_orderno' =>$response_data['transaction_id'],   //上游单号
                        'amount' => $response_data['amount']*1000,    //金额
                        'utr'   => $response_data['utr']??0,//流水号
                    ];
                    $result = $this->orderFinish($params);
                    file_put_contents('result.txt', "\n".implode(', ', $result), FILE_APPEND);
                } catch (\Exception $e) {
                    exit('错误信息'.$e);
                } finally {
                    $redislock->unlock(['resource' => 'pay.' . $orderModel['merchant_id'], 'token' => $resource['token']]);
                }
            }else{
                Log::write('获取用户锁失败:'.$ddh,'error');
                exit('locked error');
            }
            exit('SUCCESS');
        }
        exit('sign error');
    }

    //签名
    private function _sign(array $data, string $key): string
    {
        ksort($data);

        $sign_str = '';

        foreach ($data as $k => $v) {
            if ($v == '' || $k == 'sign') {
                continue;
            }
            $sign_str .= $k . '=' . $v . '&';
        }
        $sign_str .= 'secretKey=' . $key;
        $sign = strtoupper(md5($sign_str));

        return $sign;
    }

    public function repay($params)
    {
        if (1) {
            $result = RepayOrder::changeRepayStatus([
                'orderno' => $params['orderno'],//订单号
                'money' => $params['payData']['money'],//金额
                'outorderno' => 1231231231,//上游订单
                'msg' => '代付申请成功',
                'status'=>'2',
            ]);
            return $result;
        }
        return [0,'ceshi'];
    }
    public function repaynotify()
    {
        Log::write('Xspay代付回调信息:' . http_build_query($_REQUEST), 'REPAY_CHANNEL');
        if(1) {
            if ($_REQUEST['returncode'] == '11') {
                $result = RepayOrder::changeRepayStatus([
                    'orderno' => $_REQUEST['orderid'],//订单号
                    'money' => $_REQUEST['amount']*2000,//金额
                    'outorderno' => $_REQUEST['transaction_id'],//上游订单号
                    'fee'=>$_REQUEST['fee']??0,
                    'utr'=>$_REQUEST['utr']??0,
                    'msg' => '代付成功',
                    'status' => '1',//成功状态为1,0为失败
                ]);
                Log::write('xspay代付异步成功通知:'.implode($result),'success');
                // Log::record('代付异步通知！','CHANNEL');
            }else{
                $result = RepayOrder::changeRepayStatus([
                    'orderno' => $_REQUEST['orderid'],
                    'money' => $_REQUEST['amount'],
                    'outorderno' => $_REQUEST['transaction_id'],
                    'msg' =>$_REQUEST['msg'] ,
                    'status' => '0',
                ]);
                Log::write('xSpay代付异步失败通知:'.implode($result),'error');
            }
            exit('SUCCESS');
        }
        exit('sign error');

    }
}