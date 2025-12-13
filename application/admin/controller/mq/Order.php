<?php

namespace app\admin\controller\mq;

use app\common\controller\Backend;

/**
 * 卡主订单
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

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\mq\Order;
        $this->view->assign("statusList", $this->model->getStatusList());
        
        // 获取银行下拉列表
        $bankList = \app\admin\model\mq\Bank::column('name', 'id');
        $this->view->assign("bankList", $bankList);
        
        // 获取分类下拉列表
        $categoryList = \app\admin\model\mq\Category::column('name', 'id');
        $this->view->assign("categoryList", $categoryList);
        
        // 获取账户下拉列表
        $accountList = \app\admin\model\mq\Account::column('name', 'id');
        $this->view->assign("accountList", $accountList);
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
                ->with(['category','account','bank'])
                ->where($where)
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->with(['category','account','bank'])
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
    
}
