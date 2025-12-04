<?php
namespace app\common\api;

use app\common\model\Order;
use app\common\model\Pay;
use Composer\Package\Loader\ValidatingArrayLoader;
use fast\Http;
use think\Log;

class Transafe extends Base
{



    public function pay($params)
    {
        $config = $params['config'];
        $Transafe_mchNo = $config['mchId'];
        $Transafe_appId = $config['appId'];
        $Transafe_private_key= $config['private_key'];
        $request_data = array();
        $request_data['mchId'] = $Transafe_mchNo;//商户号
        $request_data['txChannel'] = 'TX_INDIA_001';//支付产品ID，固定值
        $request_data['appId'] = $Transafe_appId;//appid
        $request_data['timestamp'] = $params['timestamp'];//时间戳
        $request_data['mchOrderNo'] =$params['sys_orderno'];//订单
        $request_data['bankCode'] = $params['channel'];//银行通道
        $request_data['amount'] = $params['total_money'];//金额
        $request_data['name']=$params['name'];//姓名
        $request_data['phone']=$params['phone'];//手机号
        $request_data['email']=$params['email'];//邮件
        $request_data['productInfo']=$params['productInfo'];//产品信息
        $request_data['notifyUrl']=$params['notify_url'];//异步地址
        $request_data['returnUrl']=$params['return_url'];//同步地址
        $request_data['sign']=$this->createSign($request_data, $Transafe_private_key);//签名
        $pay_url = $params['pay_url'];//下单接口
        $resp = Http::post($pay_url, json_encode($request_data));
        $result = json_decode($resp, true);
        if ($result['status'] == 200) {
            $pay_url = $result['data']['link'];//支付URL，直接跳转或者转为二维码
            return [1,$pay_url];
        }
        return [0,$result['msg']];

    }


