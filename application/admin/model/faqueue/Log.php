<?php

namespace app\admin\model\faqueue;

use think\Model;


class Log extends Model
{

    

    

    // 表名
    protected $name = 'faqueue_log';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'create_time_text',
        'update_time_text'
    ];
    

    



    public function getCreateTimeTextAttr($value, $data)
    {
        $value = $value ?: ($data['create_time'] ?? '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getUpdateTimeTextAttr($value, $data)
    {
        $value = $value ?: ($data['update_time'] ?? '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setCreateTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setUpdateTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    /**
     * 记录队列日志
     *
     * @param string $queue 队列名
     * @param string $job   执行类
     * @param array  $data  任务数据
     * @return static
     */
    public static function log($queue, $job, $data)
    {
        return self::create([
            'queue'       => $queue,
            'job'         => $job,
            'data'        => is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : (string)$data,
        ]);
    }

}
