<?php

namespace app\admin\controller\api;

use app\common\controller\Backend;
use app\common\model\api\Channel as ApiChannel;
use think\Db;
use think\exception\PDOException;
use Exception;

/**
 * 支付类型
 *
 * @icon fa fa-circle-o
 */
class Type extends Backend
{
    // 供前端下拉或联动调用
    protected $noNeedRight = ['getAccount','items'];

    /**
     * Type模型对象
     * @var \app\admin\model\api\Type
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\api\Type;
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("defaultList", $this->model->getDefaultList());
        
        // 获取规则下拉列表
        $apiRuleList = \app\admin\model\api\Rule::column('name', 'id');
        $this->view->assign("apiRuleList", $apiRuleList);
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
        //当前是否为关联查询
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $list = $this->model
                    ->with(['rule'])
                    ->where($where)
                    ->order($sort, $order)
                    ->paginate($limit);

            foreach ($list as $row) {
                $rule = $row->getRelation('rule');
                if ($rule) {
                    $rule->visible(['name']);
                }
            }

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 获取该接口类型下开启的账户（来自 api_channel）
     */
    public function getAccount()
    {
        $id = $this->request->param('id/d', 0);
        $list = ApiChannel::getAccountByType($id);
        $this->success('', '', $list);
    }

    /**
     * 获取开启的接口类型（供下拉）
     */
    public function items()
    {
        $list = \app\admin\model\api\Type::where('status','1')->select();
        $list = is_array($list) ? $list : collection($list)->toArray();
        return json($list);
    }

    /**
     * 关联删除：删除支付类型时同时删除 api_channel 中的相关记录
     */
    public function del($ids = "")
    {
        if ($ids) {
            $pk = $this->model->getPk();
            $adminIds = $this->getDataLimitAdminIds();
            if (is_array($adminIds)) {
                $this->model->where($this->dataLimitField, 'in', $adminIds);
            }
            $list = $this->model->where($pk, 'in', $ids)->select();
            $count = 0;

            Db::startTrans();
            try {
                // 先删除 api_channel 关联数据
                foreach ($list as $v) {
                    $apiTypeId = $v[$pk];
                    Db::name('api_channel')->where('api_type_id', $apiTypeId)->delete();
                }
                // 再删除类型本身
                foreach ($list as $v) {
                    $count += $v->delete();
                }
                Db::commit();
            } catch (PDOException $e) {
                Db::rollback();
                $this->error($e->getMessage());
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if ($count) {
                $this->success();
            } else {
                $this->error(__('No rows were deleted'));
            }
        }
        $this->error(__('Parameter %s can not be empty', 'ids'));
    }

}
