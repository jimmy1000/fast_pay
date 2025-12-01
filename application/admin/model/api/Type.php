<?php

namespace app\admin\model\api;

use think\Model;


class Type extends Model
{
    // 表名
    protected $name = 'api_type';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'status_text',
        'default_text'
    ];
    
    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1')];
    }

    public function getDefaultList()
    {
        return ['0' => __('Default 0'), '1' => __('Default 1')];
    }

    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? '';
    }

    public function getDefaultTextAttr($value, $data)
    {
        $value = $value ?: ($data['default'] ?? '');
        $list = $this->getDefaultList();
        return $list[$value] ?? '';
    }

    public function rule()
    {
        return $this->belongsTo('Rule', 'api_rule_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    /**
     * 获取开启的接口类型及可选规则列表
     * 返回以支付类型ID为键的数组
     *
     * @return array [typeId => ['id' => typeId, 'name' => '名称', 'rule_list' => [...], ...]]
     */
    public static function getOpenListAndRule()
    {
        // 获取所有开启的支付类型
        $types = self::where('status', 1)
            ->order('weight', 'desc')
            ->field('id,name,api_rule_id,default')
            ->select();

        // 获取所有规则并按支付类型ID分组
        $allRules = \app\admin\model\api\Rule::field('id,name,api_type_id')->select();
        $rulesByType = [];
        foreach ($allRules as $rule) {
            $rulesByType[$rule->api_type_id][] = [
                'id' => $rule->id,
                'name' => $rule->name
            ];
        }

        // 以支付类型ID为键重新组织数组
        $result = [];
        if ($types) {
            foreach ($types as $type) {
                // 转换为数组格式
                $typeData = $type->toArray();
                $typeId = $typeData['id'];
                
                $result[$typeId] = [
                    'id' => $typeId,
                    'name' => $typeData['name'],
                    'api_rule_id' => $typeData['api_rule_id'] ?? 0,
                    'default' => isset($typeData['default']) ? $typeData['default'] : '1',
                    'rule_list' => $rulesByType[$typeId] ?? []
                ];
            }
        }

        return $result;
    }
}
