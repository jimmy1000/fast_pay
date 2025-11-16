<?php

namespace app\admin\controller\user;

use app\common\controller\Backend;

/**
 * 用户认证管理
 *
 * @icon fa fa-circle-o
 */
class Auth extends Backend
{

    /**
     * Auth模型对象
     * @var \app\admin\model\user\Auth
     */
    protected $model = null;

    /**
     * 开启关联搜索
     * @var bool
     */
    protected $relationSearch = true;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\user\Auth;
        $this->view->assign("statusList", $this->model->getStatusList());
    }

    public function index()
    {
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            [$where, $sort, $order, $offset, $limit] = $this->buildparams();
            $list = $this->model
                ->with(['user'])
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);

            foreach ($list as $row) {
                $row->getRelation('user')->visible(['merchant_id', 'username', 'mobile', 'email']);
            }

            $result = ['total' => $list->total(), 'rows' => $list->items()];
            return json($result);
        }
        return $this->view->fetch();
    }
}
