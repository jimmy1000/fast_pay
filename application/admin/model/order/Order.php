<?php

namespace app\admin\model\order;

use app\common\model\OrderAgent;
use app\common\model\User;
use think\Db;
use think\Model;
use think\Queue;
use think\Session;


class Order extends Model
{

    

    

    // 表名
    protected $name = 'order';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'style_text',
        'status_text',
        'notify_status_text',
        'paytime_text',
        'repair_text',
        'repair_time_text'
    ];
    

    
    public function getStyleList()
    {
        return ['0' => __('Style 0'), '1' => __('Style 1')];
    }

    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1'), '2' => __('Status 2')];
    }

    public function getNotifyStatusList()
    {
        return ['0' => __('Notify_status 0'), '1' => __('Notify_status 1'), '2' => __('Notify_status 2')];
    }

    public function getRepairList()
    {
        return ['0' => __('Repair 0'), '1' => __('Repair 1')];
    }


    public function getStyleTextAttr($value, $data)
    {
        $value = $value ?: ($data['style'] ?? '');
        $list = $this->getStyleList();
        return $list[$value] ?? '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? '';
    }


    public function getNotifyStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['notify_status'] ?? '');
        $list = $this->getNotifyStatusList();
        return $list[$value] ?? '';
    }


    public function getPaytimeTextAttr($value, $data)
    {
        $value = $value ?: ($data['paytime'] ?? '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getRepairTextAttr($value, $data)
    {
        $value = $value ?: ($data['repair'] ?? '');
        $list = $this->getRepairList();
        return $list[$value] ?? '';
    }


    public function getRepairTimeTextAttr($value, $data)
    {
        $value = $value ?: ($data['repair_time'] ?? '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setPaytimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setRepairTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    /**
     * 获取 req_info 属性（访问器）
     * 将字符串格式的 req_info 解析为数组，方便模板中直接使用 {$order.req_info.notifyUrl}
     */
    protected function getReqInfoAttr($value)
    {
        if (empty($value)) {
            return [];
        }
        // 如果已经是数组，直接返回
        if (is_array($value)) {
            return $value;
        }
        // 如果是字符串，解析为数组
        $req = [];
        parse_str($value, $req);
        return $req;
    }

    /**
     * 关联商户用户
     * 通过 merchant_id 关联到 User 模型
     */
    public function user()
    {
        return $this->belongsTo('app\common\model\User', 'merchant_id', 'merchant_id', [], 'LEFT')->setEagerlyType(0);
    }

    /**
     * 关联接口账号
     */
    public function account()
    {
        return $this->belongsTo('app\admin\model\api\Account', 'api_account_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    /**
     * 关联接口类型
     */
    public function apitype()
    {
        return $this->belongsTo('app\admin\model\api\Type', 'api_type_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    /**
     * 关联上游
     */
    public function upstream()
    {
        return $this->belongsTo('app\admin\model\api\Upstream', 'api_upstream_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    /**
     * 手动退单
     * @param int $order_id 订单ID
     * @throws \Exception
     */
    public static function chargeback($order_id)
    {
        $orderModel = self::get($order_id);
        $userModel = $orderModel->user;
        if (is_null($orderModel) || is_null($userModel)) {
            throw new \Exception('订单或商户信息不存在');
        }

        // 检查商户余额
        \think\Db::startTrans();
        try {
            if ($userModel['money'] < $orderModel['have_money']) {
                throw new \Exception('商户余额不足，无法撤单。');
            }

            // 判断代理
            $agentList = \app\common\model\OrderAgent::all([
                'order_id' => $orderModel->id
            ]);

            // 先检查一下是否有余额不足的
            if (count($agentList) > 0) {
                $agentList = collection($agentList)->toArray();
                foreach ($agentList as $k => $agent) {
                    if ($agent['money'] <= 0) {
                        continue;
                    }
                    $agentModel = \app\common\model\User::get([
                        'merchant_id' => $agent['merchant_id']
                    ]);
                    $agentList[$k]['user_id'] = $agentModel['id'];
                    if (!is_null($agentModel) && $agentModel['money'] < $agent['money']) {
                        throw new \Exception('代理商户【' . $agentModel['merchant_id'] . '】余额不足，无法撤单。');
                    }
                }
            }

            // 减掉用户余额
            \app\common\model\User::money(-$orderModel['have_money'], $userModel['id'], '订单撤销资金撤回：撤销金额' . $orderModel['have_money'] . '越南盾', $orderModel['orderno']);

            // 减掉代理的钱
            if (count($agentList) > 0) {
                foreach ($agentList as $agent) {
                    if ($agent['money'] <= 0) {
                        continue;
                    }
                    \app\common\model\User::money(-$agent['money'], $agent['user_id'], '订单撤销资金撤回：撤销金额' . $agent['money'] . '越南盾', $orderModel['orderno']);
                    \app\common\model\OrderAgent::destroy($agent['id']);
                }
            }

            $orderModel->status = '0';
            $orderModel->save();

            \think\Db::commit();
        } catch (\Exception $e) {
            \think\Db::rollback();
            throw $e;
        }
    }

    /**
     * 更新订单（更新订单）
     * @param array $params 参数数组，包含 orderno（系统订单号）、up_orderno（上游单号）、amount（金额）
     * @return array [状态码, 消息] 状态码：1=成功，0=失败
     * @throws \Exception
     */
    public static function orderFinish($params)
    {

        $orderno = $params['orderno'];
        $up_orderno = $params['up_orderno'];
        $amount = $params['amount'];

        $orderModel = \app\common\model\Order::get([
            'sys_orderno' => $orderno
        ]);

        if (is_null($orderModel)) {
            return [0, '订单不存在'];
        }

        //已经支付
        if ($orderModel->status != 0) {
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
        if ($userModel['agent_id'] != '0') {
            $agentData = array(
                'merchant_id' => $userModel['agent_id'],                  //代理ID
                'rate' => $orderModel['rate'],                      //订单费率
                'jkfl' => $orderModel['channel_rate'],              //通道默认费率
                'syfl' => $orderModel['upstream_rate'],             //上游费率
                'jkid' => $orderModel['api_type_id'],                        //接口类型
                'money' => $orderModel['total_money'],                 //订单金额
                'level' => 1,
            );
            $agentMoneyArray = \app\admin\model\order\Order::agentMoney($agentData);
            if (is_array($agentMoneyArray)) {
                //计算代理总费用
                foreach ($agentMoneyArray as $iDlMoneyArr) {
                    $agentMoneyAll += $iDlMoneyArr['money'];
                }
            }
        }

        //开启事务
        Db::startTrans();

        try {
            $admin = Session::get('admin'); //获取管理员
            $orderModel->status = '1';
            $orderModel->paytime = time();
            $orderModel->up_orderno = $params['up_orderno'];
            $orderModel->agent_money = $agentMoneyAll;

            $orderModel->repair = '1';  //补单
            $orderModel->repair_admin_id = $admin['id'];        //管理员id
            $orderModel->repair_time = time();                  //补单时间

            $orderModel->save();

            //给用户加上余额
            $money = $orderModel['have_money'];
            if($orderModel->style == '1'){
                User::money($money,$userModel->id,'充值：' . $orderModel['total_money'] . '越南盾，扣除手续费到账：' . $money . '越南盾',$orderModel->orderno);
            }else{
                User::money($money,$userModel->id,'资金流水记录：订单金额' . $orderModel['total_money'] . '越南盾，到账金额' . $money . '越南盾',$orderModel->orderno);
            }
            //代理金额增加
            if (!empty($agentMoneyArray)) {

                foreach ($agentMoneyArray as $agentMoney) {

                    if ($agentMoney['money'] <= 0) {
                        continue;
                    }
                    OrderAgent::create([
                        'order_id' => $orderModel->id,
                        'level' => $agentMoney['level'],
                        'merchant_id' => $agentMoney['merchant_id'],
                        'money' => $agentMoney['money'],
                        'rate' => $agentMoney['userfl']
                    ]);
                    User::money($agentMoney['money'], $agentMoney['user_id'], '代理资金流水记录：订单金额' . $orderModel['total_money'] . '越南盾，到账金额' . $agentMoney['money'] . '越南盾', $orderModel->orderno,'3');
                }
            }
            //判断是否限额
            $channelModel = $orderModel->channel();

            if ($channelModel['daymoney'] > 0) {
                //改变订单每日额度
                $channelModel->todaymoney = $channelModel->todaymoney + $orderModel['total_money'];
                $channelModel->save();
            }

            Db::commit();

        } catch (\Exception $e) {
            Db::rollback();
            return [0, $e->getMessage()];
        }


        if($orderModel->style!='1'){
            //加入通知队列 发送异步通知
            Queue::push('app\common\job\Notify',['order_id'=>$orderModel->id]);
            
        }
        return [1, 'success'];

    }
    /**
     * 获取代理金额
     * @param $data
     */
    public static function agentMoney($data)
    {
        $moneyBuffer = [];

        // 获取代理用户信息
        $user = User::getByMerchantId($data['merchant_id']);
        if (!$user || $user['group_id'] != '2' || $user['status'] === 'hidden' || $user['ifagentmoney'] != '1') {
            return false;
        }

        // 当前代理设置的费率（如果没有设置，则使用通道默认费率）

        $agentRate = User::getAgentRate($user['id'], $data['jkid']) ?: $data['jkfl'];
        if ($agentRate <= 0) {
            $agentRate = $data['rate']; // 代理没有设置费率则防御性回退
        }

        // 获取当前代理的分润比例
        $agentRatio = floatval($user['agent_ratio']);
        // 当前使用的费率（默认是代理的）
        $nowRate = $agentRate;
        $agentMoney = 0;

        // 有利润空间才计算佣金
        if ($agentRate < $data['rate']) {
            $orderRate = $data['rate'];
            $orderMoney = $data['money'];
            $upstreamRate = $data['syfl'];

            if ($agentRatio > 0 && $agentRatio <= 1) {
                //按总利润 × 比例
                $profitRate = $orderRate - $upstreamRate;
                $agentMoney = $orderMoney * $profitRate * $agentRatio / 100;
            } else {
                // 按代理差价
                $agentMoney = $orderMoney * ($orderRate - $agentRate) / 100;
            }

            $agentMoney = round($agentMoney, 2); // 精度处理
            if ($agentMoney < 0.01) {
                $agentMoney = 0;
            }
        } else {
            // 如果无利润空间，用 rate 作为当前费率
            $nowRate = $data['rate'];
        }

        // 层级默认值
        $level = $data['level'] ?? 1;

        // 当前代理分润信息
        $moneyBuffer[] = [
            'level' => $level,
            'money' => $agentMoney,
            'merchant_id' => $data['merchant_id'],
            'user_id' => $user['id'],
            'fl' => $data['rate'],              // 商户实际费率
            'userfl' => $nowRate,               // 当前代理费率
            'jkfl' => $data['jkfl'],            // 通道默认费率
            'syfl' => $data['syfl'],            // 上游费率
            'agent_ratio' => $agentRatio,       // 分润比例
            'leavemoney' => $user['money'],
        ];

        // 递归处理上级代理（最多3层）
        if ($level < 3 && $user['agent_id'] > 0) {
            $nextData = [
                'merchant_id' => $user['agent_id'],
                'rate' => $nowRate,
                'jkfl' => $data['jkfl'],
                'syfl' => $data['syfl'],
                'jkid' => $data['jkid'],
                'money' => $data['money'],
                'level' => $level + 1,
            ];
            $nextBuffer = self::agentMoney($nextData);
            if ($nextBuffer) {
                $moneyBuffer = array_merge($moneyBuffer, $nextBuffer);
            }
        }
        return $moneyBuffer;
    }

}
