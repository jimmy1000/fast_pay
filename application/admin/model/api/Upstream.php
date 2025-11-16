<?php

namespace app\admin\model\api;

use think\Model;


class Upstream extends Model
{

    

    

    // 表名
    protected $name = 'api_upstream';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];
    

    /**
     * 参数转换为json数据
     * @param $value
     * @return false|string
     */
    public function setParamsAttr($value){
        // 如果已经是 JSON 字符串，先解码
        if(is_string($value)){
            $value = json_decode($value, true);
        }
        // 如果是数组，重新索引（防止关联数组）
        if(is_array($value)){
            $value = array_values($value);
        }
        return \json_encode($value,JSON_UNESCAPED_UNICODE);
    }

    /**
     * 参数转换为数组
     * @param $value
     * @return mixed
     */
    public function getParamsAttr($value){
        return \json_decode($value,true);
    }

}
