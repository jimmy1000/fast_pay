<?php

namespace app\admin\controller\finance;

use app\common\controller\Backend;

/**
 * 对账记录
 *
 * @icon fa fa-balance-scale
 */
class Repayordercheck extends Backend{

    /**
     * Order模型对象
     * @var \app\admin\model\repay\Order
     */
    protected $model = null;

    protected $relationSearch = true;
    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\repay\Order();
        $this->view->assign("styleList", $this->model->getStyleList());
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("DaifustatusList", $this->model->getDaifustatusList());
        $this->view->assign("notifyStatusList", $this->model->getNotifyStatusList());

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
            $filter = $this->request->get('filter');
            $filter = json_decode($filter, true); // 将 JSON 解码为数组
            
            // 获取表名，用于避免字段歧义
            $tableName = $this->model->getTable();
            
            $total = $this->model
                ->with(['uporder' => function ($query) use ($filter) {
                    if (isset($filter['api_account_id'])) {
                        $query->where('api_account_id', '=', $filter['api_account_id']);
                    }
                    if (isset($filter['status'])) {
                        $statuses = explode(',', $filter['status']);
                        $query->whereIn('status', $statuses);  // 在 uporder 表中查询 status
                    }
                }])
                ->where(function ($query) use ($filter, $tableName) {
                    // 处理 status 条件
                    if (isset($filter['status'])) {
                        $statuses = explode(',', $filter['status']);
                        $query->whereIn($tableName . '.status', $statuses);  // 在 repay_order 表中查询 status
                    }

                    // 处理 merchant_id 条件
                    if (isset($filter['merchant_id'])) {
                        $query->where($tableName . '.merchant_id', '=', $filter['merchant_id']); // 在 repay_order 表中查询 merchant_id
                    }
                    $query->where($tableName . '.style', '=', "1"); //固定值为代付
                    if (isset($filter['createtime'])) {
                        // 假设前端传入的是 "开始时间 - 结束时间" 的格式
                        $timeRange = explode(' - ', $filter['createtime']);
                        if (count($timeRange) === 2) {
                            $startTime = strtotime($timeRange[0]); // 转换为时间戳
                            $endTime = strtotime($timeRange[1]);   // 转换为时间戳
                            $query->where($tableName . '.createtime', '>=', $startTime)
                                ->where($tableName . '.createtime', '<=', $endTime); // 使用 >= 和 <= 构建时间范围
                        }
                    }

                })
                ->order($sort, $order)
                ->count();
            $list = $this->model
                ->with(['uporder' => function ($query) use ($filter) {
                    if (isset($filter['api_account_id'])) {
                        $query->where('api_account_id', '=', $filter['api_account_id']);
                    }
                    if (isset($filter['status'])) {
                        $statuses = explode(',', $filter['status']);
                        $query->whereIn('status', $statuses);  // 在 uporder 表中查询 status
                    }
                }])
                ->where(function ($query) use ($filter, $tableName) {
                    // 处理 status 条件
                    if (isset($filter['status'])) {
                        $statuses = explode(',', $filter['status']);
                        $query->whereIn($tableName . '.status', $statuses);  // 在 repay_order 表中查询 status
                    }

                    // 处理 merchant_id 条件
                    if (isset($filter['merchant_id'])) {
                        $query->where($tableName . '.merchant_id', '=', $filter['merchant_id']); // 在 repay_order 表中查询 merchant_id
                    }
                    $query->where($tableName . '.style', '=', "1"); //固定值为代付
                    // 处理 createtime 条件
                    if (isset($filter['createtime'])) {
                        // 假设前端传入的是 "开始时间 - 结束时间" 的格式
                        $timeRange = explode(' - ', $filter['createtime']);
                        if (count($timeRange) === 2) {
                            $startTime = strtotime($timeRange[0]); // 转换为时间戳
                            $endTime = strtotime($timeRange[1]);   // 转换为时间戳
                            $query->where($tableName . '.createtime', '>=', $startTime)
                                ->where($tableName . '.createtime', '<=', $endTime); // 使用 >= 和 <= 构建时间范围
                        }
                    }

                })
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();
            foreach ($list as $k => $v) {
            }
            $result = array("total" => $total, "rows" => $list);


            //统计数据

