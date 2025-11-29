<?php

namespace app\admin\controller\agent;

use app\common\controller\Backend;

/**
 * 代理商户订单
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
    protected $multiFields = [];
    protected $relationSearch = true;
    protected $modelValidate = true;
    protected $modelSceneValidate = true;

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
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                ->with(['apitype','user'])
                ->where($where)
                ->where('agent_id', '<>', 0)
                ->order($sort, $order)
                ->count();
            $list = $this->model
                ->with(['apitype','user'])
                ->where($where)
                ->where('agent_id', '<>', 0)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();
            foreach ($list as $k => $v) {
            }
            $result = array("total" => $total, "rows" => $list);

            //当天的统计信息
            $todayStatistics = $this->model
                ->with(['user'])
                ->whereTime('order.createtime', 'today')
                ->where('order.status', 'in', '1,2')
                ->field('COALESCE(sum(total_money),0) as `allMoney`,COALESCE(sum(have_money),0) as `haveMoney`,COALESCE(sum(agent_money),0) as `agentMoney`,COALESCE(sum(upstream_money),0) as `upstreamMoney`')
                ->find();
            $todayStatistics = $todayStatistics->toArray();
            $allStatistics = $this->model
                ->with(['user'])
                ->where('order.status', 'in', '1,2')
                ->where('agent_id', '<>', 0)
                ->field('COALESCE(sum(total_money),0) as `allMoney`,COALESCE(sum(have_money),0) as `haveMoney`,COALESCE(sum(agent_money),0) as `agentMoney`,COALESCE(sum(upstream_money),0) as `upstreamMoney`')
                ->find();
            $allStatistics = $allStatistics->toArray();
            //列表金额
            $listMoney = $this->model
                ->with(['user'])
                ->where('agent_id', '<>', 0)
                ->where($where)->sum('total_money');

            $listAgentMoney = $this->model
                ->with(['user'])
                ->where('agent_id', '<>', 0)
                ->where($where)->sum('agent_money');

            $result['extend'] = [
                'todayMoney' => $todayStatistics['allMoney'],
                'todayAgentMoney' => $todayStatistics['agentMoney'],
                'allMoney' => $allStatistics['allMoney'],
                'allAgentMoney' => $allStatistics['agentMoney'],
                'listMoney'=>$listMoney,
                'listAgentMoney'=>$listAgentMoney
            ];


            return json($result);
        }
        return $this->view->fetch();
    }

    public function add()
    {
        $this->error('该功能不存在');
    }

    public function edit($ids = null)
    {
        $this->error('该功能不存在');
    }

    public function multi($ids = "")
    {
        $this->error('该功能不存在');
    }
}

