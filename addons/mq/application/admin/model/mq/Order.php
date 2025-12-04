<?php

namespace app\admin\model\mq;

use think\Model;


class Order extends Model
{

    

    //数据库
    protected $connection = 'database';
    // 表名
    protected $name = 'mq_order';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

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
        return ['0' => __('Status 0'), '1' => __('Status 1'), '2' => __('Status 2')];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function account(){

        return $this->belongsTo('Account','mq_account_id','id','','LEFT')->setEagerlyType(0);
    }




}
