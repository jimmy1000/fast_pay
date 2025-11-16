<?php

namespace app\common\model\api;

use think\Model;

class Channel extends Model
{
    protected $name = 'api_channel';

    public function account()
    {
        // 关联到后台模块下的 Account 模型
        return $this->belongsTo('\\app\\admin\\model\\api\\Account', 'api_account_id', 'id');
    }

    public function apitype()
    {
        // 关联到后台模块下的 Type 模型
        return $this->belongsTo('\\app\\admin\\model\\api\\Type', 'api_type_id', 'id');
    }

    protected function getTodaymoneyAttr($value)
    {
        $today = date('Y-m-d');
        if ($this->getData('today') != $today) {
            return 0;
        }
        return $value;
    }

    protected function setTodaymoneyAttr($value, $row)
    {
        $today = date('Y-m-d');
        if ($this->getData('today') != $today) {
            $this->save(['today' => $today]);
        }
        return $value;
    }

    /**
     * 获取某个账户的通道（按 api_type_id 键名索引返回）
     */
    public static function getChannelByAccount($account_id)
    {
        $list = self::all(function ($query) use ($account_id) {
            $query->where('api_account_id', $account_id);
        });

        if (count($list) > 0) {
            $list = collection($list)->toArray();
            $type_list = array_column($list, 'api_type_id');
            $list = array_combine($type_list, $list);
            return $list;
        }
        return [];
    }

    /**
     * 根据接口类型获取所有开启/全部接口账户
     */
    public static function getAccountByType($typeid, $open = true)
    {
        $list = self::all(function ($query) use ($typeid, $open) {
            $query->where('api_type_id', $typeid);
            if ($open) {
                $query->where('status', 1);
            }
        }, ['account' => function ($query) {
            $query->field('id,name');
        }]);

        if (count($list) > 0) {
            $list = collection($list)->toArray();
            $list = array_combine(array_column($list, 'api_account_id'), array_values($list));
            return $list;
        }
        return [];
    }
}

