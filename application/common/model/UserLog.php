<?php

namespace app\common\model;

use think\Model;

class UserLog extends Model
{
    // 表名
    protected $name = 'user_log';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [];

    /**
     * 添加用户日志
     * @param string $merchantid
     * @param string $content
     * @return static
     */
    public static function addLog($merchantid, $content)
    {
        $module = request()->baseUrl();
        $ip = request()->ip();
        $request = request()->param(false);
        unset($request['password'], $request['paypassword']);
        $request = serialize($request);

        $data = [
            'merchantid' => $merchantid,
            'content'    => $content,
            'module'     => $module,
            'ip'         => $ip,
            'request'    => $request,
        ];
        return self::create($data);
    }
}

