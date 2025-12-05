<?php
namespace app\common\api;

use app\common\model\Order;
use app\common\model\Pay;
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
        Log::write('印度支付Xspay回调信息:' . http_build_query($_REQUEST), 'CHANNEL');
        $data = $_REQUEST;
        $response_data = array();
        $response_data['payOrderId'] = $data['payOrderId']; //平台订单号
        $response_data['mchId'] = $data['mchId'];   //商户id
        $response_data['productId'] = $data['productId']; //通道id
        $response_data['mchOrderNo'] = $data['mchOrderNo'];//商户订单号
        $response_data['income'] = $data['income'];//入账金额
        $response_data['realAmount'] = $data['realAmount'];//实际金额
        $response_data['amount'] = $data['amount'];//金额
        $response_data['status'] = $data['status'];//状态 支付状态，0-支付中，1-已完成，3-已超时，5或7-驳回
        $response_data['paySuccessTime'] = $data['paySuccessTime'];//支付成功时间
        $response_data['sign'] = $data['sign'];//签名
        $ddh = $response_data['mchOrderNo']; //获取商户订单号
        $config = $this->getOrderConfig($ddh);//获取订单上游配置
        $key = $config['secretKey']; //秘钥，请获取最新秘钥

        $get_sign = $this->_sign($response_data,$key);//执行签名

        if($response_data['sign']==$get_sign) {
            $orderModel = Order::get(['sys_orderno' => $ddh]);
            //同一时刻 同一用户只能处理一个
            $redislock = redisLocker();
            $resource = $redislock->lock('pay.' . $orderModel['merchant_id'], 3000);   //单位毫秒  pay.商户id
            if ($resource) {
                try {
                    //更新订单状态
                    $params = [
                        'orderno' => $ddh,    //系统订单号
                        'up_orderno' =>$data['payOrderId'],   //上游单号
                        'amount' => $response_data['amount']/100      //金额
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
        $config = $params['params'];
        $sys_orderno =$params['orderno'];
        $payData=$params['payData'];
        $req_info=$payData['req_info'];
        $notifyUrl=$params['notifyUrl'];
        $Xspay_mchNo = $config['mchId'];
        $Xspay_key= $config['secretKey'];
        $request_data = array();
        $request_data['mchId'] = $Xspay_mchNo;//商户号
        $request_data['productId'] = '3020';//银行通道
        $request_data['mchOrderNo'] =$sys_orderno;//订单
        $request_data['amount'] =$req_info['money']*100;//金额
        $request_data['clientIp'] = '0.0.0.0';//ip
        $request_data['userName']=$req_info['name'];;//姓名
        $request_data['cardNumber']=$req_info['account'];//银行卡号
        $request_data['bankName']=$req_info['bankname'];//银行名
        $request_data['ifscCode']=!empty($req_info['ifsc']) ? $req_info['ifsc'] : '';//银行IFSC代码//ifsc
        $request_data['accountType']='PERSONAL_BANK';//账户类型
        $request_data['bankDetail']='PERSONAL_BANK';//银行信息
        $request_data['validateUserName']=$req_info['name'];//会员姓名
        $request_data['notifyUrl']=$notifyUrl;//异步地址
        $request_data['sign']=$this->_sign($request_data, $Xspay_key);//签名
        $pay_url = $config['pay_url'];//下单接口
        $resp = Http::post($pay_url, $request_data);
        $result = json_decode($resp, true);
        if ($result['retCode'] == 'SUCCESS') {
            $result = Pay::changePayStatus([
                'orderno' => $result['mchOrderNo'],//订单号
                'money' => $result['amount']/100,//金额
                'outorderno' => $result['P8202408231429448080'],//上游订单
                'msg' => '代付申请成功',
                'status'=>'2',
            ]);
            return $result;
        }
        return [0,$result['errmsg']];
    }
    public function repaynotify()
    {
        Log::write('印度支付Xspay代付回调信息:' . http_build_query($_REQUEST), 'CHANNEL');
        $data = $_REQUEST;
        $response_data = array();
        $response_data['payOrderId'] = $data['payOrderId']; //平台订单号
        $response_data['mchId'] = $data['mchId'];   //商户id
        $response_data['productId'] = $data['productId']; //通道id
        $response_data['mchOrderNo'] = $data['mchOrderNo'];//商户订单号
        $response_data['amount'] = $data['amount'];//入账金额
        $response_data['income'] = $data['income'];//实际金额
        $response_data['status'] = $data['status'];//状态 支付状态，0-支付中，1-已完成，3-已超时，5或7-驳回
        $response_data['paySuccessTime'] = $data['paySuccessTime'];//支付成功时间
        $response_data['sign'] = $data['sign'];//签名
        $ddh = $response_data['mchOrderNo']; //获取商户订单号
        $config = $this->getPayConfig($ddh);//获取订单上游配置
        $key = $config['secretKey']; //秘钥，请获取最新秘钥
        $get_sign = $this->_sign($response_data,$key);//执行签名
        if($response_data['sign']==$get_sign) {
            if ($response_data['status'] == 'success') {
                $result = Pay::changePayStatus([
                    'orderno' => $response_data['merchant_orderno'],//订单号
                    'money' => $response_data['amount'],//金额
                    'outorderno' => $response_data['orderno'],//上游订单号
                    'msg' => '代付成功',
                    'status' => '1',//成功状态为1,0为失败
                ]);
                Log::write('xspay代付异步成功通知:'.implode($result),'success');
                // Log::record('代付异步通知！','CHANNEL');
            }else{
                $result = Pay::changePayStatus([
                    'orderno' => $response_data['merchant_orderno'],
                    'money' => $response_data['amount'],
                    'outorderno' => $response_data['orderno'],
                    'msg' =>$response_data['retMsg'] ,
                    'status' => '0',
                ]);
                Log::write('xSpay代付异步失败通知:'.implode($result),'error');
            }
            exit('SUCCESS');
        }
        exit('sign error');

    }
}