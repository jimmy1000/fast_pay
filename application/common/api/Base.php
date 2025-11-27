<?php
/**
 * Base.php
 * 易聚合支付系统
 * =========================================================
 * 请尊重开发人员劳动成果，严禁使用本系统转卖、销售或二次开发后转卖、销售等商业行为。
 * 本源码仅供技术学习研究使用,请勿用于非法用途,如产生法律纠纷与作者无关。
 * =========================================================
 * @author : 666666@qq.com
 * @date : 2019-04-29
 */

namespace app\common\api;

use app\common\model\ApiChannel;
use app\common\model\Order;
use app\common\model\OrderAgent;
use app\common\model\PayOrder;
use app\common\model\User;
use fast\Random;
use fast\Rsa;
use think\Cache;
use think\Db;
use think\Queue;

abstract class  Base
{


    protected function getPayConfig($orderno){

        $payOrder = PayOrder::get([
            'orderno'=>$orderno
        ]);
        if (is_null($payOrder)) {
            return false;
        }
        $account = $payOrder->account;
        if (is_null($account)) {
            return false;
        }

        return $account->params;
    }


    /**
     * 获取订单所属账号的配置信息
     * @param $orderno 系统订单号
     */
    protected function getOrderConfig($orderno)
    {

        $order = Order::get([
            'sys_orderno' => $orderno
        ]);
        if (is_null($order)) {
            return false;
        }

        $account = $order->apiaccount;
        if (is_null($account)) {
            return false;
        }
        return $account->params;

    }


    /**
     * 更新订单状态
     * @param $params
     */
    protected function orderFinish($params)
    {
        $orderno = $params['orderno'];
        $up_orderno = $params['up_orderno'];
        $amount = $params['amount'];
        $utr = $params['utr'];
        $orderModel = Order::get([
            'sys_orderno' => $orderno
        ]);
        if (is_null($orderModel)) {
            return [0, '订单不存在'];
        }
        //已经支付
        if ($orderModel->status == 1) {
            return [0, '该订单支付成功'];
        }
        $money1 = number_format($orderModel['total_money'], 2, "", ".");
        $money2 = number_format($amount, 2, "", ".");

        if ($money1 != $money2) {
            return [0, '订单金额不一致'];
        }

        // 获取用户
        $userModel = $orderModel->user;
        if (is_null($userModel)) {
            return [0, '用户不存在'];
        }



        /**
         *
         * 计算代理费用
         */

        $agentMoneyArray = 0; //代理金额明细数组
        $agentMoneyAll = 0;  //代理金额汇总数额
        if($userModel['agent_id']!='0'){
            $agentData = array(
                'merchant_id' => $userModel['agent_id'],                  //代理ID
                'rate' => $orderModel['rate'],                      //订单费率
                'jkfl' => $orderModel['channel_rate'],              //通道默认费率
                'jkid' => $orderModel['api_type_id'],                        //接口类型
                'money' => $orderModel['total_money'],                 //订单金额
                'level' => 1,
            );
            $agentMoneyArray = Order::agentMoney($agentData);

            if(is_array($agentMoneyArray)){
                //计算代理总费用
                foreach ($agentMoneyArray as $iDlMoneyArr) {
                    $agentMoneyAll+=$iDlMoneyArr['money'];
                }
            }
        }

        //开启事务
        Db::startTrans();

        try{

            $orderModel->status = '1';
            $orderModel->paytime = time();
            $orderModel->up_orderno = $up_orderno;
            $orderModel->agent_money = $agentMoneyAll;
            $orderModel->utr=$utr;
            $orderModel->save();
            //给用户加上余额
            $money = $orderModel['have_money'];
            if($orderModel->style == '1'){
                User::money($money,$userModel->id,'充值：' . $orderModel['total_money'] . '比索，扣除手续费到账：' . $money . '比索',$orderModel->orderno);
            }else{
                User::money($money,$userModel->id,'资金流水记录：订单金额' . $orderModel['total_money'] . '比索，到账金额' . $money . '比索',$orderModel->orderno);
            }

            //代理金额增加
            if(!empty($agentMoneyArray)){

                foreach ($agentMoneyArray as $agentMoney){

                    if($agentMoney['money'] <= 0){
                        continue;
                    }
                    OrderAgent::create([
                        'order_id'=>$orderModel->id,
                        'level'=>$agentMoney['level'],
                        'merchant_id'=>$agentMoney['merchant_id'],
                        'money'=>$agentMoney['money'],
                        'rate'=>$agentMoney['userfl']
                    ]);

                    User::money($agentMoney['money'],$agentMoney['user_id'],'代理资金流水记录：订单金额' . $orderModel['total_money'] . '比索，到账金额' . $agentMoney['money'] . '比索',$orderModel->orderno,'3');

                }
            }

            //判断是否限额
            $channelModel = $orderModel->channel();
            if ($channelModel['daymoney'] > 0 ) {
                //改变订单每日额度
                $channelModel->todaymoney = $channelModel->todaymoney + $orderModel['total_money'];
                $channelModel->save();
            }


            Db::commit();

        }catch (\Exception $e){
            Db::rollback();
            return [0,$e->getMessage()];
        }
        if($orderModel->style!='1'){
            //加入通知队列 发送异步通知
            try {
                $result =  Queue::push('app\common\job\Notify',['order_id'=>$orderModel->id]);
                if (is_array($result)) {
                    file_put_contents('mq_result.txt', "\n".implode(', ', $result), FILE_APPEND);
                } else {
                    file_put_contents('mq_result.txt', "\n".$result, FILE_APPEND);
                }
            } catch (Exception $e){
                echo "Caught exception: " . $e->getMessage();
            }


        }

        return [1,'success'];

    }

