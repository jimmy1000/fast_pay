<?php

namespace app\common\job;

use app\admin\model\faqueue\Log as FaqueueLog;
use app\admin\model\repay\Notifylog;
use app\admin\model\repay\Order as RepayOrder;
use app\common\model\User;
use fast\Http;
use think\Db;
use think\Log;
use think\queue\Job;

class RepayNotify
{
    /**
     * fire 方法是消息队列默认调用的方法
     * @param Job $job   当前的任务对象
     * @param array $data 发布任务时自定义的数据
     */
    public function fire(Job $job, $data)
    {
        $order_id = $data['pay_id'];

        // 分布式锁：保证同一时刻同一订单只会被处理一次
        $redislock = redisLocker();
        $resource  = $redislock->lock('repay.notify.' . $order_id, 3000); // 单位毫秒

        if (!$resource) {
            // 有并发任务在处理 → 删除本次任务
            return $job->delete();
        }

        $repayModel = RepayOrder::get($order_id);
        if (is_null($repayModel)) {
            // 订单不存在 → 删除任务
            $redislock->unlock(['resource' => 'repay.notify.' . $order_id, 'token' => $resource['token']]);
            return $job->delete();
        }

        // 后台批量订单和没有异步地址的订单不通知
        if ($repayModel['style'] == '0' || empty($repayModel->req_info['notifyUrl'])) {
            $redislock->unlock(['resource' => 'repay.notify.' . $order_id, 'token' => $resource['token']]);
            return $job->delete();
        }

        // 通知成功或者通知过5次了
        if ($repayModel->notify_status == '1' || $repayModel->notify_count >= 5) {
            $redislock->unlock(['resource' => 'repay.notify.' . $order_id, 'token' => $resource['token']]);
            return $job->delete();
        }

        Db::startTrans();

        try {
            // 写队列日志
            FaqueueLog::log($job->getQueue(), $job->getName(), $data);

            $orderno = $repayModel->orderno;

            // 发送通知
            $post_data = [
                'merId'    => $repayModel->merchant_id,          // 商户号
                'orderOn'  => $orderno,                        // 商户订单号
                'sysOrder' => $orderno,                        // 系统订单号（代付订单没有单独的sysOrder，使用orderno）
                'money'    => $repayModel['money'],              // 金额
                'utr'      => $repayModel->utr,                  // utr
                'status'   => $repayModel->status,               // 通知订单状态:0=申请中,1=已支付,2=冻结,3=取消,4=失败
                'msg'      => ($repayModel->msg === '商户发起代付请求') ? '代付成功' : $repayModel->msg, // 信息 避免冲突
                'charge'   => $repayModel['charge']             // 手续费
            ];

            if (!empty($repayModel->req_info['attch'])) {
                $post_data['attch'] = $repayModel->req_info['attch']; // 附加信息
            }

            $userModel = User::get(['merchant_id' => $repayModel['merchant_id']]);
            if (!$userModel) {
                throw new \Exception('商户不存在');
            }

            $post_data['sign'] = makeApiSign($post_data, $userModel->md5key, config('site.private_key'));

            $notifyUrl = $repayModel->req_info['notifyUrl'];

            // 控制台显示消息
            $msg = date("Y-m-d H:i:s") . ' 代付异步通知发送:订单号-》》' . $repayModel['orderno'] . ',通知地址：' . $notifyUrl;
            $msg .= ', 通知数据: ' . json_encode($post_data, JSON_UNESCAPED_UNICODE);
            echo $msg . PHP_EOL;
            Log::record($msg, 'REPAY_NOTIFY');

            $result = Http::post($notifyUrl, $post_data);

            // 写入通知日志表
            Notifylog::log($repayModel->id, $notifyUrl, $post_data, $result);

            if (trim(strtolower($result)) === 'success') {
                // 更改订单状态
                $repayModel->notify_status = '1'; // 1为成功
                $repayModel->notify_count  = $repayModel->notify_count + 1;
                $repayModel->save();
                Db::commit();
                $redislock->unlock(['resource' => 'repay.notify.' . $order_id, 'token' => $resource['token']]);
                $job->delete();
            } else {
                // 更改订单状态
                $repayModel->notify_status = '2'; // 2为失败
                $repayModel->notify_count  = $repayModel->notify_count + 1;
                $repayModel->save();
                Db::commit();
                $redislock->unlock(['resource' => 'repay.notify.' . $order_id, 'token' => $resource['token']]);
                // 延迟五秒执行
                $job->release(5);
            }
        } catch (\Exception $e) {
            echo "[Error] " . $e->getMessage() . PHP_EOL;
            Log::error($e);
            Db::rollback();
            $redislock->unlock(['resource' => 'repay.notify.' . $order_id, 'token' => $resource['token']]);
            // 延迟五秒执行
            $job->release(5);
        }
    }
}

