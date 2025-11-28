<?php

namespace app\admin\model\repay;

use think\Model;


class Settle extends Model
{

    

    

    // 表名
    protected $name = 'repay_settle';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'style_text',
        'apply_style_text',
        'caraddresstype_text',
        'status_text',
        'paytime_text'
    ];
    

    
    public function getStyleList()
    {
        return ['0' => __('法币结算'), '1' => __('USDT下发')];
    }

    public function getApplyStyleList()
    {
        return ['0' => __('商户后台'), '1' => __('系统后台')];
    }

    public function getCaraddresstypeList()
    {
        return ['TRC20' => 'TRC20', 'ERC20' => 'ERC20', '-' => '-'];
    }

    public function getStatusList()
    {
        return [
            '0' => __('审核中'),
            '1' => __('已支付'),
            '2' => __('取消'),
        ];
    }


    public function getStyleTextAttr($value, $data)
    {
        $value = $value ?: ($data['style'] ?? '');
        $list = $this->getStyleList();
        return $list[$value] ?? '';
    }


    public function getApplyStyleTextAttr($value, $data)
    {
        $value = $value ?: ($data['apply_style'] ?? '');
        $list = $this->getApplyStyleList();
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


    public function getPaytimeTextAttr($value, $data)
    {
        $value = $value ?: ($data['paytime'] ?? '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setPaytimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


}
