<?php


namespace app\common\model;

use think\Model;

class ApiLog extends Model
{



    // 表名
    protected $name = 'api_log';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'status_text'
    ];


    public function getContentAttr($value){
        return unserialize($value);
    }

    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1')];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }



    public static function log($data, $msg = '',$result=''){

        $ip = request()->ip();
        $http = empty($_SERVER['HTTP_REFERER'])? '' : $_SERVER['HTTP_REFERER'];


        $content = serialize($data);
        $status = 0;
        if (empty($msg)) {
            $status = 1;
            $msg = $result;
        }
        $data = [
            'merchant_id'=>empty($data['merId']) ? '0' : $data['merId'],
            'http'=>$http,
            'content'=>$content,
            'result'=>$msg,
            'status'=>$status,
            'orderno'=>empty($data['orderId']) ? '0' : $data['orderId'],
            'total_money'=>empty($data['orderAmt']) ? '0' : $data['orderAmt'],
            'channel'=>empty($data['channel']) ? '0' : $data['channel'],
            'ip'=>$ip
        ];

        self::create($data);
    }

}