    public function notify()
    {
        $rawData = file_get_contents("php://input");
        Log::write('印度支付Transafe回调信息:' .$rawData, 'CHANNEL');
        $data = json_decode($rawData, true);
        $response_data = array();
        $response_data['status'] = $data['status']; //状态
        $response_data['memberId'] = $data['memberId'];   //商户id
        $response_data['mchOrderNo'] = $data['mchOrderNo']; //商户订单号
        $response_data['platOrderNo'] = $data['platOrderNo']; //平台订单号
        $response_data['orderStatus'] = $data['orderStatus'];//订单状态
        $response_data['fee'] = $data['fee'];//手续费
        $response_data['amount'] = $data['amount'];//金额
        $response_data['msg'] = $data['msg'];//信息
        $response_data['timeEnd'] = $data['timeEnd'];//完结时间,时间戳
        $response_data['sign'] = $data['sign'];//签名
        $ddh = $response_data['mchOrderNo']; //获取商户订单号
        $config = $this->getOrderConfig($ddh);//获取订单上游配置
        $key = $config['up_public_key']; //秘钥，请获取上游秘钥
        $get_sign = $this->verifySign($response_data,$key);//执行签名
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
                        'up_orderno' => $response_data['platOrderNo'],   //上游单号
                        'amount' => $response_data['amount']      //金额
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
    private function createSign($params, $privateKey) {
        $signString = $this->getSignString($params);
        $privateKey = '-----BEGIN PRIVATE KEY-----' . "\n" . $privateKey . "\n" . '-----END PRIVATE KEY-----';
        $res = openssl_get_privatekey($privateKey);
        if ($res === false) {
            die("无法获取私钥\n");
        }

        openssl_sign($signString, $sign, $res);
        openssl_free_key($res);
        return base64_encode($sign);
    }
    /**
     * 公钥验证签名
     * @param array $params
     * @param string $pubKey
     * @return bool
     */
    public function verifySign(array $params, string $pubKey): bool
    {
        $sign = $params['sign'] ?? '';
        $signString = self::getSignString($params);
        $pubKey = '-----BEGIN PUBLIC KEY-----' . "\n" . $pubKey . "\n" . '-----END PUBLIC KEY-----';
        // 转换为 openssl 格式的密钥
        $res = openssl_get_publickey($pubKey);

        // 调用 openssl 内置方法验签，返回 bool 值
        $result = (bool)openssl_verify($signString, base64_decode($sign), $res);
        // 释放资源
        openssl_free_key($res);

        return $result;
    }


    private function getSignString($params): string
    {
        unset($params['sign']);
        ksort($params);
        $array = array();
        foreach ($params as $key => $value) {
            if (!empty($value)) {
                $array[] = $key . '=' . $value;
            }
        }
        return implode("&", $array);
    }

    public function repay($params)
    {
        $config = $params['params'];
        $sys_orderno =$params['orderno'];
        $payData=$params['payData'];
        $req_info=$payData['req_info'];
        $notifyUrl=$params['notifyUrl'];
        $timestamp= time();
        $Transafe_mchNo = $config['mchId'];
        $Transafe_appId = $config['appId'];
        $Transafe_private_key= $config['private_key'];
        $request_data = array();
        $request_data['mchId'] = $Transafe_mchNo;//商户号
        $request_data['txChannel'] = 'TX_INDIA_001';//支付产品ID，固定值
        $request_data['appId'] = $Transafe_appId;//appid
        $request_data['timestamp'] = $timestamp;//时间戳
        $request_data['mchOrderNo'] =$sys_orderno;//订单
        $request_data['name']=$req_info['name'];//姓名
        $request_data['phone']=$req_info['phone'];//手机号
        $request_data['email']=$req_info['email'];//邮件
        $request_data['bankCode'] = $req_info['bankCode'];//银行通道
        $request_data['account']=$req_info['account'];//账号
        $request_data['amount'] = $req_info['money'];//金额
        $request_data['notifyUrl']=$notifyUrl;//异步通知地址
        $request_data['ifsc']=!empty($req_info['ifsc']) ? $req_info['ifsc'] : '';//异步地址
        $request_data['sign']=$this->createSign($request_data, $Transafe_private_key);//签名
        $pay_url = $config['repay_url'];//代付下单接口
        $resp = Http::post($pay_url, json_encode($request_data));
        $result = json_decode($resp, true);
        if ($result['status'] == 200) {
            $result = Pay::changePayStatus([
                'orderno' => $result['data']['mchOrderNo'],
                'money' => $result['data']['amount'],
                'outorderno' => $result['data']['platOrderNo'],
                'msg' => '代付申请成功',
                'status'=>'2',
            ]);
            return $result;
        }
        return [0,$result['msg']];
    }

    public function repaynotify()
    {
        $rawData = file_get_contents("php://input");
        Log::write('印度支付Transafe 代付回调信息:' .$rawData, 'CHANNEL');
        $data = json_decode($rawData, true);
        $response_data = array();
        $response_data['status'] = $data['status']; //状态
        $response_data['memberId'] = $data['memberId'];   //商户id
        $response_data['mchOrderNo'] = $data['mchOrderNo']; //商户订单号
        $response_data['platOrderNo'] = $data['platOrderNo']; //平台订单号
        $response_data['orderStatus'] = $data['orderStatus'];//订单状态
        $response_data['amount'] = $data['amount'];//金额
        $response_data['fee'] = $data['fee'];//手续费
        $response_data['msg'] = $data['msg'];//信息
        $response_data['timeEnd'] = $data['timeEnd'];//完结时间,时间戳
        $response_data['sign'] = $data['sign'];//签名
        $ddh = $response_data['mchOrderNo']; //获取商户订单号
        $config = $this->getPayConfig($ddh);//获取订单上游配置
        $key = $config['up_public_key']; //秘钥，请获取最新秘钥
        $get_sign = $this->verifySign($response_data,$key);//执行签名
        if($response_data['sign']==$get_sign) {
            if ($response_data['status'] == 200 && $response_data['orderStatus']=='SUCCESS') {
                $result = Pay::changePayStatus([
                    'orderno' => $response_data['mchOrderNo'],//订单号
                    'money' => $response_data['amount'],//金额
                    'outorderno' => $response_data['platOrderNo'],//上游订单号
                    'fee'=>$response_data['fee'],//上游手续费
                    'msg' => '代付成功',
                    'status' => '1',//成功状态为1,0为失败
                ]);
                Log::write('代付异步成功通知:'.implode($result),'success');
               // Log::record('代付异步通知！','CHANNEL');
            }else{
                $result = Pay::changePayStatus([
                    'orderno' => $response_data['mchOrderNo'],
                    'money' => $response_data['amount'],
                    'outorderno' => $response_data['platOrderNo'],
                    'msg' =>$response_data['msg'] ,
                    'status' => '0',
                ]);
                Log::write('代付异步失败通知:'.implode($result),'error');
            }
            exit('SUCCESS');
        }
        exit('sign error');

    }
}