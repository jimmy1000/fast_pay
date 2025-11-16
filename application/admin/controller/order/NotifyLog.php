<?php

namespace app\admin\controller\order;

use app\common\controller\Backend;

/**
 * 通知记录
 *
 * @icon fa fa-circle-o
 */
class NotifyLog extends Backend
{

    /**
     * NotifyLog模型对象
     * @var \app\admin\model\order\NotifyLog
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
        $this->model = new \app\admin\model\order\NotifyLog;
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
                ->with(['order'])
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);
            
            foreach ($list as $row) {
                // 暴露关联模型的字段
                if ($row->getRelation('order')) {
                    $row->getRelation('order')->visible(['id', 'merchant_id', 'orderno']);
                }
            }
            
            $result = array("total" => $list->total(), "rows" => $list->items());
            return json($result);
        }
        return $this->view->fetch();
    }
    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */


}

