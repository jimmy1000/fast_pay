<?php

namespace app\common\model;

use think\Model;


class ApiUpstream extends Model
{



    // 表名
    protected $name = 'api_upstream';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

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

        $value = array_values($value);
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
