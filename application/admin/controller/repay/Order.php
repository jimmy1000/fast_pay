<?php

namespace app\admin\controller\repay;

use app\admin\model\repay\Notifylog;
use app\common\model\RepayOrder as RepayOrderModel;
use app\common\controller\Backend;
use app\common\model\User;
use fast\Http;
use think\Db;
use think\Exception;
use think\Queue;

/**
 * 代付订单
 *
 * @icon fa fa-circle-o
 */
class Order extends Backend
{
    /**
     * @var RepayOrderModel
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new RepayOrderModel;
        $this->view->assign('styleList', $this->model->getStyleList());
        $this->view->assign('statusList', $this->model->getStatusList());
        $this->view->assign('daifustatusList', $this->model->getDaifustatusList());
        $this->view->assign('notifyStatusList', $this->model->getNotifyStatusList());
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
            $todayUpCharge    = $this->model->whereTime('createtime', 'today')->where('status', '1')->sum('upcharge') ?: 0;
            $allUpCharge      = $this->model->where('status', '1')->sum('upcharge') ?: 0;
            $allMoney         = $this->model->sum('money') ?: 0;
            $allSuccMoney     = $this->model->where('status', '1')->sum('money') ?: 0;
            $listMoney        = $this->model->where($where)->sum('money') ?: 0;
            $listChargeMoney  = $this->model->where($where)->sum('charge') ?: 0;
            $listUpChargeMoney= $this->model->where($where)->sum('upcharge') ?: 0;

            $result['extend'] = [
                'todayMoney'        => number_format($todayMoney, 2, '.', ''),
                'todaySuccMoney'    => number_format($todaySuccMoney, 2, '.', ''),
                'allMoney'          => number_format($allMoney, 2, '.', ''),
                'allSuccMoney'      => number_format($allSuccMoney, 2, '.', ''),
                'listMoney'         => number_format($listMoney, 2, '.', ''),
                'listChargeMoney'   => number_format($listChargeMoney, 2, '.', ''),
                'listUpChargeMoney' => number_format($listUpChargeMoney, 2, '.', ''),
                'todayCharge'       => number_format($todayCharge, 2, '.', ''),
                'allCharge'         => number_format($allCharge, 2, '.', ''),
                'todayUpCharge'     => number_format($todayUpCharge, 2, '.', ''),
                'allUpCharge'       => number_format($allUpCharge, 2, '.', ''),
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
     * 禁止编辑
     */
    public function edit($ids = null)
    {
        $this->error('该功能不存在');
    }

    /**
     * 禁止删除
     */
    public function del($ids = null)
    {
        $this->error('提款单不可删除');
    }

    /**
     * 批量更新状态（冻结/手动成功/取消）
     */
    public function multi($ids = '')
    {
        $ids = $ids ?: $this->request->param('ids');
        if (!$ids) {
            $this->error(__('Parameter %s can not be empty', 'ids'));
        }

        if (!$this->request->has('params')) {
            $this->error('缺少参数');
        }

        parse_str($this->request->post('params'), $values);
        $code = $values['code'] ?? '';
        unset($values['code']);

        if (!isset($values['status']) || !in_array($values['status'], ['1', '2', '3', '4'])) {
            $this->error('批量操作仅支持冻结/手动成功/取消/失败');
        }

        // 需要谷歌验证的批量操作：冻结/手动成功/取消
        if (in_array($values['status'], ['1', '2', '3'])) {
            if ($code === '') {
                $this->error('请输入谷歌验证码');
            }
            $admin = \app\admin\model\Admin::get($this->auth->id);
            if (!google_verify_code($admin, $code)) {
                $this->error(__('googleMFA error Please try again'));
            }
        }

        $values = array_intersect_key($values, array_flip(is_array($this->multiFields) ? $this->multiFields : explode(',', $this->multiFields)));
        if (!$values && !$this->auth->isSuperAdmin()) {
            $this->error(__('You have no permission'));
        }

        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            $this->model->where($this->dataLimitField, 'in', $adminIds);
        }

