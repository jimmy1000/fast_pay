<?php
namespace app\common\model;

use think\Model;


class RepayUporder Extends Model
{

    // 表名
    protected $name = 'repay_uporder';
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = '';
    // 追加属性
    protected $append = [
        'status_text'
    ];


    public function getStatusTextAttr($value,$data){
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = [
            '0'=>'发起支付',
            '1'=>'支付成功',
            '2'=>'支付失败'
        ];
        return isset($list[$value]) ? $list[$value] : '';
    }

    /**
     * 获取订单号
     * @return int
     * @throws \think\exception\DbException
     */
    public static function createOrderNo()
    {
        $order_sn = create_orderno();
        if (!is_null(self::get(['orderno' => $order_sn]))) {
            $order_sn = self::createOrderNo();
        }
        return $order_sn;
    }

    public function account(){
        return $this->belongsTo('ApiAccount','api_account_id','id','','LEFT')->setEagerlyType(0);
    }


}