<?php

namespace app\admin\model\user;

use think\Model;
use app\admin\model\User;
use app\admin\model\api\Type as ApiType;
use app\admin\model\api\Rule as ApiRule;

/**
 * 用户接口通道配置
 */
class Apichannel extends Model
{
    protected $name = 'user_apichannel';

    protected $autoWriteTimestamp = false;

    // 追加属性
    protected $append = [
        'status_text'
    ];

    /**
     * 状态列表
     */
    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1')];
    }

    /**
     * 获取状态文本
     */
    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? '';
    }

    /**
     * 关联用户模型
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    /**
     * 关联支付类型模型
     */
    public function apitype()
    {
        return $this->belongsTo(ApiType::class, 'api_type_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    /**
     * 关联接口规则模型
     */
    public function apirule()
    {
        return $this->belongsTo(ApiRule::class, 'api_rule_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

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

