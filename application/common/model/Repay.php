<?php

namespace app\common\model;

use think\Db;
use think\Log;
use think\Model;
use think\Queue;

class Pay extends Model
{
    // 表名
    protected $name = 'repay_order';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;

    // 追加属性
    protected $append = [
        'style_text',
        'status_text',
        'daifustatus_text',
        'notify_status_text',
        'createtime_text',
        'paytime_text',
    ];

    public function getCreatetimeTextAttr($value, $data)
    {
        $value = $value ? $value : ($data['createtime'] ?? '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    public function getPaytimeTextAttr($value, $data)
    {
        $value = $value ? $value : ($data['paytime'] ?? '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    public function getStyleList()
    {
        return ['0' => '后台申请', '1' => 'API提交'];
    }

    public function getStatusList()
    {
        return ['0' => '待处理', '1' => '已支付', '2' => '冻结', '3' => '已取消', '4' => '失败'];
    }

    public function getDaifustatusList()
    {
        return ['0' => '未提交', '1' => '已提交', '2' => '已失败', '3' => '已成功'];
    }

    public function getNotifyStatusList()
    {
        return ['0' => '未通知', '1' => '通知成功', '2' => '通知失败'];
    }

    public function getStyleTextAttr($value, $data)
    {
        $value = $value ? $value : ($data['style'] ?? '');
        $list = $this->getStyleList();
        return $list[$value] ?? '';
    }

    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? '';
    }

    public function getDaifustatusTextAttr($value, $data)
    {
        $value = $value ? $value : ($data['daifustatus'] ?? '');
        $list = $this->getDaifustatusList();
        return $list[$value] ?? '';
    }

    public function getNotifyStatusTextAttr($value, $data)
    {
        $value = $value ? $value : ($data['notify_status'] ?? '');
        $list = $this->getNotifyStatusList();
        return $list[$value] ?? '';
    }

    public function getReqInfoAttr($value)
    {
        $req = [];
        parse_str($value, $req);
        return $req;
    }

    public static function createOrderNo()
    {
        $orderSn = create_orderno();
        if (!is_null(self::get(['orderno' => $orderSn]))) {
            $orderSn = self::createOrderNo();
        }
        return $orderSn;
    }

    /**
     * 提交代付
     * @param int|string $payId
     * @param int $dfAccountId
     * @param bool $isOrderno
     * @return array
     */
    public static function dfSubmit($payId, $dfAccountId, $isOrderno = false)
    {
        if ($isOrderno) {
            $payModel = self::get([
                'orderno' => $payId,
                'status' => '0',
                'daifustatus' => ['in', '0,2'],
            ]);
            if (is_null($payModel)) {
                exception('用户支付信息不存在或已支付！');
            }
            $payId = $payModel['id'];
        } else {
            $payModel = self::get([
                'id' => $payId,
                'status' => '0',
                'daifustatus' => ['in', '0,2'],
            ]);
        }

        if (is_null($payModel)) {
            exception('用户支付信息不存在或已支付！');
        }

        $accountModel = ApiAccount::get([
            'id' => $dfAccountId,
            'ifrepay' => '1',
        ]);
        if (is_null($accountModel)) {
            exception('代付账户未开启或不存在！');
        }

        $waitedCount = PayOrder::where([
            'pay_id' => $payId,
            'status' => ['in', '0,1'],
        ])->count();
        if ($waitedCount > 0) {
            exception('代付状态已成功或者正在代付，请不要重复打款');
        }

        Db::startTrans();
        try {
            $ddh = 'DF' . PayOrder::createOrderNo();

            $domain = config('site.gateway');
            $payDomain = $accountModel['domain'];

            $payOrder = PayOrder::create([
                'orderno'      => $ddh,
                'createtime'   => time(),
                'pay_id'       => $payId,
                'api_account_id' => $dfAccountId,
                'status'       => '0',
            ]);

            $params = [
                'orderno'   => $ddh,
                'payData'   => $payModel->toArray(),
                'params'    => $accountModel['params'],
                'notifyUrl' => $domain . "/Pay/repaynotify/code/" . $accountModel->upstream->code,
                'pay_domain' => $payDomain,
            ];

            $result = loadApi($accountModel->upstream->code)->repay($params);
            if ($result[0] == 0) {
                $payModel->msg = $result['msg'] ?? '银行通道异常,代付提交失败!';
                $payModel->save();
                $payOrder->delete();
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            exception('系统异常:' . $e->getMessage());
        }

        return $result;
    }

    /**
     * 变更代付状态
     * @param array $params
     * @return array
     */
    public static function changePayStatus($params)
    {
        $payOrderModel = PayOrder::get(['orderno' => $params['orderno']]);
        if (is_null($payOrderModel)) {
            return [0, '未找到代付订单。'];
        }
        $payModel = self::get($payOrderModel['pay_id']);
        if (is_null($payModel)) {
            return [0, '未找到代付订单。'];
        }
        if ($payModel['money'] != $params['money']) {
            return [0, '代付金额与提交金额不一致。'];
        }
        if ($payModel['status'] > 1) {
            return [0, '订单已被管理员处理'];
        }

        $status = 2;
        $return = '处理完成.';
        if ($payModel['status'] == '0') {
            $payData = [];
            $payOrderData = [];
            if ($params['status'] == '1') {
                $return = '代付已支付';
                $status = 1;
                $payData = [
                    'status'      => $status,
                    'daifustatus' => '3',
                    'paytime'     => time(),
                    'upcharge'    => $params['fee'] ?? 0,
                    'msg'         => $params['msg'] ?? '',
                    'utr'         => $params['utr'] ?? '',
                ];
                $payOrderData = [
                    'status'     => '1',
                    'outorderno' => $params['outorderno'] ?? '',
                    'paytime'    => time(),
                    'outdesc'    => $params['msg'] ?? '',
                ];
            } elseif ($params['status'] == '0' || $params['status'] == '3') {
                $money = $payModel['money'];
                $charge = $payModel['charge'];
                $needMoney = bcadd($money, $charge, 2);
                $userModel = User::getByMerchantId($payModel['merchant_id']);
                if ($userModel) {
                    $userModel->setDec('withdrawal', $needMoney);
                    User::money($needMoney, $userModel->id, '代付失败返回' . $needMoney . '越南盾: ' . $money . '越南盾，手续费：' . $charge . '越南盾', $payModel['orderno'], '2');
                }
                $payData = [
                    'status'      => $params['status'] == '0' ? '4' : '3',
                    'msg'         => $params['msg'] ?? '',
                    'daifustatus' => $params['status'] == '0' ? '2' : '4',
                ];
                $payOrderData = [
                    'status'     => '2',
                    'outorderno' => $params['outorderno'] ?? '',
                    'paytime'    => time(),
                    'outdesc'    => $params['msg'] ?? '',
                ];
                $return = $params['status'] == '0' ? '当前代付提交返回状态失败' : '当前代付提交返回状态驳回';
            } elseif ($params['status'] == '2') {
                $return = '当前代付提交返回状态代付中';
                $payData = [
                    'msg'         => $params['msg'] ?? '',
                    'daifustatus' => '1',
                ];
                $payOrderData = [
                    'status'     => '0',
                    'outorderno' => $params['outorderno'] ?? '',
                    'paytime'    => time(),
                    'outdesc'    => $params['msg'] ?? '',
                ];
            }

            Db::startTrans();
            try {
                $payModel->save($payData);
                if (!empty($payOrderData)) {
                    $payOrderModel->save($payOrderData);
                }
                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                return [0, $e->getMessage()];
            }

            if ($payModel['style'] == '1' && !empty($payModel['req_info']['notifyUrl']) && $params['status'] != '2') {
                Queue::push('app\common\job\RepayNotify', ['pay_id' => $payModel->id]);
            }

            return [1, $return];
        }

        return [0, '该订单已支付成功或已被管理员处理'];
    }
}

