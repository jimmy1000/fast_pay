<?php

namespace app\admin\controller\order;

use app\admin\model\order\NotifyLog;
use app\common\controller\Backend;
use fast\Http;
use think\Db;
use think\Exception;

/**
 * 订单管理
 *
 * @icon fa fa-circle-o
 */
class Order extends Backend
{

    /**
     * Order模型对象
     * @var \app\admin\model\order\Order
     */
    protected $model = null;
    
    /**
     * 是否关联查询
     * @var bool
     */
    protected $relationSearch = true;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\order\Order;
        $this->view->assign("styleList", $this->model->getStyleList());
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("notifyStatusList", $this->model->getNotifyStatusList());
        $this->view->assign("repairList", $this->model->getRepairList());
    }

    /**
     * 查看
     */
    public function index()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            
            $list = $this->model
                ->with(['account', 'apitype', 'upstream'])
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);
            
            // 收集所有的 repair_admin_id
            $adminIds = [];
            foreach ($list as $row) {
                if ($row->repair_admin_id) {
                    $adminIds[] = $row->repair_admin_id;
                }
            }
            
            // 批量查询管理员名称
            $adminNames = [];
            if (!empty($adminIds)) {
                $adminIds = array_unique($adminIds);
                $admins = \app\admin\model\Admin::where('id', 'in', $adminIds)->field('id,username')->select();
                foreach ($admins as $admin) {
                    $adminNames[$admin->id] = $admin->username;
                }
            }
            
            foreach ($list as $row) {
                // 暴露关联模型的 name 字段
                if ($row->getRelation('account')) {
                    $row->getRelation('account')->visible(['name']);
                }
                if ($row->getRelation('apitype')) {
                    $row->getRelation('apitype')->visible(['name']);
                }
                if ($row->getRelation('upstream')) {
                    $row->getRelation('upstream')->visible(['name']);
                }
                
                // 添加管理员名称
                if ($row->repair_admin_id && isset($adminNames[$row->repair_admin_id])) {
                    $row->repair_admin_name = $adminNames[$row->repair_admin_id];
                } else {
                    $row->repair_admin_name = '';
                }
            }
            
            // 当天的统计信息（status=1,2 表示成功订单和扣量订单）
            $todayStatistics = $this->model
                ->whereTime('createtime', 'today')
                ->where('status', 'in', '1,2')
                ->field('COALESCE(sum(total_money),0) as `allMoney`,COALESCE(sum(have_money),0) as `haveMoney`,COALESCE(sum(agent_money),0) as `agentMoney`,COALESCE(sum(upstream_money),0) as `upstreamMoney`')
                ->find();
            $todayStatistics = $todayStatistics->toArray();
            
            // 平台总统计数据（所有订单，status=1,2）
            $allStatistics = $this->model
                ->where('status', 'in', '1,2')
                ->field('COALESCE(sum(total_money),0) as `allMoney`,COALESCE(sum(have_money),0) as `haveMoney`,COALESCE(sum(agent_money),0) as `agentMoney`,COALESCE(sum(upstream_money),0) as `upstreamMoney`')
                ->find();
            $allStatistics = $allStatistics->toArray();
            
            // 列表金额（根据当前搜索条件）
            $listMoney = $this->model
                ->with(['apitype'])
                ->where($where)->sum('total_money');
            
            $listHaveMoney = $this->model
                ->with(['apitype'])
                ->where($where)->sum('have_money');
            
            $result = array("total" => $list->total(), "rows" => $list->items());
            $result['extend'] = [
                'todayMoney' => $todayStatistics['allMoney'],
                'todayHaveMoney' => $todayStatistics['haveMoney'],
                'todayAgentMoney' => $todayStatistics['agentMoney'],
                'todayUpstreamMoney' => $todayStatistics['upstreamMoney'],
                'todayExpenseMoney' => bcadd(bcadd($todayStatistics['upstreamMoney'], $todayStatistics['agentMoney'], 2), $todayStatistics['haveMoney'], 2),
                'allMoney' => $allStatistics['allMoney'],
                'allExpenseMoney' => bcadd(bcadd($allStatistics['haveMoney'], $allStatistics['agentMoney'], 2), $allStatistics['upstreamMoney'], 2),
                'listMoney' => $listMoney,
                'listHaveMoney' => $listHaveMoney
            ];
            
            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 首页订单统计
     */
    public function chart()
    {
        $interval = 5; // 间隔单位分钟
        $list = 5;     // 五条记录
        
        $mins = [];
        $now = time();
        $minute = date('i', $now);
        
        $mod = $minute % $interval;
        if ($mod != 0) {
            $now = $now - $mod * 60;
        }
        
        $allList = [];
        $succList = [];
        $succRateList = [];
        $moneyList = [];
        
        for ($i = 1; $i <= $list; $i++) {
            $end = $now - ($interval * ($list - $i) * 60);
            $endStr = date('H:i', $end);
            $start = $end - ($interval * 60);
            $mins[] = $endStr;
            
            // 所有的订单数量
            $allList[$endStr] = $this->model->where([
                'createtime' => ['between', [$start, $end]]
            ])->count();
            
            // 成功订单数
            $succList[$endStr] = $this->model->where([
                'createtime' => ['between', [$start, $end]],
                'status' => ['in', '1,2']
            ])->count();
            
            // 实时成功率
            if ($allList[$endStr] == 0 || $succList[$endStr] == 0) {
                $succRateList[$endStr] = 0;
            } else {
                $succRateList[$endStr] = number_format($succList[$endStr] / $allList[$endStr] * 100, 2);
            }
            
            // 实时订单金额
            $moneyList[$endStr] = $this->model->where([
                'createtime' => ['between', [$start, $end]],
                'status' => ['in', '1,2']
            ])->sum('total_money');
        }
        
        $result = [
            'allList' => array_values($allList),
            'succList' => array_values($succList),
            'succRateList' => array_values($succRateList),
            'moneyList' => array_values($moneyList),
            'mins' => $mins
        ];
        
        $this->success('获取成功。', null, $result);
    }

    /**
     * 手动通知
     */
    public function notify()
    {
        $id = $this->request->param('id/d', 0);
        if ($id <= 0) {
            $id = $this->request->param('ids/d', 0);
        }
        $data = ['id' => $id];
        $rules = [
            'id|编号' => 'require|number'
        ];
        $result = $this->validate($data, $rules);
        if (true !== $result) {
            $this->error($result);
        }

        $orderModel = $this->model->find($data['id']);
        if (!$orderModel) {
            $this->error('订单不存在');
        }
        if ((string)$orderModel->style === '1') {
            $this->error('充值订单不发通知');
        }

        $postData = [
            'merId'       => $orderModel->merchant_id,
            'orderId'     => $orderModel->orderno,
            'sysOrderId'  => $orderModel->sys_orderno,
            'productInfo' => $orderModel->productInfo,
            'haveMoney'   => $orderModel->have_money,
            'orderAmt'    => $orderModel->total_money,
            'status'      => $orderModel->status,
            'utr'         => $orderModel->utr,
        ];
        // req_info 可能是数组也可能是 URL 查询字符串，需要统一解析成数组
        $reqInfo = $orderModel->req_info;
        if (is_string($reqInfo)) {
            $tmp = [];
            parse_str($reqInfo, $tmp);
            $reqInfo = $tmp;
        }
        if (!empty($reqInfo['attch'])) {
            $postData['attch'] = $reqInfo['attch'];
        }

        // 通过模型关联获取商户信息
        $userModel = $orderModel->user;
        if (!$userModel) {
            $this->error('商户信息不存在');
        }
        $postData['sign'] = makeApiSign($postData, $userModel->md5key ?? '', config('site.private_key'));

        if ($this->request->isPost()) {
            $notifyUrl = $reqInfo['notifyUrl'] ?? '';
            if (!$notifyUrl) {
                $this->error('通知地址不存在');
            }
            $notifyResult = Http::post($notifyUrl, $postData);
            Db::startTrans();
            try {
                NotifyLog::log($orderModel->id, $notifyUrl, $postData, $notifyResult);
                $orderModel->notify_count = ($orderModel->notify_count ?? 0) + 1;
                $orderModel->notify_status = $notifyResult === 'success' ? '1' : '2';
                $orderModel->save();
                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if ($notifyResult === 'success') {
                $this->success('手动通知成功:' . $notifyResult);
            }
            $this->error('手动通知失败:' . $notifyResult);
        }
        $this->assign('order', $orderModel);
        $this->assign('post_data', urldecode(http_build_query($postData)));
        return $this->view->fetch();
    }
    /**
     * 手动补单
     * @return mixed
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function repair()
    {
        $id = $this->request->param('id/d', 0);
        if ($id <= 0) {
            $id = $this->request->param('ids/d', 0);
        }
        $data = ['id' => $id];
        $rules = [
            'id|编号' => 'require|number'
        ];

        $result = $this->validate($data, $rules);
        if (true !== $result) {
            $this->error($result);
        }

        $orderModel = $this->model->find($data['id']);
        if (!$orderModel) {
            $this->error('订单不存在');
        }

        if ((string)$orderModel->status !== '0') {
            $this->error('补单失败,该订单已成功！');
        }

        if ($this->request->isPost()) {
            // 验证 Google MFA（替代安全码验证）
            $code = $this->request->post('code', '');
            if (empty($code)) {
                $this->error('请输入谷歌验证码');
            }
            $admin = \app\admin\model\Admin::get($this->auth->id);
            if (!google_verify_code($admin, $code)) {
                $this->error(__('googleMFA error Please try again'));
            }

            // 同一时刻 同一用户只能处理一个
            $redislock = redisLocker();
            $resource = $redislock->lock('pay.' . $orderModel->merchant_id, 3000);   // 单位毫秒

            if ($resource) {
                try {
                    // 更新订单状态
                    $params = [
                        'orderno' => $orderModel->sys_orderno,    // 系统订单号
                        'up_orderno' => 'EP' . $orderModel->sys_orderno,   // 上游单号
                        'amount' => $orderModel->total_money       // 金额
                    ];
                    $result = \app\admin\model\order\Order::orderFinish($params);
                } catch (\Exception $e) {
                    $redislock->unlock(['resource' => 'pay.' . $orderModel->merchant_id, 'token' => $resource['token']]);
                    $this->error($e->getMessage());
                } finally {
                    $redislock->unlock(['resource' => 'pay.' . $orderModel->merchant_id, 'token' => $resource['token']]);
                }
            } else {
                $this->error('系统处理订单繁忙，请重试');
            }

            if ($result[0] == 1) {
                $this->success('补单成功！');
            }
            $this->error($result[1]);
        }

        $this->assign('order', $orderModel);
        return $this->view->fetch();
    }

    /**
     * .手动退单
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function chargeback()
    {


        $data = $this->request->only('id');
        $rules = [
            'id|编号' => 'require|number'
        ];

        $result = $this->validate($data, $rules);
        if (true !== $result) {
            $this->error($result);
        }

        $orderModel = $this->model->find($data['id']);

        if ($orderModel['status'] == '0') {
            $this->error('退单失败,该订单未支付！');
        }

        //获取用户锁
        $redislock = redisLocker();
        $resource = $redislock->lock('pay.' . $orderModel['merchant_id'], 3000);   //单位毫秒
        if ($resource) {
            try {
                \app\admin\model\order\Order::chargeback($orderModel->id);

                $redislock->unlock(['resource' => 'pay.' . $orderModel['merchant_id'], 'token' => $resource['token']]);

            } catch (\Exception $e) {

                $redislock->unlock(['resource' => 'pay.' . $orderModel['merchant_id'], 'token' => $resource['token']]);

                $this->error($e->getMessage());
            }
        } else {
            $this->error('系统处理订单繁忙，请重试');
        }

        $this->success('退单成功');

    }
}
