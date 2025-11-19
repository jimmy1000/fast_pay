<?php

namespace app\admin\model;

use think\Model;

/**
 * 用户接口通道配置
 */
class UserApichannel extends Model
{
    protected $name = 'user_apichannel';

    protected $autoWriteTimestamp = false;

    /**
     * 根据用户ID获取通道配置并以接口类型ID为键返回
     *
     * @param int $userId
     * @return array
     */
    public static function getListByUser($userId)
    {
        if (!$userId) {
            return [];
        }
        $list = self::where('user_id', $userId)->select();
        if (!$list) {
            return [];
        }
        $list = collection($list)->toArray();
        $result = [];
        foreach ($list as $item) {
            $result[$item['api_type_id']] = $item;
        }
        return $result;
    }
}