    /**
     * 同步返回
     * @param $orderno
     * @return array
     * @throws \think\exception\DbException
     */
    protected function backurl($orderno=''){


        if(empty($orderno)){
            $orderno = request()->param('orderno','');
        }


        $orderModel = Order::get([
            'sys_orderno'=>$orderno
        ]);

        if(is_null($orderModel)){
            return [0,'订单不存在'];
        }
        $userModel = $orderModel->user;

        $fj = $orderModel->req_info;

        $orderno = substr($orderModel->orderno, strlen($orderModel->merchant_id));

        $data  = [
            'merId' => $orderModel->merchant_id,          //商户号
            'orderId' => $orderno,            //商户订单号
            'sysOrderId' => $orderModel->sys_orderno,     //系统订单号
            'productInfo' => $orderModel->req_info['productInfo']??'',      //描述
            'orderAmt' => $orderModel->total_money,       //订单金额
            'status' => $orderModel->status,              //通知状态 1为支付成功
            'nonceStr' => Random::alnum('32')        //随机字符串
        ];

        if (!empty($fj['attch'])) {
            $data['attch'] = $fj['attch'];       //附加信息
        }

        $privateKey = config('site.private_key');
        $md5Key =$userModel->md5key;


        $data['sign'] = makeApiSign($data,$md5Key,$privateKey);


        $url = $fj['returnUrl'];

        $url = $url.'?'.http_build_query($data);

        return [1,$url];

    }

    protected function qrcode($url,$type=''){

        $url = urlencode($url);
        return config('site.url').'/qrcode/build?text='.$url.'&logo='.$type;

    }



    /**
     * 构建表单
     * @param $url
     * @param $data
     * @return string
     */
    protected function createSubmitForm($url, $data)
    {
        $html = "<form action='" . $url . "' method='post' id='pay_form'>";
        foreach ($data as $field => $value) {
            $html .= "<input type='hidden' name='{$field}' id='{$field}' value='{$value}'/>";
        }
        $html .= "</form>";
        $html .= "<script type='text/javascript'>document.all.pay_form.submit();</script>";

        return $html;
    }


    /**
     *
     * @param $orderno
     * @param $html
     */
    protected function formUrl($orderno,$html){

        Cache::set('content.'.$orderno,$html,3600);

        $gateway = str_replace("https://","http://",config('site.gateway'));

        return $gateway.'/Pay/html/orderno/'.$orderno;
    }



    /**
     *
     * @param $orderno
     * @param $html
     */
    protected function redirectUrl($orderno,$url){

        Cache::set('url.'.$orderno,$url,18000);

        $gateway = str_replace("https://","http://",config('site.gateway'));
        return $gateway.'/Pay/url/orderno/'.$orderno;
    }



}