<?php

namespace app\admin\model\repay;

use think\Model;

class Notifylog extends Model
{
    // 表名
    protected $name = 'repay_notifylog';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    /**
     * 写通知日志
     *
     * @param int         $orderId
     * @param string      $url
     * @param array|string $data
     * @param array|string $result
     * @return static
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
    public function repayorder()
    {
        return $this->belongsTo('app\admin\model\repay\Order', 'order_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}

