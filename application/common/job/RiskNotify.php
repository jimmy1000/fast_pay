<?php
/**
 * RepayNotify.php
 * 易聚合支付系统
 * =========================================================
 * 请尊重开发人员劳动成果，严禁使用本系统转卖、销售或二次开发后转卖、销售等商业行为。
 * 本源码仅供技术学习研究使用,请勿用于非法用途,如产生法律纠纷与作者无关。
 * =========================================================
 * @author : 666666@qq.com
 * @date : 2019-05-13
 */
namespace app\common\job;

use addons\faqueue\model\FaqueueLog;
use app\common\model\NotifyLog;
use app\common\model\Pay;
use app\common\model\PayOrder;
use app\common\model\User;
use fast\Http;
use fast\Random;
use fast\Rsa;
use think\Db;
use think\Log;
use think\queue\Job;

class RiskNotify{


    /**
     * fire方法是消息队列默认调用的方法
     * @param Job $job 当前的任务对象
     * @param array|mixed $data 发布任务时自定义的数据
     */

    public function fire(Job $job, $data)
    {

        $pay_id = $data['pay_id'];

        $payModel = Pay::get($pay_id);
        if (is_null($payModel)) {
            return $job->delete();
        }

        //普通订单和没有异步地址的订单不通知
        if($payModel['style'] == '0' || empty($payModel['req_info']['notifyUrl'])){
            return $job->delete();
        }

        //通知成功或者通知过5次了
        if ($payModel->notify_status == '1' || $payModel->notify_count >= 5) {

            return $job->delete();
        }

        Db::startTrans();

        try {
            (new FaqueueLog())->log($job->getQueue(), $job->getName(), $data);

            $orderno = $payModel->orderno;

            //发送通知
            $post_data = [
                'merId' => $payModel->merchant_id,          //商户号
                'orderOn' => $orderno,                       //商户订单号
                'sysOrder'=>'Rejected'.time(),       //系统订单
                'money'=>$payModel['money'],                 //金额
                'utr' =>$payModel->utr,  //utr
                'status' => $payModel->status,              //通知订单状态:0=申请中,1=已支付,2=冻结,3=取消,4=失败
                'msg' =>'Risk Control Rejection!',//信息 避免冲突
                'charge'=>$payModel['charge'] //手续费

               // 'nonceStr' => Random::alnum('32')        //随机字符串

            ];

            if (!empty($payModel->req_info['attch'])) {
                $post_data['attch'] = $payModel->req_info['attch'];       //附加信息
            }

            $userModel = User::get(['merchant_id'=>$payModel['merchant_id']]);

            $post_data['sign'] = makeApiSign($post_data, $userModel->md5key, config('site.private_key'));

            $notifyUrl = $payModel->req_info['notifyUrl'];

            // 控制台显示消息
            $msg = date("Y-m-d H:i:s").'代付风控驳回:订单号-》》'.$payModel['orderno'].',通知地址：'.$notifyUrl;
            $msg .= ', 通知数据: ' . json_encode($post_data, JSON_UNESCAPED_UNICODE);
            echo $msg;
            Log::record($msg,'RISK_NOTIFY');
            $result = Http::post($notifyUrl, $post_data);

            // 写入通知日志表
            NotifyLog::log($payModel->id,$notifyUrl,$post_data,$result);
            if ($result == 'success') {
                //更改订单状态
                $payModel->notify_status = '1'; //1为成功
                $payModel->notify_count = $payModel->notify_count + 1;
                $payModel->save();
                Db::commit();
                $job->delete();
            }else{
                //更改订单状态
                $payModel->notify_status = '2';//2为失败
                $payModel->notify_count = $payModel->notify_count + 1;
                $payModel->save();
                Db::commit();
                //延迟五秒执行
                $job->release(5);
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
            Db::rollback();
            $job->release(5);
        }
    }



}