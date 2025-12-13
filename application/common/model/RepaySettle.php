<?php

namespace app\common\model;

use think\Model;

/**
 * 结算账单模型（对应表 ep_repay_settle）
 */
class RepaySettle extends Model
{
    // 表名
    protected $name = 'repay_settle';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'style_text',
        'status_text',
    ];

    public function getStyleList(): array
    {
        return ['0' => __('法币结算'), '1' => __('USDT下发')];
    }

    public function getApplyStyleList(): array
    {
        return ['0' => __('商户后台'), '1' => __('系统后台')];
    }

    public function getCaraddresstypeList(): array
    {
        return ['TRC20' => 'TRC20', 'ERC20' => 'ERC20', '-' => '-'];
    }

    public function getStatusList(): array
    {
        return [
            '0' => __('审核中'),
            '1' => __('已支付'),
            '2' => __('取消'),
        ];
    }
    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? '';
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

}