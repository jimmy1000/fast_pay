<?php

namespace app\common\model;

use think\Model;

class ApiAccount extends Model
{
    // 数据库
    protected $connection = 'database';

    // 表名
    protected $name = 'api_account';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'ifrepay_text',
        'ifrecharge_text',
    ];

    public function getIfrepayList()
    {
        return ['0' => __('Ifrepay 0'), '1' => __('Ifrepay 1')];
    }

    public function getIfrechargeList()
    {
        return ['0' => __('Ifrecharge 0'), '1' => __('Ifrecharge 1')];
    }

    public function getIfrepayTextAttr($value, $data)
    {
        $value = $value ?: ($data['ifrepay'] ?? '');
        $list  = $this->getIfrepayList();
        return $list[$value] ?? '';
    }

    public function getIfrechargeTextAttr($value, $data)
    {
        $value = $value ?: ($data['ifrecharge'] ?? '');
        $list  = $this->getIfrechargeList();
        return $list[$value] ?? '';
    }

    public function setParamsAttr($value)
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    public function getParamsAttr($value)
    {
        return json_decode($value, true);
    }

    public function upstream()
    {
        return $this->belongsTo('ApiUpstream', 'api_upstream_id', 'id');
    }
}

