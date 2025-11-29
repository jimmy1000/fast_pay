<?php

namespace app\admin\model\repay;

use think\Model;


class Uporder extends Model
{

    

    

    // 表名
    protected $name = 'repay_uporder';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'status_text',
        'paytime_text'
    ];
    

    
    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1'), '2' => __('Status 2'), '3' => __('Status 3')];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? '';
    }


    public function getPaytimeTextAttr($value, $data)
    {
        $value = $value ?: ($data['paytime'] ?? '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setPaytimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }
    /**
    * 关联接口账户
    */
    public function apiaccount()
    {
        return $this->belongsTo('app\admin\model\api\Account', 'api_account_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
    /**     
     * 关联代付订单
     */
    public function repayorder()
    {
        return $this->belongsTo('app\admin\model\repay\Order', 'pay_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

}
