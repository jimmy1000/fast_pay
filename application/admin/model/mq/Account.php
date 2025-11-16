<?php

namespace app\admin\model\mq;

use think\Model;


class Account extends Model
{

    

    

    // 表名
    protected $name = 'mq_account';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'status_text'
    ];
    

    
    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1')];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? '';
    }
    public function category()
    {
        return $this->belongsTo('Category', 'category_id', 'id');
    }
    public function channel()
    {
        return $this->belongsTo('Channel', 'channel_id', 'id');
    }
    public function bank()
    {
        return $this->belongsTo('Bank', 'bank_id', 'id');
    }



}
