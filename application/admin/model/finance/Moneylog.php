<?php

namespace app\admin\model\finance;

use think\Model;


class Moneylog extends Model
{

    

    

    // 表名
    protected $name = 'user_money_log';
    
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
     * @return \think\model\relation\BelongsTo 关联用户表
     */
    public function user(){
        return $this->belongsTo('app\admin\model\User','user_id','id');
    }


}
