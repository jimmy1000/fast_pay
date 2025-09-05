<?php

namespace app\admin\model\CardOwner;

use think\Model;


class CardOwner extends Model
{

    

    

    // 表名
    protected $name = 'card_owner';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'google_bind_text',
        'status_text'
    ];
    

    
    public function getGoogleBindList()
    {
        return ['0' => __('Google_bind 0'), '1' => __('Google_bind 1'), '2' => __('Google_bind 2')];
    }

    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1'), '2' => __('Status 2')];
    }


    public function getGoogleBindTextAttr($value, $data)
    {
        $value = $value ?: ($data['google_bind'] ?? '');
        $list = $this->getGoogleBindList();
        return $list[$value] ?? '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? '';
    }




}
