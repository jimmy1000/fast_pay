<?php

namespace app\admin\controller\finance;

use app\common\controller\Backend;

/**
 * 对账记录
 *
 * @icon fa fa-balance-scale
 */
class Ordercheck extends Backend
{
    /**
     * Order模型对象
     * @var \app\admin\model\order\Order
     */
    protected $model = null;
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
                ->with(['apitype'])
                ->where($where)
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->with(['apitype'])
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            foreach ($list as $k => $v) {
            }

            $result = array("total" => $total, "rows" => $list);

            //统计数据
            //当日总收入
            $allMoney = $this->model
                ->with(['apitype'])
                ->where($where)
                ->order($sort, $order)
                ->sum('total_money');

            //用户的收入
            $haveMoney = $this->model
                ->with(['apitype'])
                ->where($where)
                ->order($sort, $order)
                ->sum('have_money');

            //代理的收入
            $agentMoney = $this->model
                ->with(['apitype'])
                ->where($where)
                ->order($sort, $order)
                ->sum('agent_money');

            //上游的金额
            $upstreamMoney = $this->model
                ->with(['apitype'])
                ->where($where)
                ->order($sort, $order)
                ->sum('upstream_money');

            $result['extend'] = [
                'allMoney' => $allMoney ?: 0,
                'haveMoney' => $haveMoney ?: 0,
                'agentMoney' => $agentMoney ?: 0,
                'upstreamMoney' => $upstreamMoney ?: 0
            ];

            return json($result);
        }

        return $this->view->fetch();
    }
}

