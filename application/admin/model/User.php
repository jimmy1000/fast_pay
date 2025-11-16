<?php

namespace app\admin\model;

use app\common\model\MoneyLog;
use app\common\model\ScoreLog;
use think\Model;
use fast\Random;
use app\admin\model\user\Auth as UserAuth;
use app\admin\model\user\Log as UserLog;
use app\admin\model\mq\Order;

class User extends Model
{

    // 表名
    protected $name = 'user';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    // 追加属性
    protected $append = [
        'prevtime_text',
        'logintime_text',
        'jointime_text'
    ];

    public function getOriginData()
    {
        return $this->origin;
    }

    protected static function init()
    {
        $auth = \app\common\library\Auth::instance();
        
        // 更新前处理：密码加密、金额变更日志
        self::beforeUpdate(function ($row) use ($auth) {
            $changed = $row->getChangedData();
            
            // 处理登录密码
            if (isset($changed['password']) && $changed['password']) {
                $salt = Random::alnum();
                $row->password = $auth->getEncryptPassword($changed['password'], $salt);
                $row->salt = $salt;
            } elseif (isset($changed['password'])) {
                unset($row->password);
            }
            
            // 处理支付密码
            if (isset($changed['paypassword']) && $changed['paypassword']) {
                $salt = Random::alnum();
                $row->paypassword = $auth->getEncryptPassword($changed['paypassword'], $salt);
                $row->paysalt = $salt;
            } elseif (isset($changed['paypassword'])) {
                unset($row->paypassword);
            }
            
            // 记录金额变更日志
            if (isset($changed['money'])) {
                $origin = $row->getOriginData();
                MoneyLog::create([
                    'user_id' => $row['id'],
                    'money' => $changed['money'] - $origin['money'],
                    'before' => $origin['money'],
                    'after' => $changed['money'],
                    'memo' => '管理员变更金额'
                ]);
            }
        });

        // 插入前：生成md5key、设置默认值
        self::beforeInsert(function ($row) {
            $row->md5key = Random::alpha(32);
            $row->jointime = $row->jointime ?? time();
            $row->joinip = $row->joinip ?? '0.0.0.0';
        });

        // 插入后：生成商户号
        self::afterInsert(function ($row) {
            $row->merchant_id = date("Ym") . $row->id;
            $row->save();
        });

        // 删除后：清理关联数据
        self::afterDelete(function ($row) {
            $merchantId = $row->getData('merchant_id');
            UserAuth::destroy(['user_id' => $row->id]);
            UserLog::destroy(['merchantid' => $merchantId]);
            Order::destroy(['merchant_id' => $merchantId]);
            MoneyLog::destroy(['user_id' => $row->id]);
        });
    }

    public function getGenderList()
    {
        return ['1' => __('Male'), '0' => __('Female')];
    }

    public function getStatusList()
    {
        return ['normal' => __('Normal'), 'hidden' => __('Hidden')];
    }


    public function getPrevtimeTextAttr($value, $data)
    {
        $value = $value ? $value : ($data['prevtime'] ?? "");
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    public function getLogintimeTextAttr($value, $data)
    {
        $value = $value ? $value : ($data['logintime'] ?? "");
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    public function getJointimeTextAttr($value, $data)
    {
        $value = $value ? $value : ($data['jointime'] ?? "");
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setPrevtimeAttr($value)
    {
        return $value && !is_numeric($value) ? strtotime($value) : $value;
    }

    protected function setLogintimeAttr($value)
    {
        return $value && !is_numeric($value) ? strtotime($value) : $value;
    }

    protected function setJointimeAttr($value)
    {
        return $value && !is_numeric($value) ? strtotime($value) : $value;
    }

    protected function setBirthdayAttr($value)
    {
        return $value ? $value : null;
    }

    public function group()
    {
        return $this->belongsTo('UserGroup', 'group_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

}
