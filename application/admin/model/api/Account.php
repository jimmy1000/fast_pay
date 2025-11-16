<?php

namespace app\admin\model\api;

use app\common\model\api\Channel as ApiChannel;
use think\Model;


class Account extends Model
{

    

    

    // 表名
    protected $name = 'api_account';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'ifrepay_text',
        'ifrecharge_text',
        'channel_list'
    ];

    /**
     * 处理操作的事件
     */
    protected static function init()
    {
        //删除关联数据
        self::afterDelete(function ($row){
            $api_account_id = $row->id;
            ApiChannel::where('api_account_id', $api_account_id)->delete();
        });
    }

    /**
     * 获取支持的所有通道信息
     */
    public function getChannelListAttr($value, $data)
    {
        //获取所有的通道信息
        $channelList = ApiChannel::where([
            'api_account_id' => $data['id']
        ])->with(['apitype' => function($query) {
            $query->withField('id,name,code');
        }])->select();

        $channelList = collection($channelList)->toArray();
        return $channelList;
    }

    public function getIfrepayList()
    {
        return ['0' => __('Ifrepay 0'), '1' => __('Ifrepay 1')];
    }

    public function getIfrechargeList()
    {
        return ['0' => __('Ifrecharge 0'), '1' => __('Ifrecharge 1')];
    }


    public function getIfrepayTextAttr($value, $data)
    {
        $value = $value ?: ($data['ifrepay'] ?? '');
        $list = $this->getIfrepayList();
        return $list[$value] ?? '';
    }


    public function getIfrechargeTextAttr($value, $data)
    {
        $value = $value ?: ($data['ifrecharge'] ?? '');
        $list = $this->getIfrechargeList();
        return $list[$value] ?? '';
    }

    /**
     * 参数转换为json数据
     * Account 的 params 是键值对：{"mchId":"eapay","key":"xxx"}
     * 不需要 array_values()，否则会丢失键名
     * @param $value
     * @return false|string
     */
    public function setParamsAttr($value){
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

    /**
     * 关联上游
     */
    public function upstream()
    {
        return $this->belongsTo('Upstream', 'api_upstream_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

}
