<?php
/**
 * @author : jimmy 商户后台代收管理接口
 * @date : 2025-12-11
*/
namespace app\api\controller;

use app\common\controller\Api;

class Collection extends Api
{

    protected $noNeedRight = ['index', 'orderLog'];



    public function _initialize()
    {
        parent::_initialize();
    }


    public function index()
    {
        $data = $this->request->only(['orderno', 'status', 'api_type_id', 'createtime', 'paytime']);

        $rules = [
            'orderno|订单号' => 'alphaDash',
            'status|订单状态' => 'in:0,1',
            'api_type_id|订单类型' => 'integer',
            'createtime|创建时间' => 'array',
            'paytime|支付时间' => 'array'
        ];

        $result = $this->validate($data, $rules);
        if ($result !== true) {
            $this->error($result);
        }

        $merchantId = $this->auth->merchant_id;

        // 构建查询条件（with() 是预加载关联，主查询仍然是单表查询，不需要表别名）
        $where = [
            'merchant_id' => $merchantId
        ];

        // 筛选订单号
        if (!empty($data['orderno'])) {
            $where['orderno'] = ['like', '%' . $data['orderno'] . '%'];
        }
        
        // 订单状态
        if (isset($data['status']) && $data['status'] !== '') {
            $where['status'] = $data['status'];
        }
        
        // 订单类型
        if (!empty($data['api_type_id'])) {
            $where['api_type_id'] = $data['api_type_id'];
        }

        // 处理创建时间范围
        if (isset($data['createtime']) && is_array($data['createtime']) && count($data['createtime']) == 2) {
            $timeRange = $this->parseTimeRange($data['createtime']);
            $where['createtime'] = ['between time', $timeRange];
        }

        // 处理支付时间范围
        if (isset($data['paytime']) && is_array($data['paytime']) && count($data['paytime']) == 2) {
            $timeRange = $this->parseTimeRange($data['paytime']);
            $where['paytime'] = ['between time', $timeRange];
        }

        // 分页参数
        $orderField = $this->request->param('orderField', 'id');
        $sort = 'DESC';
        $page = $this->request->param('page/d', 1);
        $pageLimit = 10;
        $offset = ($page - 1) * $pageLimit;

        // 获取表名，用于明确指定字段所属表，避免字段歧义
        // 当使用 with() 预加载时，虽然主查询是单表，但在某些情况下可能生成 JOIN
        // 如果 order 表和关联表（如 api_type）都有相同字段名（如 status），会出现歧义
        $tableName = \app\common\model\Order::getTable();
        
        // 为可能出现歧义的字段添加表名前缀（特别是 status 字段，因为 api_type 表也有 status）
        $whereWithTable = [];
        foreach ($where as $key => $value) {
            // status 字段需要明确指定表名，避免与关联表的 status 字段冲突
            if ($key === 'status') {
                $whereWithTable[$tableName . '.' . $key] = $value;
            } else {
                $whereWithTable[$key] = $value;
            }
        }

        // 获取总数（count 查询不需要 with，可以提升性能）
        $total = \app\common\model\Order::where($whereWithTable)->count();

        // 查询订单列表（使用关联查询预加载 apitype）
        $list = \app\common\model\Order::with(['apitype' => function ($query) {
            $query->withField('name');
        }])
            ->where($whereWithTable)
            ->order($orderField, $sort)
            ->limit($offset, $pageLimit)
            ->select();

        // 设置可见字段（直接返回时间戳，不进行格式转换）
        $visibleFields = [
            'orderno', 'sys_orderno', 'total_money', 'have_money', 
            'style_text', 'status', 'status_text', 'notify_status_text', 
            'createtime', 'paytime', 'apitype'
        ];
        foreach ($list as $v) {
            $v->visible($visibleFields);
        }
        $list = collection($list)->toArray();

        // 统计信息（使用相同的查询条件）
        $extend = $this->calculateStatistics($where, $merchantId);

        $this->success('Data retrieved successfully', [
            'total' => $total,
            'list' => $list,
            'limit' => $pageLimit,
            'extend' => $extend
        ]);
    }

    /**
     * 解析时间范围（自动识别毫秒或秒级时间戳）
     * @param array $timeRange 时间范围数组 [开始时间, 结束时间]
     * @return array 转换后的时间范围（秒级）
     */
    private function parseTimeRange($timeRange)
    {
        $startTime = $timeRange[0];
        $endTime = $timeRange[1];
        
        // 判断是否为毫秒级时间戳（13位数字）
        // 如果时间戳大于 10 位数字，则认为是毫秒级，需要除以1000
        if (strlen((string)$startTime) > 10) {
            $startTime = intval($startTime / 1000);
        }
        if (strlen((string)$endTime) > 10) {
            $endTime = intval($endTime / 1000);
        }
        
        return [$startTime, $endTime];
    }

