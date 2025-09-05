<?php

namespace app\admin\controller\CardOwner;

use app\common\controller\Backend;

/**
 * 银行卡信息表（含代收二维码）
 *
 * @icon fa fa-circle-o
 */
class CardBank extends Backend
{

    /**
     * CardBank模型对象
     * @var \app\admin\model\CardOwner\CardBank
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\CardOwner\CardBank;
        
        // 获取卡主列表，用于下拉选择
        $cardOwnerModel = new \app\admin\model\cardowner\CardOwner;
        $cardOwnerList = $cardOwnerModel->select();
        $this->view->assign("cardOwnerList", $cardOwnerList);
        
        // 获取银行类型列表
        $bankTypeModel = new \app\admin\model\CardOwner\CardBankType;
        $bankTypeList = $bankTypeModel->where('bank_status', 1)->order('bank_sort asc')->select();
        $this->view->assign("bankTypeList", $bankTypeList);
    }
    
    /**
     * 重写index方法，添加银行名称显示
     */
    public function index()
    {
        // 设置过滤方法
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                ->where($where)
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();
            
            $result = array("total" => $total, "rows" => $list);
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
