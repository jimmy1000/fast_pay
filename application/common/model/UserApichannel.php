<?php

namespace app\common\model;

use think\Model;


class UserApichannel extends Model
{


    // 表名
    protected $name = 'user_apichannel';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;


    /**
     * 根据userid获取列表
     * @param $user_id
     */
    public static function getListByUser($user_id){

        $list = self::where('user_id',$user_id)->select();

        if(count($list) > 0 ){
            $list = collection($list)->toArray();
            $list = array_combine(array_column($list,'api_type_id'),array_values($list));
            return $list;
        }
        return [];

    }

}
