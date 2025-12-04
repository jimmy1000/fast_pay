<?php

namespace app\admin\controller\mq;

use addons\mq\model\MqOrder;
use app\common\controller\Backend;

/**
 * 收款记录
 *
 * @icon fa fa-circle-o
 */
class Order extends Backend
{

    /**
     * Order模型对象
     * @var \app\admin\model\mq\Order
     */
    protected $model = null;

    protected $relationSearch = true;


    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\mq\Order;
        $this->view->assign("statusList", $this->model->getStatusList());
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

        $config = get_addon_config('mq');

        $time = time() - intval($config['orderValidity']) * 60;

        $this->model->save([
            'status' => '2'
        ], [
            'status' => '0',
            'createtime' => ['LT', $time],
        ]);


        //设置过滤方法
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                ->with('account')
                ->where($where)
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->with('account')
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            $list = collection($list)->toArray();
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * @internal
     */
    public function edit($ids = "")
    {
        $this->error('禁止修改');
    }

    /**
     * @internal
     */
    public function add()
    {
        $this->error('禁止修改');
    }

    /**
     * @internal
     */
    public function del($ids="")
    {
        $this->error('禁止删除');
    }


}
