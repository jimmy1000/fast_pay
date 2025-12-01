<?php

namespace app\common\model;

use think\Model;


class ApiType extends Model
{



    // 表名
    protected $name = 'api_type';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'status_text',
        'default_text'
    ];


    /**
     * 全局范围查询
     * @param $query
     */
    protected function base($query){
        $query->order('weight ASC,id DESC');
    }


    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1')];
    }

    public function getDefaultList()
    {
        return ['0' => __('Default 0'), '1' => __('Default 1')];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getDefaultTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['default']) ? $data['default'] : '');
        $list = $this->getDefaultList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    /**
     * 获取开启的接口列表
     */
    public static function getOpenList(){
        return collection(self::where('status','1')->select())->toArray();
    }

    public function rule(){
        return $this->belongsTo('ApiRule','api_rule_id','id',[],'LEFT')->setEagerlyType(0);
    }

    /**
     * 获取开启的接口列表包含规则以及默认状态
     */
    public static function getOpenListAndRule(){

        $open_list = collection(self::where('status','1')->select())->toArray();
        //id作为键值
        $open_list = array_combine(array_column($open_list,'id'),array_values($open_list));

        foreach ($open_list as $k=>$v){
            $rule_list  = ApiRule::getListByApiType($k);
            $open_list[$k]['rule_list'] = $rule_list;
        }

        return $open_list;

    }

}
