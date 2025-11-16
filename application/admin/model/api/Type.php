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
}