    /**
     * 计算统计信息
     * @param array $baseWhere 基础查询条件
     * @param int $merchantId 商户ID
     * @return array 统计信息
     */
    private function calculateStatistics($baseWhere, $merchantId)
    {
        $extend = [];

        // 今日收益（今日创建且状态=1的订单的have_money总和）
        $extend['today'] = \app\common\model\Order::whereTime('createtime', 'today')
            ->where('status', '1')
            ->where('merchant_id', $merchantId)
            ->sum('have_money') ?: 0;

        // 昨日收益
        $extend['yesterday'] = \app\common\model\Order::whereTime('createtime', 'yesterday')
            ->where('status', '1')
            ->where('merchant_id', $merchantId)
            ->sum('have_money') ?: 0;

        // 当前列表总金额（符合筛选条件的所有订单）
        $extend['all'] = \app\common\model\Order::where($baseWhere)->sum('total_money') ?: 0;

        // 成功订单总金额（符合筛选条件且状态=1的订单）
        $successWhere = array_merge($baseWhere, ['status' => '1']);
        $extend['success'] = \app\common\model\Order::where($successWhere)->sum('total_money') ?: 0;

        // 应结算金额（符合筛选条件且状态=1的订单的have_money总和）
        $extend['have'] = \app\common\model\Order::where($successWhere)->sum('have_money') ?: 0;

        // 当日成功率
        $todayCount = \app\common\model\Order::whereTime('createtime', 'today')
            ->where('merchant_id', $merchantId)
            ->count();
        
        $todaySuccessCount = \app\common\model\Order::whereTime('createtime', 'today')
            ->where('status', '1')
            ->where('merchant_id', $merchantId)
            ->count();

        // 如果没有订单，成功率返回0，而不是100%
        if ($todayCount == 0) {
            $extend['successRate'] = 0;
        } else {
            $extend['successRate'] = number_format($todaySuccessCount / $todayCount * 100, 2);
        }

        return $extend;
    }

    /**
     * 获取订单日志
     * 
     * @ApiMethod (GET)
     * @ApiParams (name="orderno", type="string", required=false, description="订单号")
     * @ApiParams (name="status", type="integer", required=false, description="状态 0=失败 1=成功")
     * @ApiParams (name="createtime", type="array", required=false, description="创建时间范围")
     * @ApiParams (name="page", type="integer", required=false, description="页码，默认1")
     * @ApiParams (name="orderField", type="string", required=false, description="排序字段，默认id")
     */
    public function orderLog()
    {
        $data = $this->request->only(['orderno', 'status', 'createtime']);

        $rules = [
            'orderno|订单号' => 'alphaDash',
            'status|状态' => 'in:0,1',
            'createtime|创建时间' => 'array',
        ];

        $result = $this->validate($data, $rules);
        if ($result !== true) {
            $this->error($result);
        }

        $merchantId = $this->auth->merchant_id;

        // 构建查询条件
        $where = [
            'merchant_id' => $merchantId
        ];

        // 筛选订单号
        if (!empty($data['orderno'])) {
            $where['orderno'] = ['like', '%' . $data['orderno'] . '%'];
        }

        // 筛选状态
        if (isset($data['status']) && $data['status'] !== '') {
            $where['status'] = $data['status'];
        }

        // 处理创建时间范围
        if (isset($data['createtime']) && is_array($data['createtime']) && count($data['createtime']) == 2) {
            $timeRange = $this->parseTimeRange($data['createtime']);
            $where['createtime'] = ['between time', $timeRange];
        }

        // 分页参数
        $orderField = $this->request->param('orderField', 'id');
        $sort = 'DESC';
        $page = $this->request->param('page/d', 1);
        $pageLimit = 10;
        $offset = ($page - 1) * $pageLimit;

        // 获取总数
        $total = \app\common\model\ApiLog::where($where)->count();

        // 获取列表数据
        $list = \app\common\model\ApiLog::where($where)
            ->order($orderField, $sort)
            ->limit($offset, $pageLimit)
            ->select();

        // 设置可见字段（content 会自动反序列化，createtime 返回时间戳）
        $visibleFields = [
            'id', 'orderno', 'total_money', 'channel', 
            'content', 'result', 'status', 'status_text', 
            'ip', 'createtime'
        ];
        foreach ($list as $v) {
            $v->visible($visibleFields);
        }
        $list = collection($list)->toArray();

        $this->success('Data retrieved successfully', [
            'total' => $total,
            'list' => $list,
            'limit' => $pageLimit
        ]);
    }
}