            //当日总收入
            $allMoney = $this->model
                ->with(['uporder' => function ($query) use ($filter) {
                    if (isset($filter['api_account_id'])) {
                        $query->where('api_account_id', '=', $filter['api_account_id']);
                    }
                    if (isset($filter['status'])) {
                        $statuses = explode(',', $filter['status']);
                        $query->whereIn('status', $statuses);  // 在 uporder 表中查询 status
                    }
                }])
                ->where(function ($query) use ($filter, $tableName) {
                    // 处理 status 条件
                    if (isset($filter['status'])) {
                        $statuses = explode(',', $filter['status']);
                        $query->whereIn($tableName . '.status', $statuses);  // 在 repay_order 表中查询 status
                    }
                    $query->where($tableName . '.style', '=', "1"); //固定值为代付
                    // 处理 merchant_id 条件
                    if (isset($filter['merchant_id'])) {
                        $query->where($tableName . '.merchant_id', '=', $filter['merchant_id']); // 在 repay_order 表中查询 merchant_id
                    }

                    // 处理 createtime 条件
                    if (isset($filter['createtime'])) {
                        // 假设前端传入的是 "开始时间 - 结束时间" 的格式
                        $timeRange = explode(' - ', $filter['createtime']);
                        if (count($timeRange) === 2) {
                            $startTime = strtotime($timeRange[0]); // 转换为时间戳
                            $endTime = strtotime($timeRange[1]);   // 转换为时间戳
                            $query->where($tableName . '.createtime', '>=', $startTime)
                                ->where($tableName . '.createtime', '<=', $endTime); // 使用 >= 和 <= 构建时间范围
                        }
                    }
                })
                ->order($sort, $order)
                ->sum('money');

            //平台手续费
            $Charge = $this->model
                ->with(['uporder' => function ($query) use ($filter) {
                    if (isset($filter['api_account_id'])) {
                        $query->where('api_account_id', '=', $filter['api_account_id']);
                    }
                    if (isset($filter['status'])) {
                        $statuses = explode(',', $filter['status']);
                        $query->whereIn('status', $statuses);  // 在 uporder 表中查询 status
                    }
                }])
                ->where(function ($query) use ($filter, $tableName) {
                    // 处理 status 条件
                    if (isset($filter['status'])) {
                        $statuses = explode(',', $filter['status']);
                        $query->whereIn($tableName . '.status', $statuses);  // 在 repay_order 表中查询 status
                    }
                    $query->where($tableName . '.style', '=', "1"); //固定值为代付
                    // 处理 merchant_id 条件
                    if (isset($filter['merchant_id'])) {
                        $query->where($tableName . '.merchant_id', '=', $filter['merchant_id']); // 在 repay_order 表中查询 merchant_id
                    }

                    // 处理 createtime 条件
                    if (isset($filter['createtime'])) {
                        // 假设前端传入的是 "开始时间 - 结束时间" 的格式
                        $timeRange = explode(' - ', $filter['createtime']);
                        if (count($timeRange) === 2) {
                            $startTime = strtotime($timeRange[0]); // 转换为时间戳
                            $endTime = strtotime($timeRange[1]);   // 转换为时间戳
                            $query->where($tableName . '.createtime', '>=', $startTime)
                                ->where($tableName . '.createtime', '<=', $endTime); // 使用 >= 和 <= 构建时间范围
                        }
                    }

                })
                ->order($sort, $order)
                ->sum('charge');

            //代理的收入
            $agentMoney = $this->model
                ->with(['uporder'])
                ->where(function ($query) use ($filter, $tableName) {
                    if (isset($filter['status'])) {
                        $statuses = explode(',', $filter['status']);
                        $query->whereIn($tableName . '.status', $statuses);  // 在 repay_order 表中查询 status
                    }
                })
                ->order($sort, $order)
                ->sum('charge');

            //上游手续费

            $upstreamCharge = $this->model
                ->with(['uporder' => function ($query) use ($filter) {
                    if (isset($filter['api_account_id'])) {
                        $query->where('api_account_id', '=', $filter['api_account_id']);
                    }
                    if (isset($filter['status'])) {
                        $statuses = explode(',', $filter['status']);
                        $query->whereIn('status', $statuses);  // 在 uporder 表中查询 status
                    }
                }])
                ->where(function ($query) use ($filter, $tableName) {
                    // 处理 status 条件
                    if (isset($filter['status'])) {
                        $statuses = explode(',', $filter['status']);
                        $query->whereIn($tableName . '.status', $statuses);  // 在 repay_order 表中查询 status
                    }
                    $query->where($tableName . '.style', '=', "1"); //固定值为代付
                    // 处理 merchant_id 条件
                    if (isset($filter['merchant_id'])) {
                        $query->where($tableName . '.merchant_id', '=', $filter['merchant_id']); // 在 repay_order 表中查询 merchant_id
                    }

                    // 处理 createtime 条件
                    if (isset($filter['createtime'])) {
                        // 假设前端传入的是 "开始时间 - 结束时间" 的格式
                        $timeRange = explode(' - ', $filter['createtime']);
                        if (count($timeRange) === 2) {
                            $startTime = strtotime($timeRange[0]); // 转换为时间戳
                            $endTime = strtotime($timeRange[1]);   // 转换为时间戳
                            $query->where($tableName . '.createtime', '>=', $startTime)
                                ->where($tableName . '.createtime', '<=', $endTime); // 使用 >= 和 <= 构建时间范围
                        }
                    }

                })
                ->order($sort, $order)
                ->sum('upcharge');


            $result['extend'] = [
                'allMoney'=>$allMoney,
                'charge'=>$Charge,
                'agentMoney'=>$agentMoney,
                'upstreamCharge'=>$upstreamCharge
            ];

            return json($result);
        }
        return $this->view->fetch();
    }
}