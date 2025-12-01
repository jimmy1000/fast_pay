<?php

namespace app\common\model;

use Carbon\Carbon;
use think\Model;


class ApiRule extends Model
{


    // 表名
    protected $name = 'api_rule';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'type_text'
    ];


    public function getTypeList()
    {
        return ['0' => __('Type 0'), '1' => __('Type 1'), '2' => __('Type 2')];
    }


    public function getTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['type']) ? $data['type'] : '');
        $list = $this->getTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function apitype(){
        return $this->belongsTo('ApiType','api_type_id',[],'LEFT')->setEagerlyType(0);
    }

    public function setApiAccountIdsAttr($value)
    {

        $field_array = [];

        foreach ($value['id'] as $k => $v) {
            array_push($field_array, $v . ':' . $value['weight'][$k]);
        }

        return implode(',', $field_array);
    }

    public function getApiAccountIdsAttr($value,$row)
    {
        if(!$value) return [];


        $field_array = explode(',', $value);
        $result = [];


        foreach ($field_array as $k => $v) {

            //要去掉超过额度的通道id
            $tmp_array = explode(":", $v);
            $account_id = $tmp_array[0];
            $account_weight = $tmp_array[1];
            $channelModel = ApiChannel::get([
                'api_type_id'=>$row['api_type_id'],
                'api_account_id'=>$account_id
            ]);

            if($channelModel['daymoney'] > 0){
                $today = Carbon::now()->toDateString();
                if($channelModel['today'] == $today && $channelModel['todaymoney'] >= $channelModel['daymoney'] ){
                    continue;
                }
            }
            $result['id'][] = $account_id;
            $result['weight'][$account_id] = $account_weight;
        }
        return $result;
    }

    /**
     * 根据接口类型获取规则列表
     * @param $type_id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getListByApiType($type_id){
        $list = self::where([
            'api_type_id'=>$type_id
        ])->select();

        return collection($list)->toArray();
    }


    /**
     * 获取规则列表的费率 限额 充值范围等
     * @param $id
     * @return array
     * @throws \think\Exception
     * @throws \think\exception\DbException
     */
    public static function getChannelInfo($id,$isSort=true,$isUsable = false,$money=0){
        $row = self::get($id);
        if(is_null($row)) return [];
        $row = $row->toArray();

        //修复限额之后获取不到账户的bug
        if(empty( $row['api_account_ids'])){
            return [];
        }
        $account_list = $row['api_account_ids']['id'];
        $rate_list = [];    //费率
        $money_range_list = []; //充值范围
        $total = 0;   //当天限额
        $has = 0;     //已经交易了多少


        foreach ($account_list as $key=>$item){
            $account = explode(':',$item)[0];

            //从channel表中获取该rule 的费率
            $channelModel = ApiChannel::get([
                'api_account_id'=>$account,
                'api_type_id'=>$row['api_type_id']
            ]);

            if($isUsable){

                $flag = false;

                if($channelModel['daymoney'] > 0){
                    //限额
                    if(bcadd($channelModel['todaymoney'],$money) >= floatval($channelModel['daymoney'])){
                        $flag = true;
                    }
                }


                //单笔金额限制
                if ($channelModel['minmoney'] > 0 && $money < $channelModel['minmoney']) {
                    $flag = true;
                }
                if ($channelModel['maxmoney'] > 0 && $money > $channelModel['maxmoney']) {
                    $flag = true;
                }

                if ($flag){
                    unset($row['api_account_ids']['id'][$key]);
                    unset($row['api_account_ids']['weight'][$account]);
                    continue;
                }
            }


            $rate_list[] = $channelModel['rate'];
            $money_range_list[] = $channelModel['minmoney'].'-'.$channelModel['maxmoney'];
            $total = $total + $channelModel['daymoney'];
            $has = $has+$channelModel['todaymoney'];

        }
        if($isSort){
            $rate_list = array_unique($rate_list);
            asort($rate_list);
        }

        $money_range_list = array_unique($money_range_list);

        $row['api_account_ids']['id'] = array_values($row['api_account_ids']['id']);
        return [
            'info'=>$row,
            'rate_list'=>$rate_list,
            'money_range_list'=>$money_range_list,
            'total'=>$total,
            'has'=>$has
        ];
    }



    public function upstream(){
        return $this->belongsTo('ApiUpsream','api_upsream_id','id');
    }

}
