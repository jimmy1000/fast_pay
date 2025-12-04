<?php
namespace addons\mq\model;

use app\admin\model\mq\Channel;
use Carbon\Carbon;
use think\Model;

class MqAccount extends Model
{
    protected $name = 'mq_account';

    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = '';
    protected $updateTime = '';


    /**
     * 获取可以使用的类型
     * @param $type
     */
    public static function getList($type)
    {
        $channel = Channel::where('name', $type)->find();
        if (!$channel) return [];
        $accountList = self::where([
            'status' => 1,
            'channel_id' => $channel['id'],
        ])->select();

        return collection($accountList)->toArray();
    }


    /**
     * 随机获取一个账户
     */
    public static function getAccount($channel)
    {
        $accountList = self::getList($channel);
        if (empty($accountList)) return [];

        while (!empty($accountList)) {
            $key = array_rand($accountList);
            $account = $accountList[$key];

            $today = (float)$account['todaymoney'];
            $max   = (float)$account['maxmoney'];

            // 已用完额度的账户排除
            if ($max > 0 && $today >= $max) {
                unset($accountList[$key]);
                continue;
            }

            // 找到可用账户
            return $account;
        }

        // ⚠️ 所有账户额度已用完
        return [];
    }




    protected function getTodaymoneyAttr($value)
    {
        $today = Carbon::now()->toDateString();
        if ($this->getData('today') != $today) {
            return 0;
        }
        return $value;
    }

    protected function setTodaymoneyAttr($value, $row)
    {
        $today = Carbon::now()->toDateString();

        if ($this->getData('today') != $today) {
            $this->save([
                'today' => $today
            ]);
        }
        return $value;
    }
}