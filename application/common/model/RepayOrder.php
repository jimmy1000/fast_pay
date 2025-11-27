<?php

namespace app\common\model;

use think\Db;
use think\Log;
use think\Model;
use think\Queue;
class RepayOrder extends Model
{


    // 表名
    protected $name = 'repay_order';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'style_text',
        'status_text',
        'daifustatus_text',
        'notify_status_text',
        'createtime_text',
        'paytime_text'
    ];


    public function getCreatetimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['createtime']) ? $data['createtime'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }
    public function getPaytimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['paytime']) ? $data['paytime'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    public function getStyleList()
    {
        return ['0' => '后台批量', '1' => 'API提交'];
    }

    public function getStatusList()
    {
        return ['0' => '待处理', '1' => '已支付', '2' => '冻结', '3' => '已取消','4' => '失败'];
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
        $value = $value ? $value : (isset($data['style']) ? $data['style'] : '');
        $list = $this->getStyleList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getDaifustatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['daifustatus']) ? $data['daifustatus'] : '');
        $list = $this->getDaifustatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getNotifyStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['notify_status']) ? $data['notify_status'] : '');
        $list = $this->getNotifyStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    protected function getReqInfoAttr($value)
    {

        $req = [];
        parse_str($value, $req);

        return $req;
    }

    /**
     * 获取订单号
     * @return int
     * @throws \think\exception\DbException
     */
    public static function createOrderNo()
    {
        $order_sn = create_orderno();
        if (!is_null(self::get(['orderno' => $order_sn]))) {
            $order_sn = self::createOrderNo();
        }
        return $order_sn;
    }


    /**
     * 代付接口提交
     * @param $payId
     * @param $dfAccountId
     */
    public static function dfSubmit($payId, $dfAccountId, $isOrderno=false)
    {
        if ($isOrderno) {
            $payModel = self::get([
                'orderno' => $payId,
                'status' => '0',
                'daifustatus' => ['in', '0,2']
            ]);

            if (is_null($payModel)) {
                exception('用户支付信息不存在或已支付！');
            }
            $payId = $payModel['id'];

        } else {
            $payModel = self::get([
                'id' => $payId,
                'status' => '0',
                'daifustatus' => ['in', '0,2']
            ]);
        }


        if (is_null($payModel)) {
            exception('用户支付信息不存在或已支付！');
        }
        $accountModel = ApiAccount::get([
            'id' => $dfAccountId,
            'ifrepay' => '1'
        ]);
        if (is_null($accountModel)) {
            exception('代付账户未开启或不存在！');
        }
        //查看是不是有等待支付的订单
        $waitedCount = RepayUporder::where([
            'pay_id' => $payId,
            'status' => ['in', '0,1']
        ])->count();
        if ($waitedCount > 0) {
            exception('代付状态已成功或者正在代付，请不要重复打款');
        }
        Db::startTrans();

        try {
            //生成代付订单号
            $ddh = 'DF'.RepayUporder::createOrderNo();
            //调用支付接口
            $code = $accountModel->upstream->code;
            $domain = config('site.gateway');   //返回的域名
            //获取系统域名

            //获取支付域名
            $pay_domain = $accountModel['domain'];
            //写入代付订单表
            $data = array(
                'orderno' => $ddh,
                'createtime' => time(),
                'pay_id' => $payId,
                'api_account_id' => $dfAccountId,
                'status' => '0'//状态:0=发起代付,1=代付支付成功,2=支付失败
            );
            $payOrder = RepayUporder::create($data);
            //调用代付接口的参数
            $params = array(
                'orderno' => $ddh,
                'payData' => $payModel->toArray(),
                'params' => $accountModel['params'],
                'notifyUrl' => $domain . "/Pay/repaynotify/code/" . $code,
                'pay_domain' =>$pay_domain
            );
            $result = loadApi($code)->repay($params);
            if ($result[0] == 0) {
                $payModel->msg =$result['msg']??'银行通道异常,代付提交失败!';
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
     * $data=array(
     * 'orderno'=>$val['orderid'], 订单号
     * 'money'=>$val['amount'],金额
     * 'outorderno'=>$val['transaction'],外部订单
     * 'msg'=>$val['returnmsg'],返回说明
     * 'status'=>$val['returncode']=='00'?1:0 状态 0失败 1成功 2状态不变*
     * 更新代付订单状态
     * @param $params
     */
    public static function changePayStatus($params)
    {
        $payOrderModel = RepayUporder::get([
            'orderno' => $params['orderno']
        ]);

        if (is_null($payOrderModel)) {
            return [0, '未找到代付订单。'];
        }
        //检查金额
        $payModel = self::get($payOrderModel['pay_id']);
        if (is_null($payModel)) {
            return [0, '未找到代付订单。'];
        }

        if ($payModel['money'] != $params['money']) {
            return [0, '代付金额与提交金额不一致。'];
        }
        //如果订单已被冻结或者取消
        if ($payModel['status'] > 1) {
            return [0, '订单已被管理员处理'];
        }


        $status = 2; //代付进行中
        $return = '处理完成.';
        //只有申请中的订单可以操作
        if ($payModel['status'] == '0') {
            //代付已成功
            if ($params['status'] == '1') {
                $return = '代付已支付';
                $status = 1; //代付成功
                $payData = [
                    'status' => $status,
                    'daifustatus' => '3',
                    'paytime' => time(),
                    'upcharge'=>$params['fee'],
                    'msg' => $params['msg'],
                    'utr'=>$params['utr']
                ];
                $payOrderData = [
                    'status' => '1',
                    'outorderno' => $params['outorderno'],
                    'paytime' => time(),
                    'outdesc' => $params['msg']
                ];
            }

            //代付已失败返回资金
            if ($params['status'] == '0') {
                $money = $payModel['money'];
                $charge= $payModel['charge'];
                $needMoney=bcadd($money, $charge, 2);
                //更新用户金额
                $userModel = User::getByMerchantId($payModel['merchant_id']);      //用户
                $userModel->setDec('withdrawal', $needMoney);
                //资金变动
                User::money($needMoney, $userModel->id, '代付失败返回' . $needMoney.'卢比: '.$money . '卢比，手续费：' . $charge . '卢比', $payModel['orderno'], '2');
                $return = '当前代付提交返回状态失败';
                $payData = [
                    'status' => '4',
                    'msg' => $params['msg'],
                    'daifustatus' => '2'
                ];
                $payOrderData = [
                    'status' => '2',
                    'outorderno' => $params['outorderno'],
                    'paytime' => time(),
                    'outdesc' => $params['msg']
                ];
            }
            //代付已驳回返回资金
            if ($params['status'] == '3') {
                $money = $payModel['money'];
                $charge= $payModel['charge'];
                $needMoney=bcadd($money, $charge, 2);
                //更新用户金额
                $userModel = User::getByMerchantId($payModel['merchant_id']);      //用户
                $userModel->setDec('withdrawal', $needMoney);
                //资金变动
                User::money($needMoney, $userModel->id, '代付被驳回返回' . $needMoney.'卢比: '.$money . '卢比，手续费：' . $charge . '卢比', $payModel['orderno'], '2');
                $return = '当前代付提交返回状态驳回';
                $payData = [
                    'status' => '3',
                    'msg' => $params['msg'],
                    'daifustatus' => '4'
                ];
                $payOrderData = [
                    'status' => '2',
                    'outorderno' => $params['outorderno'],
                    'paytime' => time(),
                    'outdesc' => $params['msg']
                ];
            }

            //代付申请中
            if ($params['status'] == '2') {
                $return = '当前代付提交返回状态代付中';
                $payData = [
                    'msg' => $params['msg'],
                    'daifustatus' => "1"
                ];
                $payOrderData = [
                    'status' => '0',
                    'outorderno' => $params['outorderno'],
                    'paytime' => time(),
                    'outdesc' => $params['msg']
                ];
            }
            //开启事务提交
            Db::startTrans();
            try {
                $payModel->save($payData);
                $payOrderModel->save($payOrderData);
                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                return [0, $e->getMessage()];
            }
            //到这里可以通知客户了
            //自动代付为1,有通知地址,状态不为发起'代付状态'可通知
            if ($payModel['style'] == '1' && !empty($payModel['req_info']['notifyUrl']) && $params['status'] != '2') {
                Queue::push('app\common\job\RepayNotify',['pay_id'=>$payModel->id]);
            }
            return [1, $return];
        } else {
            return [0, '该订单已支付成功或已被管理员处理'];
        }

    }

    /**
     * $data=array(
     * 'orderno'=>$val['orderid'], 订单号
     * 'money'=>$val['amount'],金额
     * 'outorderno'=>$val['transaction'],外部订单
     * 'msg'=>$val['returnmsg'],返回说明
     * 'status'=>$val['returncode']=='00'?1:0 状态 0失败 1成功 2状态不变*
     * 更新代付订单状态
     * @param $params
     */
    public static function changeRepayStatus($params)
    {
        $payOrderModel = RepayUporder::get([
            'orderno' => $params['orderno']
        ]);

        if (is_null($payOrderModel)) {
            return [0, '未找到代付订单。'];
        }
        //检查金额
        $payModel = self::get($payOrderModel['pay_id']);
        if (is_null($payModel)) {
            return [0, '未找到代付订单。'];
        }

        if ($payModel['money'] != $params['money']) {
            return [0, '代付金额与提交金额不一致。'];
        }
        //如果订单已被冻结或者取消
        if ($payModel['status'] > 1) {
            return [0, '订单已被管理员处理'];
        }


        $status = 2; //代付进行中
        $return = '处理完成.';
        //只有申请中的订单可以操作
        if ($payModel['status'] == '0') {
            //代付已成功
            if ($params['status'] == '1') {
                $return = '代付已支付';
                $status = 1; //代付成功
                $payData = [
                    'status' => $status,
                    'daifustatus' => '3',
                    'paytime' => time(),
                    'upcharge'=>$params['fee'],
                    'msg' => $params['msg'],
                    'utr'=>$params['utr']
                ];
                $payOrderData = [
                    'status' => '1',
                    'outorderno' => $params['outorderno'],
                    'paytime' => time(),
                    'outdesc' => $params['msg']
                ];
            }

            //代付已失败返回资金
            if ($params['status'] == '0') {
                $money = $payModel['money'];
                $charge= $payModel['charge'];
                $needMoney=bcadd($money, $charge, 2);
                //更新用户金额
                $userModel = User::getByMerchantId($payModel['merchant_id']);      //用户
                $userModel->setDec('withdrawal', $needMoney);
                //资金变动
                User::money($needMoney, $userModel->id, '代付失败返回' . $needMoney.'卢比: '.$money . '卢比，手续费：' . $charge . '卢比', $payModel['orderno'], '2');
                $return = '当前代付提交返回状态失败';
                $payData = [
                    'status' => '4',
                    'msg' => $params['msg'],
                    'daifustatus' => '2'
                ];
                $payOrderData = [
                    'status' => '2',
                    'outorderno' => $params['outorderno'],
                    'paytime' => time(),
                    'outdesc' => $params['msg']
                ];
            }
            //代付已驳回返回资金
            if ($params['status'] == '3') {
                $money = $payModel['money'];
                $charge= $payModel['charge'];
                $needMoney=bcadd($money, $charge, 2);
                //更新用户金额
                $userModel = User::getByMerchantId($payModel['merchant_id']);      //用户
                $userModel->setDec('withdrawal', $needMoney);
                //资金变动
                User::money($needMoney, $userModel->id, '代付被驳回返回' . $needMoney.'卢比: '.$money . '卢比，手续费：' . $charge . '卢比', $payModel['orderno'], '2');
                $return = '当前代付提交返回状态驳回';
                $payData = [
                    'status' => '3',
                    'msg' => $params['msg'],
                    'daifustatus' => '4'
                ];
                $payOrderData = [
                    'status' => '2',
                    'outorderno' => $params['outorderno'],
                    'paytime' => time(),
                    'outdesc' => $params['msg']
                ];
            }

            //代付申请中
            if ($params['status'] == '2') {
                $return = '当前代付提交返回状态代付中';
                $payData = [
                    'msg' => $params['msg'],
                    'daifustatus' => "1"
                ];
                $payOrderData = [
                    'status' => '0',
                    'outorderno' => $params['outorderno'],
                    'paytime' => time(),
                    'outdesc' => $params['msg']
                ];
            }
            //开启事务提交
            Db::startTrans();
            try {
                $payModel->save($payData);
                $payOrderModel->save($payOrderData);
                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                return [0, $e->getMessage()];
            }
            //到这里可以通知客户了
            //自动代付为1,有通知地址,状态不为发起'代付状态'可通知
            if ($payModel['style'] == '1' && !empty($payModel['req_info']['notifyUrl']) && $params['status'] != '2') {
                Queue::push('app\common\job\RepayNotify',['pay_id'=>$payModel->id]);
            }
            return [1, $return];
        } else {
            return [0, '该订单已支付成功或已被管理员处理'];
        }

    }


}