        Db::startTrans();
        try {
            $where = [
                'daifustatus' => ['in', '0,2,4'],
            ];
            if ($values['status'] === '2') {
                $where['status'] = ['in', '0,2'];
            } elseif ($values['status'] === '1') {
                $where['status'] = ['in', '0,2'];
            } elseif ($values['status'] === '3') {
                $where['status'] = ['in', '0'];
            }

            $list = $this->model->where($this->model->getPk(), 'in', $ids)->where($where)->select();
            $count = 0;
            foreach ($list as $item) {
                if ($values['status'] === '1') {
                    $values['paytime'] = time();
                    $values['msg'] = $values['msg'] ?? '手动处理成功';
                }
                $count += $item->allowField(true)->isUpdate(true)->save($values);
            }
            Db::commit();

            if ($count > 0) {
                if (in_array($values['status'], ['1', '3'])) {
                    foreach (explode(',', $ids) as $id) {
                        Queue::push('app\common\job\RepayNotify', ['order_id' => $id]);
                    }
                }
                $this->success();
            }
            $this->error(__('No rows were updated'));
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
    }

    /**
     * 批量提交代付
     */
    public function batchhandle()
    {
        $ids = trim($this->request->param('ids', ''));
        if ($ids === '') {
            $this->error('请选择订单');
        }

        $idsArray = array_filter(explode(',', $ids));

        if ($this->request->isAjax()) {
            $params = $this->request->only(['daifuid', 'code']);
            if (empty($params['daifuid'])) {
                $this->error('请选择代付机构');
            }

            $code = $params['code'] ?? '';
            if ($code === '') {
                $this->error('请输入谷歌验证码');
            }
            $admin = \app\admin\model\Admin::get($this->auth->id);
            if (!google_verify_code($admin, $code)) {
                $this->error(__('googleMFA error Please try again'));
            }

            $orders = $this->model
                ->where($this->model->getPk(), 'in', $idsArray)
                ->where('status', '0')
                ->where('daifustatus', 'in', ['0', '2', '4'])
                ->select();

            if (empty($orders)) {
                $this->error(__('No rows were updated'));
            }

            foreach ($orders as $order) {
                RepayOrderModel::dfSubmit($order->id, $params['daifuid']);
            }

            $this->success('批量处理成功', null, ['msg' => '所有订单已提交代付']);
        }

        $orders = $this->model->whereIn('id', $idsArray)->select();
        $daifuid = '';
        if (!empty($orders)) {
            $userModel = User::get(['merchant_id' => $orders[0]['merchant_id']]);
            if ($userModel && $userModel['daifuid'] > 0) {
                $daifuid = $userModel['daifuid'];
            }
        }

        $this->assign(compact('ids', 'daifuid'));
        return $this->fetch('batchhandle');
    }

    /**
     * 单笔提交代付
     */
    public function handle()
    {
        if ($this->request->isAjax()) {
            $params = $this->request->only(['id', 'daifuid', 'code']);
            if (empty($params['daifuid'])) {
                $this->error('请选择代付机构');
            }

            $code = $params['code'] ?? '';
            if ($code === '') {
                $this->error('请输入谷歌验证码');
            }
            $admin = \app\admin\model\Admin::get($this->auth->id);
            if (!google_verify_code($admin, $code)) {
                $this->error(__('googleMFA error Please try again'));
            }

            RepayOrderModel::dfSubmit($params['id'], $params['daifuid']);
            $this->success('处理成功', null, ['msg' => '代付提交成功，等待上游处理']);
        }

        $id = $this->request->param('id/d', 0);
        $payModel = $this->model->find($id);
        if (!$payModel) {
            $this->error('订单不存在');
        }

        $userModel = User::get(['merchant_id' => $payModel['merchant_id']]);
        $daifuid = ($userModel && $userModel['daifuid'] > 0) ? $userModel['daifuid'] : '';

        $this->assign('pay', $payModel->toArray());
        $this->assign('daifuid', $daifuid);

        return $this->fetch();
    }

