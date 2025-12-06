<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\model\User;
use think\Queue;

class Webhooks extends Api
{
    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';

    use \app\api\library\traits\Api;
    /**
     * tg钩子webhooks
     */
    //风控接受和驳回
    public function riskhook(){
        $result = $this->request->param();

        // 校验
        if (empty($result['orderno']) || empty($result['status'])) {
            return json([
                'status' => false,
                'msg'    => '缺少参数'
            ]);
        }

        // 查看订单号是否存在
        $payModel = \app\common\model\RepayOrder::get([
            'orderno'=>$result['orderno'],
        ]);

        if (!$payModel) {
            return json([
                'status' => false,
                'msg'    => '订单不存在'
            ]);
        }

        $userModel = User::getByMerchantId($payModel['merchant_id']);
        // ✅ 接受逻辑
        if ($result['status'] == 'accept'){
            $res = \app\common\model\RepayOrder::dfSubmit($payModel->id, $userModel['daifuid']);
            if ($res) {
                return json([
                    'status' => true,
                    'msg'    => '订单已接受'
                ]);
            } else {
                return json([
                    'status' => false,
                    'msg'    => '代付提交失败'
                ]);
            }
        }

        // ❌ 驳回逻辑
        $money = $payModel['money'];
        $charge= $payModel['charge'];
        $needMoney=bcadd($money, $charge, 2);

        // 更新用户金额
        $userModel->setDec('withdrawal', $needMoney);

        // 资金变动
        User::money($needMoney, $userModel->id, '代付被驳回返回' . $needMoney.'越南盾: '.$money . '越南盾，手续费：' . $charge . '越南盾', $payModel['orderno'], '2');

        // 更新订单状态
        $payData = [
            'status' => '3',
            'msg' => '风控驳回!',
            'daifustatus' => '4'
        ];
        $payModel->save($payData);

        Queue::push('app\common\job\RiskNotify',['pay_id'=>$payModel->id]);

        return json([
            'status' => true,
            'msg'    => '订单已驳回'
        ]);
    }

}