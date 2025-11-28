<?php

namespace app\admin\controller\repay;

use app\common\controller\Backend;
use app\common\model\User;
use think\Db;
use think\Exception;

/**
 * 结算账单
 *
 * @icon fa fa-circle-o
 */
class Settle extends Backend
{

    /**
     * Settle模型对象
     * @var \app\admin\model\repay\Settle
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\repay\Settle;
        $this->view->assign("styleList", $this->model->getStyleList());
        $this->view->assign("applyStyleList", $this->model->getApplyStyleList());
        $this->view->assign("caraddresstypeList", $this->model->getCaraddresstypeList());
        $this->view->assign("statusList", $this->model->getStatusList());
    }

    /**
     * 查看
     */
    public function index()
    {
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }

            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $total = $this->model
                ->where($where)
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            $result = [
                'total' => $total,
                'rows'  => $list,
            ];

            // 统计信息
            $todayMoney       = $this->model->whereTime('createtime', 'today')->sum('money') ?: 0;
            $todaySuccMoney   = $this->model->whereTime('createtime', 'today')->where('status', '1')->sum('money') ?: 0;
            $todayCharge      = $this->model->whereTime('createtime', 'today')->where('status', '1')->sum('charge') ?: 0;
            $allCharge        = $this->model->where('status', '1')->sum('charge') ?: 0;
            $allMoney         = $this->model->sum('money') ?: 0;
            $allSuccMoney     = $this->model->where('status', '1')->sum('money') ?: 0;
            $listMoney        = $this->model->where($where)->sum('money') ?: 0;
            $listChargeMoney  = $this->model->where($where)->sum('charge') ?: 0;

            $result['extend'] = [
                'todayMoney'        => number_format($todayMoney, 2, '.', ''),
                'todaySuccMoney'    => number_format($todaySuccMoney, 2, '.', ''),
                'allMoney'          => number_format($allMoney, 2, '.', ''),
                'allSuccMoney'      => number_format($allSuccMoney, 2, '.', ''),
                'listMoney'         => number_format($listMoney, 2, '.', ''),
                'listChargeMoney'   => number_format($listChargeMoney, 2, '.', ''),
                'todayCharge'       => number_format($todayCharge, 2, '.', ''),
                'allCharge'         => number_format($allCharge, 2, '.', ''),
            ];

            return json($result);
        }

        return $this->view->fetch();
    }

    /**
     * 禁止新增
     */
    public function add()
    {
        $this->error('该功能不存在');
    }
    /**
     * 禁止删除
     */
    public function del($ids = null)
    {
        $this->error('结算单不可删除');
    }

    /**
     * 手动成功
     */
    public function manualSuccess()
    {
        $id = $this->request->param('id/d', 0);
        $code = $this->request->param('code', '');

        if ($code === '') {
            $this->error('请输入谷歌验证码');
        }

        $admin = \app\admin\model\Admin::get($this->auth->id);
        if (!google_verify_code($admin, $code)) {
            $this->error(__('googleMFA error Please try again'));
        }

        $settleModel = $this->model->find($id);
        if (!$settleModel) {
            $this->error('结算单不存在');
        }

        if ($settleModel['status'] == '1') {
            $this->error('该结算单已支付');
        }

        if (!in_array($settleModel['status'], ['0', '3'])) {
            $this->error('只有审核中或打款中的结算单可以手动成功');
        }

        Db::startTrans();
        try {
            $settleModel->save([
                'status'   => '1',
                'paytime'  => time(),
                'msg'      => '手动处理成功',
            ]);
            Db::commit();
            $this->success('手动成功处理完成');
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
    }

    /**
     * 驳回结算
     */
    public function cancel()
    {
        $id = $this->request->param('id/d', 0);
        $code = $this->request->param('code', '');

        if ($code === '') {
            $this->error('请输入谷歌验证码');
        }

        $admin = \app\admin\model\Admin::get($this->auth->id);
        if (!google_verify_code($admin, $code)) {
            $this->error(__('googleMFA error Please try again'));
        }

        $settleModel = $this->model->find($id);
        if (!$settleModel) {
            $this->error('结算单不存在');
        }

        if ($settleModel['status'] == '2') {
            $this->error('该结算单已取消');
        }

        if ($settleModel['status'] == '1') {
            $this->error('已支付的结算单不能驳回');
        }

        if (!in_array($settleModel['status'], ['0', '3'])) {
            $this->error('只有审核中或打款中的结算单可以驳回');
        }

        $userModel = User::get(['merchant_id' => $settleModel['merchant_id']]);
        if (!$userModel) {
            $this->error('商户不存在');
        }

        $redislock = redisLocker();
        $resource  = $redislock->lock('settle.' . $userModel['merchant_id'], 3000);
        if (!$resource) {
            $this->error('系统处理订单繁忙，请重试');
        }

        Db::startTrans();
        try {
            $settleModel->save([
                'status' => '2',
                'msg'    => '结算已驳回取消',
            ]);

            // 返还金额给商户
            $refundMoney = bcadd($settleModel['money'], $settleModel['charge'], 2);
            User::money($refundMoney, $userModel['id'], '结算驳回返还金额：' . $refundMoney . '越南盾', $settleModel['orderno'], '2');
            
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $redislock->unlock(['resource' => 'settle.' . $userModel['merchant_id'], 'token' => $resource['token']]);
            $this->error($e->getMessage());
        }

        $redislock->unlock(['resource' => 'settle.' . $userModel['merchant_id'], 'token' => $resource['token']]);
        $this->success('驳回结算成功');
    }
}