    /**
     * 单笔取消驳回订单
     */
    public function cancel()
    {
        $id = $this->request->param('id/d', 0);
        $payModel = $this->model->find($id);

        if (!$payModel) {
            $this->error('获取代付订单失败');
        }

        if ($payModel['status'] == '3') {
            $this->error('当前订单已取消');
        }

        if (in_array($payModel['daifustatus'], ['1', '3'])) {
            $this->error('代付提交中或成功后不能取消');
        }

        $userModel = User::get(['merchant_id' => $payModel['merchant_id']]);
        if (!$userModel) {
            $this->error('商户不存在');
        }

        $redislock = redisLocker();
        $resource  = $redislock->lock('repay.' . $userModel['merchant_id'], 3000);
        if (!$resource) {
            $this->error('系统处理订单繁忙，请重试');
        }

        Db::startTrans();
        try {
            $payModel->save([
                'status'      => '3',
                'daifustatus' => '4',
                'msg'         => '订单已驳回取消',
            ]);

            $refundMoney = bcadd($payModel['money'], $payModel['charge'], 2);
            User::money($refundMoney, $userModel['id'], '提现取消返还金额：' . $refundMoney . '越南盾', $payModel['orderno'], '2');
            $userModel->setDec('withdrawal', $refundMoney);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $redislock->unlock(['resource' => 'repay.' . $userModel['merchant_id'], 'token' => $resource['token']]);
            $this->error($e->getMessage());
        }

        $redislock->unlock(['resource' => 'repay.' . $userModel['merchant_id'], 'token' => $resource['token']]);
        $this->success('取消代付订单成功');
    }

    /**
     * 手动通知
     */
    public function notify()
    {
        $data = $this->request->only(['id']);
        $rules = [
            'id|编号' => 'require|number',
        ];

        $result = $this->validate($data, $rules);
        if (true !== $result) {
            $this->error($result);
        }

        $payModel = $this->model->find($data['id']);
        if (!$payModel) {
            $this->error('代付订单不存在');
        }

        if ($payModel['status'] === '0' || $payModel['style'] === '0') {
            $this->error('申请中和后台申请的订单不发送通知');
        }

        $postData = [
            'merId'    => $payModel['merchant_id'],
            'orderOn'  => $payModel['orderno'],
            'sysOrder' => $payModel['orderno'],
            'money'    => $payModel['money'],
            'status'   => $payModel['status'],
            'utr'      => $payModel['utr'],
            'msg'      => $payModel['msg'],
            'charge'   => $payModel['charge'],
        ];

        if (!empty($payModel->req_info['attch'])) {
            $postData['attch'] = $payModel->req_info['attch'];
        }

        $userModel = User::get(['merchant_id' => $payModel['merchant_id']]);
        $postData['sign'] = makeApiSign($postData, $userModel->md5key, config('site.private_key'));

        if ($this->request->isPost()) {
            $notifyUrl = $payModel->req_info['notifyUrl'] ?? '';
            if (empty($notifyUrl)) {
                $this->error('订单缺少通知地址');
            }

            $result = Http::post($notifyUrl, $postData);

            Db::startTrans();
            try {
                Notifylog::log($payModel->id, $notifyUrl, $postData, $result);

                $payModel->notify_status = (trim(strtolower($result)) === 'success') ? '1' : '2';
                $payModel->notify_count  = $payModel->notify_count + 1;
                $payModel->save();
                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }

            if (trim(strtolower($result)) === 'success') {
                $this->success('手动通知成功: success');
            }
            $this->error('手动通知失败:' . $result);
        }

        $this->assign('pay', $payModel);
        $this->assign('post_data', urldecode(http_build_query($postData)));
        return $this->fetch();
    }

    /**
     * 批量通知
     */
    public function batchnotify()
    {
        $ids = $this->request->param('ids', '');
        if ($ids === '') {
            $this->error('请选择订单');
        }

        $idsArray = array_filter(explode(',', $ids));
        $list = $this->model
            ->where($this->model->getPk(), 'in', $idsArray)
            ->whereNotIn('status', ['0', '2'])
            ->whereNotIn('style', ['0'])
            ->select();
        if (empty($list)) {
            $this->error('没有符合条件的订单可以发送通知');
        }

        foreach ($list as $item) {
            Queue::push('app\common\job\RepayNotify', ['order_id' => $item->id]);
        }

        $this->success('批量通知已发送');
    }
}
