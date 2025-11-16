<?php

namespace app\admin\controller\api;

use app\common\controller\Backend;

/**
 * 接口上游
 *
 * @icon fa fa-circle-o
 */
class Upstream extends Backend
{

    /**
     * Upstream模型对象
     * @var \app\admin\model\api\Upstream
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\api\Upstream;

    }



    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */

    /**
     * 获取上游参数（AJAX）
     */
    public function get()
    {
        $id = $this->request->request('id');
        if (!$id) {
            $this->error('记录不存在');
        }

        $row = $this->model->find($id);
        if (!$row) {
            $this->error('记录不存在');
        }

        $this->success('获取成功', null, ['params' => $row->params]);
    }

    public function items()
    {
        $list = collection($this->model->all())->toArray();
        return json($list);
    }

}
