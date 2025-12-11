<?php

namespace app\common\model;

use think\Model;

/**
 * 会员余额日志模型
 */
class MoneyLog extends Model
{

    // 表名
    protected $name = 'user_money_log';
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = '';
    // 追加属性
    protected $append = [
        'style_text'
    ];
    public function getStyleTextAttr($value,$data){
        $value = $value ? $value : (isset($data['style']) ? $data['style'] : '');
        $list = $this->getStyleList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getStyleList()
    {
        return ['1' => '充值', '2' => '提现','3'=>'代理佣金'];
    }
}
