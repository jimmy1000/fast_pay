<?php

namespace app\common\model;

use fast\Date;
use think\exception\DbException;
use think\Model;

class Order extends Model
{
    // 表名
    protected $name = 'order';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

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
        'createtime_text',
    ];

    public static function createOrderNo()
    {
        $orderSn = create_orderno();
        if (!is_null(self::get(['sys_orderno' => $orderSn]))) {
            $orderSn = self::createOrderNo();
        }
        return $orderSn;
    }

    protected function getReqInfoAttr($value)
    {
        $req = [];
        parse_str($value, $req);
        return $req;
    }

    public function getPaytimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['paytime']) ? $data['paytime'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    public function getCreatetimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['createtime']) ? $data['createtime'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    public function getStyleTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['style']) ? $data['style'] : '');
        $list = $this->getStyleList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getNotifyStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['notify_status']) ? $data['notify_status'] : '');
        $list = $this->getNotifyStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getStyleList()
    {
        return ['0' => '普通订单', '1' => '充值订单'];
    }

    public function getStatusList()
    {
        return ['0' => '未支付', '1' => '已成功', '2' => '扣量订单'];
    }

    public function getNotifyStatusList()
    {
        return ['0' => '未通知', '1' => '通知成功', '2' => '通知失败'];
    }

    public function apiaccount()
    {
        return $this->belongsTo('ApiAccount', 'api_account_id', 'id', '', 'LEFT')->setEagerlyType(0);
    }

    public function user()
    {
        return $this->belongsTo('User', 'merchant_id', 'merchant_id', 'LEFT')->setEagerlyType(0);
    }

    public function apitype()
    {
        return $this->belongsTo('ApiType', 'api_type_id', 'id', '', 'LEFT')->setEagerlyType(0);
    }

    public function upstream()
    {
        return $this->belongsTo('ApiUpstream', 'api_upstream_id', 'id', '', 'LEFT')->setEagerlyType(0);
    }

    /**
     * 获取代理金额
     * @param array $data
     * @return array|false
     */
    public static function agentMoney($data)
    {
        $userModel = User::getByMerchantId($data['merchant_id']);
        if (is_null($userModel) || $userModel['group_id'] != '2' || $userModel['status'] == 'hidden' || $userModel['ifagentmoney'] != '1') {
            return false;
        }

        $agentRate = User::getAgentRate($userModel['id'], $data['jkid']);
        if (!$agentRate) {
            $agentRate = $data['jkfl'];
        }
        if ($agentRate <= 0) {
            $agentRate = $data['rate'];
        }

        $agentMoney = 0;
        $nowRate = $agentRate;
        if ($agentRate < $data['rate']) {
            $agentMoney = $data['money'] * ($data['rate'] - $agentRate) / 100;
            $agentMoney = number_format($agentMoney, 2);
            if ($agentMoney <= 0) {
                $agentMoney = 0;
            }
        } else {
            $nowRate = $data['rate'];
        }

        if (empty($data['level'])) {
            $data['level'] = 1;
        }

        $moneyBuffer[] = [
            'level'       => $data['level'],
            'money'       => $agentMoney,
            'merchant_id' => $data['merchant_id'],
            'user_id'     => $userModel['id'],
            'fl'          => $data['rate'],
            'userfl'      => $nowRate,
            'jkfl'        => $data['jkfl'],
            'leavemoney'  => $userModel['money'],
        ];

        if (10 > $data['level'] && $userModel['agent_id'] > 0) {
            $agentData = [
                'merchant_id' => $userModel['agent_id'],
                'rate'        => $nowRate,
                'jkfl'        => $data['jkfl'],
                'jkid'        => $data['jkid'],
                'money'       => $data['money'],
                'level'       => $data['level'] + 1,
            ];
            $tmp = self::agentMoney($agentData);
            if ($tmp) {
                foreach ($tmp as $item) {
                    $moneyBuffer[] = $item;
                }
            }
        }

        return $moneyBuffer;
    }

    public function channel()
    {
        return ApiChannel::get([
            'api_account_id' => $this->getAttr('api_account_id'),
            'api_type_id'    => $this->getAttr('api_type_id'),
        ]);
    }

    /**
     *  获取用户冻结金额
     * 计算商户的冻结金额
     *
     * 逻辑说明：
     * - 支持结算方式：T+N（工作日结算）与 D+N（自然日结算）
     * - T+N 自动根据周末进行顺延（周六 +1，周日 +2）
     * - T+0 / D+0 视为 T1，但需要按费率（paylv）扣除一定比例
     * - 若商户为代理商（group_id = 2），还需要计算代理订单金额
     *
     * @param int $merchant_id 商户ID
     * @param string $settle 结算方式，例如 "T+1"、"T+0"、"D+1"
     * @return float|bool          返回冻结金额，或 false 表示结算方式非法
     * @throws DbException
     */
    public static function getFrozenMoney($merchant_id, $settle)
    {
        // 获取商户信息
        $user = User::get(['merchant_id' => $merchant_id]);
        if (!$user) return 0;
        // 解析结算方式（T+N / D+N）
        if (preg_match('/^T\+(\d+)/', $settle, $m)) {
            $t = (int)$m[1];

            // T+N 需要处理周末顺延
            $week = date('w');
            if ($week == 6) $t += 1; // 周六顺延 1 天
            if ($week == 0) $t += 2; // 周日顺延 2 天

        } elseif (preg_match('/^D\+(\d+)/', $settle, $m)) {
            $t = (int)$m[1]; // D+N 自然日，无需顺延
        } else {
            return false; // 不合法的结算方式
        }

        // 处理 T+0 / D+0 的特殊规则
        $isT0 = ($t == 0);
        $paylv = 0;

        if ($isT0) {
            $t = 1; // T0 至少冻结当天交易

            // 商户费率处理
            if (!empty($user['paylv']) && $user['paylv'] > 0) {
                $paylv = min($user['paylv'], 100); // 费率最大不超过 100%
            }

            // 费率 = 100% 相当于商户全额得到，不需要冻结金额
            if ($paylv == 100) return 0;
        }

        // 计算冻结区间的开始时间
        // 例如 T+1 → 冻结昨日数据；T+2 → 冻结前两天数据
        $startTime = strtotime('today') - 86400 * ($t - 1);

        // 查询普通订单的冻结金额
        $money = self::where([
            'style'       => '0',
            'status'      => '1',
            'merchant_id' => $merchant_id,
            'paytime'     => ['>=', $startTime],
        ])->sum('have_money');
        // T0 情况下根据比例扣减冻结金额
        if ($isT0 && $money > 0 && $paylv > 0) {
            $money = $money * (100 - $paylv) / 100;
        }
        // 代理商模式下，需要计算代理订单金额
        $agentMoney = 0;
        if ($user['group_id'] == '2') {
            $agentMoney = OrderAgent::where([
                'merchant_id' => $merchant_id,
                'createtime'  => ['>=', $startTime],
            ])->sum('money');

            // T0 下代理金额同样要扣费率
            if ($isT0 && $agentMoney > 0 && $paylv > 0) {
                $agentMoney = $agentMoney * (100 - $paylv) / 100;
            }
        }

        // 返回合计冻结金额（普通订单 + 代理订单）
        return $money + $agentMoney;
    }

}

