<?php

namespace app\admin\model\order;

use think\Model;


class NotifyLog extends Model
{

    

    

    // 表名
    protected $name = 'notify_log';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];
    
    /**
     * 关联订单
     */
    public function order()
    {
        return $this->belongsTo('app\admin\model\order\Order', 'order_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    /**
     * 写入通知日志
     */
    public static function log($orderId, $url, $data, $result)
    {
        return self::create([
            'order_id'  => $orderId,
            'notifyurl' => $url,
            'data'      => is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : (string)$data,
            'result'    => is_array($result) ? json_encode($result, JSON_UNESCAPED_UNICODE) : (string)$result,
        ]);
    }

}

