<?php

namespace app\admin\model\user;

use think\Model;
use app\admin\model\User;

class Bankcard extends Model
{

    

    

    // 表名
    protected $name = 'bankcard';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'bankcardtype_text',
        'caraddresstype_text',
        'status_text',
        'checktime_text'
    ];
    

    
    public function getBankcardtypeList()
    {
        return ['bank' => __('Bank'), 'usdt' => __('Usdt'), 'alipay' => __('Alipay')];
    }

    public function getCaraddresstypeList()
    {
        return ['ERC20' => __('ERC20'), 'TRC20' => __('TRC20'), '-' => __('-')];
    }

    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1')];
    }


    public function getBankcardtypeTextAttr($value, $data)
    {
        $value = $value ?: ($data['bankcardtype'] ?? '');
        $list = $this->getBankcardtypeList();
        return $list[$value] ?? '';
    }


    public function getCaraddresstypeTextAttr($value, $data)
    {
        $value = $value ?: ($data['caraddresstype'] ?? '');
        $list = $this->getCaraddresstypeList();
        return $list[$value] ?? '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? '';
    }


    public function getChecktimeTextAttr($value, $data)
    {
        $value = $value ?: ($data['checktime'] ?? '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setChecktimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    /**
     * 关联用户模型
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'merchant_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

}
