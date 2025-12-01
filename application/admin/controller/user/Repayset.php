<?php

namespace app\admin\controller\user;

use app\admin\model\ApiAccount;
use app\common\controller\Backend;

/**
 * 会员代付设置（批量设置代付账户及费率）
 *
 * @icon fa fa-circle-o
 */
class Repayset extends Backend
{
    /**
     * 用户模型对象
     * @var \app\admin\model\User
     */
    protected $model = null;

    protected $multiFields = [];
    protected $relationSearch = true;
    protected $modelValidate = true;
    protected $modelSceneValidate = true;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\User;
    }

    /**
     * 会员列表
     */
    public function index()
    {
        // 设置过滤方法
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            // 如果发送的来源是 Selectpage，则转发到 Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }

            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $total = $this->model
                ->with(['account'])
                ->where($where)
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->with(['account'])
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            foreach ($list as $k => $v) {
                // 这里按老逻辑不做额外处理
            }

            $result = array("total" => $total, "rows" => $list);
            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 批量设置代付参数
     */
    public function batchassign()
    {
        $ids = $this->request->param("ids");
        if ($this->request->isAjax()) {
            // Ajax 提交前先校验谷歌验证码
            $code = $this->request->param('code', '');
            if ($code === '') {
                $this->error('请输入谷歌验证码');
            }
            $adminModel = \app\admin\model\Admin::get($this->auth->id);
            if (!$adminModel || !google_verify_code($adminModel, $code)) {
                $this->error(__('googleMFA error Please try again'));
            }

            if (!$ids) {
                $this->error("参数错误");
            }

            $ids = explode(",", $ids);

            // 允许更新的字段
            $fields = ['daifuid', 'ifdaifuauto', 'payrate_percent', 'payrate_each'];
            $update = [];

            foreach ($fields as $field) {
                $value = $this->request->post($field, '');
                // 只处理非空值
                if ($value !== '') {
                    // 对数字字段做个检查
                    if (in_array($field, ['payrate_percent', 'payrate_each']) && !is_numeric($value)) {
                        continue; // 非数字则跳过
                    }
                    $update[$field] = $value;
                }
            }

            if ($update) {
                $update['updatetime'] = time();
                $this->model->whereIn("id", $ids)->update($update);
            }

            $this->success("操作成功");
        } else {
            // 打开弹窗页面时也校验一次谷歌验证码
            $code = $this->request->param('code', '');
            if ($code === '') {
                $this->error('请输入谷歌验证码');
            }
            $adminModel = \app\admin\model\Admin::get($this->auth->id);
            if (!$adminModel || !google_verify_code($adminModel, $code)) {
                $this->error(__('googleMFA error Please try again'));
            }

            $apilist = ApiAccount::field('id,name')->select();
            $this->view->assign(compact('apilist', 'ids'));
            return $this->view->fetch();
        }
    }

    public function add()
    {
        $this->error('该功能不存在');
    }

    public function edit($ids = null)
    {
        $this->error('该功能不存在');
    }

    public function multi($ids = "")
    {
        $this->error('该功能不存在');
    }
}


