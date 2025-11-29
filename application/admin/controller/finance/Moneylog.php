<?php

namespace app\admin\controller\finance;

use app\common\controller\Backend;

/**
 * 会员余额变动管理
 *
 * @icon fa fa-circle-o
 */
class Moneylog extends Backend
{

    /**
     * Moneylog模型对象
     * @var \app\admin\model\finance\Moneylog
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\finance\Moneylog;

    }



    /**
     * 禁止添加
     */
    public function add()
    {
        $this->error('该功能不存在');
    }

    /**
     * 禁止编辑
     */
    public function edit($ids = null)
    {
        $this->error('该功能不存在');
    }

    /**
     * 禁止删除
     */
    public function del($ids = null)
    {
        $this->error('该功能不存在');
    }

    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */


}
