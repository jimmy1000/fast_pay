<?php
/**
 * NotifyLog.php
 * 易聚合支付系统
 * =========================================================
 * 请尊重开发人员劳动成果，严禁使用本系统转卖、销售或二次开发后转卖、销售等商业行为。
 * 本源码仅供技术学习研究使用,请勿用于非法用途,如产生法律纠纷与作者无关。
 * =========================================================
 * @author : 666666@qq.com
 * @date : 2019-05-04
 */

namespace app\common\model;

use think\Model;

class NotifyLog extends Model{


    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;



    public static function log($order_id,$notifyurl,$data,$result){

        $data = is_string($data) ? $data : urldecode(http_build_query($data));
        self::create([
            'order_id'=>$order_id,
            'data'=>$data,
            'notifyurl'=>$notifyurl,
            'result'=>$result
        ]);
    }
}