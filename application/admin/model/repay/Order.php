<?php

namespace app\admin\model\repay;
use think\Model;

class Order extends Model
{
    // 表名
    protected $name = 'repay_order';

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
        'daifustatus_text',
        'notify_status_text',
        'paytime_text',
    ];

    public function getStyleList()
    {
        return ['0' => __('Style 0'), '1' => __('Style 1')];
    }

    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1'), '2' => __('Status 2'), '3' => __('Status 3'), '4' => __('Status 4')];
    }

    public function getDaifustatusList()
    {
        return ['0' => __('Daifustatus 0'), '1' => __('Daifustatus 1'), '2' => __('Daifustatus 2'), '3' => __('Daifustatus 3'), '4' => __('Daifustatus 4')];
    }

    public function getNotifyStatusList()
    {
        return ['0' => __('Notify_status 0'), '1' => __('Notify_status 1'), '2' => __('Notify_status 2')];
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

    public function getDaifustatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['daifustatus'] ?? '');
        $list = $this->getDaifustatusList();
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

    protected function setPaytimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    /**
     * 解析 req_info 为数组
     */
    protected function getReqInfoAttr($value)
    {
        if (!$value) {
            return [];
        }
        if (is_array($value)) {
            return $value;
        }
        $req = [];
        parse_str($value, $req);
        return $req;
    }

    /**
     * 关联商户
     */
    public function user()
    {
        return $this->belongsTo('app\common\model\User', 'merchant_id', 'merchant_id', [], 'LEFT')->setEagerlyType(0);
    }
    public function uporder()
    {
        return $this->belongsTo('app\admin\model\repay\Uporder', 'id', 'pay_id', '', 'LEFT')->setEagerlyType(0);
    }
}
