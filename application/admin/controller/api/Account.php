<?php

namespace app\admin\controller\api;

use app\admin\model\api\Type as ApiType;
use app\common\controller\Backend;
use app\common\model\api\Channel as ApiChannel;
use think\Db;

/**
 * 接口账户
 *
 * @icon fa fa-circle-o
 */
class Account extends Backend
{

    /**
     * ApiAccount模型对象
     * @var \app\admin\model\ApiAccount
     */
    protected $model = null;

    protected $modelValidate = true;

    protected $relationSearch = true;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\api\Account;
        $this->view->assign("ifrepayList", $this->model->getIfrepayList());
        $this->view->assign("ifrechargeList", $this->model->getIfrechargeList());
    }

    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */

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
                ->with(['upstream'])
                ->where($where)
                ->order($sort, $order)
                ->count();
            $list = $this->model
                ->with(['upstream'])
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();
            foreach ($list as $k => $v) {
            }
            $result = array("total" => $total, "rows" => $list);
            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 费率以及通道设置
     */
    public function channel()
    {
        $data = $this->request->only('id');

        //获取接口账户
        $row = $this->model->hidden(['params'])->find($data['id']);

        if (!$row) {
            $this->error('记录不存在！');
        }

        //获取接口类型
        $api_type_list = ApiType::where('status', '1')->select();
        // 兼容结果集为数组或Collection两种情形
        $api_type_ids = array_column(is_array($api_type_list) ? $api_type_list : collection($api_type_list)->toArray(), 'id');

        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $row = $this->request->param('row/a', []);
                $selected_type_list = $row['types'] ?? [];

                //删除未勾选的记录
                $noselected_type_list = array_diff($api_type_ids, $selected_type_list);
                if (count($noselected_type_list) > 0) {
                    ApiChannel::where('api_account_id', $data['id'])
                        ->where('api_type_id', 'in', $noselected_type_list)
                        ->delete();
                }

                //插入数组,更新数组
                $inser_data = [];
                $update_data = [];

                foreach ($selected_type_list as $type_id) {
                    $param = $row[$type_id] ?? [];
                    
                    $model = ApiChannel::where([
                        'api_type_id' => $type_id,
                        'api_account_id' => $data['id']
                    ])->find();

                    if (is_null($model)) {
                        array_push($inser_data, [
                            'api_type_id' => $type_id,
                            'api_account_id' => $data['id'],
                            'upstream_rate' => $param['upstream_rate'] ?? 0,
                            'rate' => $param['rate'] ?? 0,
                            'minmoney' => $param['minmoney'] ?? 0,
                            'maxmoney' => $param['maxmoney'] ?? 0,
                            'daymoney' => $param['daymoney'] ?? 0,
                            'ifjump' => $param['ifjump'] ?? 0,
                            'status' => $param['status'] ?? 0
                        ]);
                    } else {
                        array_push($update_data, [
                            'id' => $model->id,
                            'api_type_id' => $type_id,
                            'api_account_id' => $data['id'],
                            'upstream_rate' => $param['upstream_rate'] ?? 0,
                            'rate' => $param['rate'] ?? 0,
                            'minmoney' => $param['minmoney'] ?? 0,
                            'maxmoney' => $param['maxmoney'] ?? 0,
                            'daymoney' => $param['daymoney'] ?? 0,
                            'ifjump' => $param['ifjump'] ?? 0,
                            'status' => $param['status'] ?? 0
                        ]);
                    }
                }

                //处理数据
                if (count($inser_data) > 0) {
                    ApiChannel::insertAll($inser_data);
                }
                if (count($update_data) > 0) {
                    $model = new ApiChannel();
                    $model->saveAll($update_data);
                }
                
                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            $this->success('设置成功！');
        }

        // 获取已设置的通道列表（按 api_type_id 为键）
        $channel_list = ApiChannel::getChannelByAccount($data['id']);

        $this->assign('channel_list', $channel_list);
        $this->assign('row', $row);
        $this->assign('api_type_list', $api_type_list);
        return $this->view->fetch();
    }

    /**
     * 代付账户列表（用于selectpage）
     */
    public function repay()
    {
        $this->request->filter(['strip_tags', 'htmlspecialchars']);
        
        // 获取selectpage参数
        $word = (array)$this->request->request("q_word/a");
        $page = $this->request->request("pageNumber", 1);
        $pagesize = $this->request->request("pageSize", 10);
        $andor = $this->request->request("andOr", "and", "strtoupper");
        $orderby = (array)$this->request->request("orderBy/a");
        $field = $this->request->request("showField", "name");
        $primarykey = $this->request->request("keyField", "id");
        $primaryvalue = $this->request->request("keyValue");
        $searchfield = (array)$this->request->request("searchField/a", [$field]);
        $custom = (array)$this->request->request("custom/a");
        
        // 构建排序
        $order = [];
        foreach ($orderby as $k => $v) {
            $order[$v[0]] = $v[1];
        }
        $order = $order ?: [$primarykey => 'desc'];
        
        // 构建查询条件
        if ($primaryvalue !== null) {
            $where = [$primarykey => ['in', is_array($primaryvalue) ? $primaryvalue : explode(',', $primaryvalue)]];
        } else {
            $where = function ($query) use ($word, $andor, $searchfield, $custom) {
                $logic = $andor == 'AND' ? '&' : '|';
                $searchfield = is_array($searchfield) ? implode($logic, $searchfield) : $searchfield;
                foreach ($word as $k => $v) {
                    $query->where(str_replace(',', $logic, $searchfield), "like", "%{$v}%");
                }
                if ($custom && is_array($custom)) {
                    foreach ($custom as $k => $v) {
                        if (is_array($v) && 2 == count($v)) {
                            $query->where($k, trim($v[0]), $v[1]);
                        } else {
                            $query->where($k, '=', $v);
                        }
                    }
                }
            };
        }
        
        // 只查询代付账户
        $list = [];
        $total = $this->model->where($where)->where('ifrepay', '1')->count();
        
        if ($total > 0) {
            $datalist = $this->model->where($where)
                ->where('ifrepay', '1')
                ->order($order)
                ->page($page, $pagesize)
                ->field($this->selectpageFields ?: '*')
                ->select();
            
            foreach ($datalist as $index => $item) {
                $list[] = [
                    $primarykey => isset($item[$primarykey]) ? $item[$primarykey] : '',
                    $field      => isset($item[$field]) ? $item[$field] : '',
                ];
            }
        }
        
        return json(['list' => $list, 'total' => $total]);
    }

}
