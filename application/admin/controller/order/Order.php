<?php

namespace app\admin\controller\order;

use app\common\controller\Backend;

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

}
