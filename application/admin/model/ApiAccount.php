<?php

namespace app\admin\model;

use app\common\model\ApiChannel;
use think\Model;


class ApiAccount extends Model
{



    // 表名
    protected $name = 'api_account';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

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
            ApiChannel::destroy(['api_account_id'=>$api_account_id]);
        });

    }


    //获取支持的所有通道信息
    public function getChannelListAttr($value,$data){


        //获取所有的通道信息
        $channelList = ApiChannel::where([
            'api_account_id'=>$data['id']
        ])->with(['apitype'=>function($query){
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
        $value = $value ? $value : (isset($data['ifrepay']) ? $data['ifrepay'] : '');
        $list = $this->getIfrepayList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getIfrechargeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['ifrecharge']) ? $data['ifrecharge'] : '');
        $list = $this->getIfrechargeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function setParamsAttr($value){
        return \json_encode($value,JSON_UNESCAPED_UNICODE);
    }

    public function getParamsAttr($value){
        return \json_decode($value,true);
    }

    public function upstream(){
        return $this->belongsTo('ApiUpstream','api_upstream_id','id','','LEFT')->setEagerlyType(0);
    }

    public function myorder(){
        return $this->hasMany('Order','api_account_id','id');
    }


}
