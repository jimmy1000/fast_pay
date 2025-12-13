<?php
namespace app\common\api;

use app\common\model\Order;
use app\common\model\RepayOrder;
use fast\Http;
use think\Log;

class Tatapay extends Base
{



    public function pay($params)
    {
        $config = $params['config'];
        $Tatapay_mchNo = $config['mchId'];
        $Tatapay_ds_key= $config['ds_key'];
        $request_data = array();
        $request_data['merchantCode'] = $Tatapay_mchNo;//商户号
        $request_data['channelPayType'] =2;//支付产品ID，固定值
        $request_data['amount'] = number_format($params['total_money'] * 100, 0, '', '');//金额到分
        $request_data['currency'] = 'INR';//币种
        $request_data['orderNo'] =$params['sys_orderno'];//订单
        $request_data['notifyUrl']=$params['notify_url'];//异步地址
        $request_data['clientIp']=$params['ip'];//ip地址
        $request_data['cname']=$params['name'];//名字
        $request_data['cemail']=$params['email'];//邮箱
        $request_data['cmobile']=$params['phone'];//手机号
        $request_data['version']='2.0';//
        $request_data['sign_type']='MD5';//
        $request_data['sign']=$this->_sign($request_data, $Tatapay_ds_key);//签名
        $pay_url = $params['pay_url'];//下单接口
        $resp = Http::send_json($pay_url, $request_data);
        $result = json_decode($resp, true);
        if ($result['code'] == '0') {
            $pay_url = $result['data']['payUrl'];//支付URL，直接跳转或者转为二维码
            return [1,$pay_url];
        }
        return [0,$result['message']];

    }


    public function notify()
    {
        $rawData = file_get_contents("php://input");
        Log::write('印度支付Tatapay回调信息 代收回调信息:' .$rawData, 'PAY_CHANNEL');
        $rawData=json_decode($rawData,true);
        $response_data = array();
        $response_data['orderNo'] = $rawData['orderNo']; //平台订单号
        $response_data['merchantCode'] = $rawData['merchantCode'];   //商户id
        $response_data['merchantOrderNo'] = $rawData['merchantOrderNo'];//商户订单号
        $response_data['amount'] =  $rawData['amount'];//金额
        $response_data['status'] = $rawData['status'];//状态 支付状态，0-支付中，1-已完成，3-已超时，5或7-驳回
        $response_data['createTime'] = $rawData['createTime'];//支付成功时间
        $response_data['sign'] = $rawData['sign'];//签名
        $ddh = $response_data['merchantOrderNo']; //获取商户订单号
        $config = $this->getOrderConfig($ddh);//获取订单上游配置
        $key = $config['ds_key']; //秘钥，请获取最新秘钥
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
                        'up_orderno' =>$rawData['orderNo'],   //上游单号
                        'amount' => number_format($rawData['amount'] / 100, 0, '', '')     //金额
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
        $sign_str .= 'key=' . $key;
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
        $Tatapay_mchNo = $config['mchId'];
        $Tatapay_df_key= $config['df_key'];//代付key
        $request_data = array();
        $request_data['merchantCode'] = $Tatapay_mchNo;//商户号
        $request_data['orderNo'] =$sys_orderno;//订单
        $request_data['amount'] = number_format($req_info['money'] * 100, 0, '', '');//金额到分
        $request_data['notifyUrl']=$notifyUrl;//异步地址
        $request_data['currency'] = 'INR';//币种
        $request_data['payType'] ='0';//支付产品ID，固定值
        $request_data['accNo']=$req_info['account'];//收款账号
        $request_data['accName']=$req_info['name'];//名字
        $request_data['phone']=$req_info['phone'];//手机号
        $request_data['email']=$req_info['email'];//邮箱
        $request_data['bankCode']=$req_info['ifsc'];//银行编码 ifsc
        $request_data['version']='2.0';//
        $request_data['sign_type']='MD5';//
        $request_data['sign']=$this->_sign($request_data, $Tatapay_df_key);//签名
        $pay_url = $config['repay_url'];//下单接口
        $resp = Http::send_json($pay_url, $request_data);
        $result = json_decode($resp, true);
        if ($result['code'] == '0') {
            $result = RepayOrder::changePayStatus([
                'orderno' => $result["data"]['merchantOrderNo'],//订单号
                'money' => number_format($req_info['money'] * 100, 0, '', ''),//金额
                'outorderno' => $result["data"]['orderNo'],//上游订单
                'msg' => '代付申请成功',
                'status'=>'2',
            ]);
            return $result;
        }
        return [0,$result['message']];
    }
    public function repaynotify()
    {
        $rawData = file_get_contents("php://input");
        Log::write('印度支付Tatapay回调信息 代付回调信息:' .$rawData, 'REPAY_CHANNEL');
        $data = json_decode($rawData,true);
        $response_data = array();
        $response_data['merchantCode'] = $data['merchantCode'];   //商户id
        $response_data['merchantOrderNo'] = $data['merchantOrderNo'];//商户订单号
        $response_data['orderNo'] = $data['orderNo']; //平台订单号
        $response_data['amount'] = $data['amount'];//入账金额
        $response_data['status'] = $data['status'];//状态 支付状态，0-支付中，1-已完成，3-已超时，5或7-驳回
        $response_data['errorMsg'] = $data['errorMsg'];//错误消息
        $response_data['successTime'] = $data['successTime'];//成功时间
        $response_data['createTime'] = $data['createTime'];//创建时间
        $response_data['sign'] = $data['sign'];//签名
        $ddh = $response_data['merchantOrderNo']; //获取商户订单号
        $config = $this->getPayConfig($ddh);//获取订单上游配置

        $key = $config['df_key']; //秘钥，请获取最新秘钥
        $get_sign = $this->_sign($response_data,$key);//执行签名
        if($response_data['sign']==$get_sign) {
            if ($response_data['status'] == '2') {
                $result = RepayOrder::changePayStatus([
                    'orderno' => $response_data['merchantOrderNo'],//订单号
                    'money' => number_format($response_data['amount'] / 100, 0, '', ''),//金额
                    'outorderno' => $response_data['orderNo'],//上游订单号
                    'msg' => '代付成功',
                    'status' => '1',//成功状态为1,0为失败
                ]);
                Log::write('Tatapay代付异步成功通知:'.implode($result),'success');
                // Log::record('代付异步通知！','CHANNEL');
            }else{
                $result = RepayOrder::changePayStatus([
                    'orderno' => $response_data['merchantOrderNo'],
                    'money' =>number_format($response_data['amount'] / 100, 0, '', ''),//金额
                    'outorderno' => $response_data['orderNo'],
                    'msg' =>$response_data['errorMsg'] ,
                    'status' => '0',
                ]);
                Log::write('Tatapay代付异步失败通知:'.implode($result),'error');
            }
            exit('SUCCESS');
        }
        exit('sign error');

    }
}