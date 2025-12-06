<?php

namespace app\common\model;

use think\Db;
use think\Model;
use fast\Http;

/**
 * 会员模型
 * @method static mixed getByUsername($str) 通过用户名查询用户
 * @method static mixed getByNickname($str) 通过昵称查询用户
 * @method static mixed getByMobile($str) 通过手机查询用户
 * @method static mixed getByEmail($str) 通过邮箱查询用户
 */
class User extends Model
{

    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    // 追加属性
    protected $append = [
        'url',
    ];


    /**
     * 获取个人URL
     * @param string $value
     * @param array  $data
     * @return string
     */
    public function getUrlAttr($value, $data)
    {
        return "/u/" . $data['id'];
    }

    /**
     * 获取头像
     * @param string $value
     * @param array  $data
     * @return string
     */
    public function getAvatarAttr($value, $data)
    {
        if (!$value) {
            //如果不需要启用首字母头像，请使用
            //$value = '/assets/img/avatar.png';
            $value = letter_avatar($data['nickname']);
        }
        return $value;
    }

    /**
     * 获取会员的组别
     */
    public function getGroupAttr($value, $data)
    {
        return UserGroup::get($data['group_id']);
    }

    /**
     * 获取验证字段数组值
     * @param string $value
     * @param array  $data
     * @return  object
     */
    public function getVerificationAttr($value, $data)
    {
        $value = array_filter((array)json_decode($value, true));
        $value = array_merge(['email' => 0, 'mobile' => 0], $value);
        return (object)$value;
    }

    /**
     * 设置验证字段
     * @param mixed $value
     * @return string
     */
    public function setVerificationAttr($value)
    {
        $value = is_object($value) || is_array($value) ? json_encode($value) : $value;
        return $value;
    }

