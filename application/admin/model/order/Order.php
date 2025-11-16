<?php

namespace app\admin\model\order;

use think\Model;


class Order extends Model
{

    

    

    // 表名
    protected $name = 'order';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'style_text',
        'status_text',
        'notify_status_text',
        'paytime_text',
        'repair_text',
        'repair_time_text'
    ];
    

    
    public function getStyleList()
    {
        return ['0' => __('Style 0'), '1' => __('Style 1')];
    }

    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1'), '2' => __('Status 2')];
    }

    public function getNotifyStatusList()
    {
        return ['0' => __('Notify_status 0'), '1' => __('Notify_status 1'), '2' => __('Notify_status 2')];
    }

    public function getRepairList()
    {
        return ['0' => __('Repair 0'), '1' => __('Repair 1')];
    }


    public function getStyleTextAttr($value, $data)
    {
        $value = $value ?: ($data['style'] ?? '');
        $list = $this->getStyleList();
        return $list[$value] ?? '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? '';
    }


    public function getNotifyStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['notify_status'] ?? '');
        $list = $this->getNotifyStatusList();
        return $list[$value] ?? '';
    }


    public function getPaytimeTextAttr($value, $data)
    {
        $value = $value ?: ($data['paytime'] ?? '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getRepairTextAttr($value, $data)
    {
        $value = $value ?: ($data['repair'] ?? '');
        $list = $this->getRepairList();
        return $list[$value] ?? '';
    }


    public function getRepairTimeTextAttr($value, $data)
    {
        $value = $value ?: ($data['repair_time'] ?? '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setPaytimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setRepairTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    /**
     * 关联接口账号
     */
    public function account()
    {
        return $this->belongsTo('app\admin\model\api\Account', 'api_account_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    /**
     * 关联接口类型
     */
    public function apitype()
    {
        return $this->belongsTo('app\admin\model\api\Type', 'api_type_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    /**
     * 关联上游
     */
    public function upstream()
    {
        return $this->belongsTo('app\admin\model\api\Upstream', 'api_upstream_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

}
