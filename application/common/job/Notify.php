<?php

namespace app\common\job;

use app\admin\model\faqueue\Log as FaqueueLog;
use app\common\model\NotifyLog;
use app\common\model\Order;
use fast\Http;
use fast\Random;
use think\Db;
use think\Log;
use think\queue\Job;

class Notify
{
    /**
     * fire 方法是消息队列默认调用的方法
     * @param Job $job   当前的任务对象
     * @param array $data 发布任务时自定义的数据
     */
    public function fire(Job $job, $data)
    {
        $order_id = $data['order_id'];

        // 分布式锁：保证同一时刻同一订单只会被处理一次
        $redislock = redisLocker();
        $resource  = $redislock->lock('notify.' . $order_id, 3000); // 单位毫秒

        if (!$resource) {
            // 有并发任务在处理 → 删除本次任务
            return $job->delete();
        }

        $orderModel = Order::get($order_id);
        if (is_null($orderModel)) {
            // 订单不存在 → 删除任务
            return $job->delete();
        }

        // 如果已成功，或者超过最大通知次数（5次），则不再处理
        if ($orderModel->notify_status == '1' || $orderModel->notify_count >= 5) {
            return $job->delete();
        }

        Db::startTrans();

        try {
            // 写队列日志
            (new FaqueueLog())->log($job->getQueue(), $job->getName(), $data);

            // 组装通知数据
            $post_data = [
                'merId'      => $orderModel->merchant_id,   // 商户号
                'orderId'    => $orderModel->orderno,      // 商户订单号
                'sysOrderId' => $orderModel->sys_orderno,  // 系统订单号
                'productInfo'=> $orderModel->productInfo,  // 产品信息
                'haveMoney'  => $orderModel->have_money,   // 扣除手续费后金额
                'orderAmt'   => $orderModel->total_money,  // 订单金额
                'status'     => $orderModel->status,       // 状态：1 表示支付成功
                'utr'        => $orderModel->utr,          // utr/交易号
            ];

            if (!empty($orderModel->req_info['attch'])) {
                $post_data['attch'] = $orderModel->req_info['attch']; // 附加信息
            }

            $userModel        = $orderModel->user;
            $post_data['sign'] = makeApiSign(
                $post_data,
                $userModel->md5key,
                config('site.private_key')
            );

            $notifyUrl = $orderModel->req_info['notifyUrl'];

            // 控制台日志
            $msg = date("Y-m-d H:i:s") . ' 代收异步通知发送:订单号=>' .$orderModel['orderno'] . ', 通知地址：' . $notifyUrl;
            $msg .= ', 通知数据: ' . json_encode($post_data, JSON_UNESCAPED_UNICODE);
            echo $msg . PHP_EOL;
            Log::record($msg, 'NOTIFY');
            // 发起 HTTP POST 通知
            $result = Http::post($notifyUrl, $post_data);

            // 记录到通知日志表
            NotifyLog::log($orderModel->id, $notifyUrl, $post_data, $result);

            // 判断通知是否成功
            if (trim(strtolower($result)) === 'success') {
                $orderModel->notify_status = '1'; // 通知成功
                $orderModel->notify_count  = $orderModel->notify_count + 1;
                $orderModel->save();
                Db::commit();
                $job->delete(); // 删除任务
            } else {
                $orderModel->notify_status = '2'; // 通知失败
                $orderModel->notify_count  = $orderModel->notify_count + 1;
                $orderModel->save();
                Db::commit();
                $job->release(5);
            }

        } catch (\Exception $e) {
            Db::rollback();
            echo "[Error] " . $e->getMessage() . PHP_EOL;
            Log::error($e);

            // 异常时延迟 5 秒再重试（避免 notify_count 不一致）
            $job->release(5);

        } finally {
            // 解锁 Redis
            if ($resource) {
                $redislock->unlock([
                    'resource' => 'notify.' . $order_id,
                    'token'    => $resource['token']
                ]);
            }
        }
    }
}
