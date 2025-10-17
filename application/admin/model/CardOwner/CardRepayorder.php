<?php

namespace app\admin\model\CardOwner;

use think\Model;
use traits\model\SoftDelete;

class CardRepayorder extends Model
{

    use SoftDelete;

    

    // 表名
    protected $name = 'card_repayorder';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        'status_text',
        'notify_status_text'
    ];
    

    
    public function getStatusList()
    {
        return ['pending' => __('Pending'), 'processing' => __('Processing'), 'success' => __('Success'), 'failed' => __('Failed'), 'closed' => __('Closed')];
    }

    public function getNotifyStatusList()
    {
        return ['none' => __('None'), 'doing' => __('Doing'), 'success' => __('Success'), 'failed' => __('Failed')];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? '';
    }


    public function getNotifyStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['notify_status'] ?? '');
        $list = $this->getNotifyStatusList();
        return $list[$value] ?? '';
    }




}
