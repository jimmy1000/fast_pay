<?php

namespace app\admin\model\api;

use think\Model;
use app\admin\model\api\Account as ApiAccountModel;


class Rule extends Model
{

    

    

    // 表名
    protected $name = 'api_rule';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'type_text',
        'account_weight_list',
    ];
    

    
    public function getTypeList()
    {
        return ['0' => __('Type 0'), '1' => __('Type 1'), '2' => __('Type 2')];
    }


    public function getTypeTextAttr($value, $data)
    {
        $value = $value ?: ($data['type'] ?? '');
        $list = $this->getTypeList();
        return $list[$value] ?? '';
    }




    public function types()
    {
        return $this->hasMany('Type', 'api_rule_id', 'id');
    }

    /**
     * 关联支付类型（belongsTo）
     */
    public function apitype()
    {
        return $this->belongsTo('Type', 'api_type_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    /**
     * 计算属性：接口账号及权重列表
     * 返回形如：[{id:1,name:"自营",weight:2},...]
     */
    public function getAccountWeightListAttr($value, $data)
    {
        $result = [];
        $raw = $data['api_account_ids'] ?? '';
        if (!$raw) {
            return $result;
        }
        $pairs = array_filter(explode(',', $raw));
        $idToWeight = [];
        $ids = [];
        foreach ($pairs as $pair) {
            $tmp = explode(':', $pair);
            if (count($tmp) < 2) { continue; }
            $id = (int)$tmp[0];
            $weight = (int)$tmp[1];
            $idToWeight[$id] = $weight ?: 1;
            $ids[] = $id;
        }
        if (!$ids) {
            return $result;
        }
        $accounts = ApiAccountModel::where('id','in',$ids)->column('name','id');
        foreach ($ids as $id) {
            $result[] = [
                'id' => $id,
                'name' => $accounts[$id] ?? ('ID:'.$id),
                'weight' => $idToWeight[$id] ?? 1,
            ];
        }
        return $result;
    }

    /**
     * 入库时将表单的 [id][],[weight][] 组装为 id:weight,id:weight
     */
    public function setApiAccountIdsAttr($value)
    {
        // 兼容直接传字符串
        if (!is_array($value)) {
            return (string)$value;
        }
        $fieldArray = [];
        if (!empty($value['id'])) {
            foreach ($value['id'] as $k => $v) {
                if (!$v) continue;
                $w = isset($value['weight'][$k]) && $value['weight'][$k] !== '' ? $value['weight'][$k] : 1;
                $fieldArray[] = $v . ':' . $w;
            }
        }
        return implode(',', $fieldArray);
    }

    /**
     * 取出时解析为 [ 'id'=>[...], 'weight'=>[id=>weight] ]
     */
    public function getApiAccountIdsAttr($value)
    {
        if (!$value) return ['id' => [], 'weight' => []];
        $result = ['id' => [], 'weight' => []];
        foreach (explode(',', $value) as $pair) {
            $tmp = explode(':', $pair);
            if (count($tmp) < 2) continue;
            $id = $tmp[0];
            $weight = $tmp[1];
            $result['id'][] = $id;
            $result['weight'][$id] = $weight;
        }
        return $result;
    }

}