    /**
     * 变更会员余额
     * @param int    $money   余额
     * @param int    $user_id 会员ID
     * @param string $memo    备注
     */
    public static function money($money, $user_id, $memo,$orderno, $style = '1')
    {
        Db::startTrans();
        try {
            $user = self::lock(true)->find($user_id);
            if ($user && $money != 0) {
                $before = $user->money;
                //$after = $user->money + $money;
                $after = function_exists('bcadd') ? bcadd($user->money, $money, 2) : $user->money + $money;
                //更新会员信息
                $user->save(['money' => $after]);
                //写入日志
                MoneyLog::create(['user_id' => $user_id, 'money' => $money, 'before' => $before, 'after' => $after, 'memo' => $memo,'orderno' => $orderno,'style' => $style]);
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
        }
    }

    /**
     * 变更会员积分
     * @param int    $score   积分
     * @param int    $user_id 会员ID
     * @param string $memo    备注
     */
    public static function score($score, $user_id, $memo)
    {
        Db::startTrans();
        try {
            $user = self::lock(true)->find($user_id);
            if ($user && $score != 0) {
                $before = $user->score;
                $after = $user->score + $score;
                $level = self::nextlevel($after);
                //更新会员信息
                $user->save(['score' => $after, 'level' => $level]);
                //写入日志
                ScoreLog::create(['user_id' => $user_id, 'score' => $score, 'before' => $before, 'after' => $after, 'memo' => $memo]);
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
        }
    }

    /**
     * 根据积分获取等级
     * @param int $score 积分
     * @return int
     */
    public static function nextlevel($score = 0)
    {
        $lv = array(1 => 0, 2 => 30, 3 => 100, 4 => 500, 5 => 1000, 6 => 2000, 7 => 3000, 8 => 5000, 9 => 8000, 10 => 10000);
        $level = 1;
        foreach ($lv as $key => $value) {
            if ($score >= $value) {
                $level = $key;
            }
        }
        return $level;
    }
    /**
     * 代理收益
     * @param $value
     * @return mixed
     */
    public function getIfagentmoneyAttr($value)
    {
        return $value == '-1' ? config('site.ifagentmoney') : $value;
    }

    /**
     * 代理收益类型比例
     * @param $value
     * @return mixed
     */
    public function getAgentRatioAttr($value)
    {
        return $value == '-1' ? config('site.agent_ratio') : $value;
    }
    /**
     * 结算类型
     * @param $value
     */
    public function getBalancestyleAttr($value){
        return $value == '-1' ? config('site.balancestyle') : $value;
    }

    /**
     * 结算周期
     * @param $value
     * @return mixed
     */
    public function getBalancetimeAttr($value){
        return $value == '-1' ? config('site.balancetime') : $value;
    }

    /**
     * 当日提现比例
     * @param $value
     * @return mixed
     */
    public function getPaylvAttr($value){
        return $value == '-1' ? config('site.paylv') : $value;
    }

    /**
     * 提现费率类型
     */
    public function getPayrateTypeAttr($value){
        return $value == '-1' ? config('site.payrate_type') : $value;
    }


    /**
     * 提现费率
     * @param $value
     */
    public function getPayrateAttr($value){
        return $value < 0 ? config('site.payrate') : $value;
    }
    public function getPayratePercentAttr($value){
        return $value < 0 ? config('site.payrate_percent') : $value;
    }
    public function getPayrateEachAttr($value){
        return $value < 0 ? config('site.payrate_each') : $value;
    }
    /**
     * 获取用户的冻结金额
     */
    public function getFreezeMoney(){
        $money =  Order::getFrozenMoney($this->getAttr('merchant_id'),$this->settle());
        return $money;
    }
    /**
     * 结算信息
     */
    public function settle(){
        return $this->getAttr('balancestyle').'+'.$this->getAttr('balancetime');
    }
    /**
     * 代付手续费 新版
     */
    public function commission($money)
    {
        // 获取优先级：模型属性 > 全局配置
        $percent = $this->getAttr('payrate_percent');
        $each = $this->getAttr('payrate_each');

        $percent = $percent >= 1 ? $percent : config('site.payrate_percent');
        $each = $each >= 1 ? $each : config('site.each');

        // 手续费计算
        $percentFee = bcdiv(bcmul($money, $percent, 4), 100, 2);
        $totalFee = bcadd($percentFee, $each, 2);

        return $totalFee;
    }
    /**
     * 代付风控通知tg
     */
    public function RiskControl($money,$order_no)
    {
        $risk = $this->getAttr('risk_control');
        $risk_value = $risk == -1 ? config('site.risk_control') : $risk;

        if ($money >= $risk_value) {
            $payload = [
                'create_time' => time(),
                'money'      => $money,
                'merchantId' => $this->merchant_id,
                'risk_money' => $risk_value,
                'username'   => $this->username,
                'order_no'   => $order_no,
                'contacts'   => $this->contacts,
            ];

            $url = "http://127.0.0.1:9000/notify";
            $result = Http::send_json($url, $payload);
            
            // 1. HTTP 请求失败
            if ($result === false || (is_string($result) && strpos($result, 'Error code:') === 0)) {
                return [
                    'status'  => true,  // 风控触发了，只是通知失败
                    'message' => '通知请求失败: ' . ($result ?: '请求失败'),
                    'data'    => ['error' => $result]
                ];
            }

            // 2. HTTP 200，但 JSON 格式错误
            $json = json_decode($result, true);
            if (!$json) {
                return [
                    'status'  => true,
                    'message' => '通知返回异常，非 JSON 格式',
                    'data'    => ['response' => $result]
                ];
            }

            // 3. 正常解析 JSON
            if (isset($json['status']) && $json['status'] === 'success') {
                return [
                    'status'  => true,
                    'message' => '通知发送成功',
                    'data'    => $json
                ];
            } else {
                return [
                    'status'  => true,
                    'message' => '通知发送失败',
                    'data'    => $json
                ];
            }
        }

        // 不触发风控
        return [
            'status'  => false,
            'message' => '未触发风控'
        ];
    }
    /**
     * 清除谷歌令牌绑定
     * @param int $user_id
     * @return bool
     */
    public static function clearGoogleSecret($user_id)
    {
        $user = self::get($user_id);
        $user->googlesecret = '';
        $user->googlebind = 0;
        return $user->save();
    }

    /**
     * 获取代理的某个通道的费率
     * @param $user_id
     * @param $api_type_id
     * @return bool
     * @throws \think\exception\DbException
     */
    public static function getAgentRate($user_id, $api_type_id)
    {
        $userChannelModel = UserApichannel::get([
            'user_id' => $user_id,
            'api_type_id' => $api_type_id,
        ]);
        //代理必须要设置费率
        if (is_null($userChannelModel) || $userChannelModel['rate'] <= 0) {
            return false;
        }
        return $userChannelModel['rate'];
    }
    /**
     * 获取用户可用的接口以及费率
     * 以接口类型为key
     */
    public function getApiList2($money=0)
    {

        $user_id = $this->getAttr('id');
        //获取系统的所有开启的通道
        $api_type_list = ApiType::getOpenList();
        //获取用户的接口规则
        $api_user_channels = UserApichannel::getListByUser($user_id);
        $result = [];   //结果数组
        foreach ($api_type_list as $type) {
            $type_id = $type['id'];
            $rule_id = $type['api_rule_id'];
            $rate_flag = false;
            $user_rate = 0;

            //如果用户拥有默认的规则
            if (!empty($api_user_channels[$type_id])) {
                $rule_id = $api_user_channels[$type_id]['api_rule_id'] == 0 ? $rule_id : $api_user_channels[$type_id]['api_rule_id'];
                $rate_flag = $api_user_channels[$type_id]['rate'] > 0 ? true : false;
                //如果通道关闭了
                if ($api_user_channels[$type_id]['status'] == 0) {
                    continue;
                }
            }
            //没有设置规则的情况下
            if ($rule_id == 0) {
                continue;
            }

            //轮询规则自动失效限额的接口
            $channel_info = ApiRule::getChannelInfo($rule_id, false,true,$money);

            //没有设置的规则的时候不可用
            if(empty($channel_info)){
                continue;
            }


            //如果用户自定义了费率
            if ($rate_flag) {
                $user_rate = $api_user_channels[$type_id]['rate'];
            }
            $type_text = '';
            switch ($channel_info['info']['type']) {
                case '0':
                    $type_text = '单通道模式';
                    break;
                case '1':
                    $type_text = '顺序轮询';
                    break;
                case '2':
                    $type_text = '随机轮询';
                    break;
            }
            //封装结果
            $result[$type['code']] = [
                'id' => $type['id'],
                'name' => $type['name'],  //接口名称
                'code' => $type['code'],  //调用代码
                'domain' => $type['domain'],  //接口域名
                'account_id' => $channel_info['info']['api_account_ids']['id'],
                'account_weight' => $channel_info['info']['api_account_ids']['weight'],
                'rule_type' => $channel_info['info']['type'], //规则
                'rule_type_text' => $type_text,
                'rate' => $channel_info['rate_list'], //费率数组
                'user_rate' => $user_rate,
                'money_range' => implode(',', $channel_info['money_range_list']),   //充值范围
                'total' => $channel_info['total'],    //每天额度
                'has' => $channel_info['has']         //已用额度
            ];

        }
        return $result;

    }

}
