<?php

namespace app\admin\controller\user;

use app\common\controller\Backend;
use app\admin\model\api\Type as ApiType;
use app\common\model\UserApichannel;
use think\Db;

/**
 * 用户通道配置
 *
 * @icon fa fa-exchange
 */
class Apichannel extends Backend
{
    protected $relationSearch = true;
    
    /**
     * @var \app\admin\model\User
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('User');
    }

    /**
     * 用户列表
     */
    public function index()
    {
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $total = $this->model
                ->with('group')
                ->where($where)
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->with('group')
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            foreach ($list as $k => $v) {
                $v->hidden(['password', 'salt', 'googlesecret', 'paypassword', 'paysalt', 'token', 'public_key', 'md5key']);
            }
            
            $result = array("total" => $total, "rows" => $list);
            $allMoney = $this->model->sum('money');
            $allWithDrayMoney = $this->model->sum('withdrawal');
            $result['extend'] = [
                'allMoney' => $allMoney,
                'allWithDrayMoney' => $allWithDrayMoney
            ];
            
            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 单个用户通道费率设置
     */
    public function single()
    {
        // 谷歌验证码校验（仅首次进入页面时验证 GET/POST 的 code 参数）
        $code = $this->request->param('code', '');
        if ($code === '') {
            $this->error('请输入谷歌验证码');
        }
        // 验证当前管理员的谷歌验证码
        $adminModel = \app\admin\model\Admin::get($this->auth->id);
        if (!$adminModel || !google_verify_code($adminModel, $code)) {
            $this->error(__('googleMFA error Please try again'));
        }

        if ($this->request->isPost()) {
            $row = $this->request->param('row/a');
            
            Db::startTrans();
            try {
                $insert_data = [];
                $update_data = [];
                
                // 遍历所有提交的支付类型
                foreach ($row['types'] as $typeid) {
                    // 验证该支付类型的数据是否存在
                    if (!isset($row[$typeid])) {
                        continue; // 跳过没有数据的支付类型
                    }
                    
                    // 获取表单数据，设置默认值
                    $ruleId = isset($row[$typeid]['rule']) ? intval($row[$typeid]['rule']) : 0;
                    $rate = isset($row[$typeid]['rate']) ? floatval($row[$typeid]['rate']) : 0;
                    $status = isset($row[$typeid]['status']) ? $row[$typeid]['status'] : '1';
                    
                    // 查询数据库中是否已存在该用户该支付类型的配置
                    $model = UserApichannel::get(function ($query) use ($typeid, $row) {
                        $query->where([
                            'api_type_id' => $typeid,
                            'user_id' => $row['id']
                        ]);
                    });
                    
                    // 如果不存在则插入，存在则更新
                    if (is_null($model)) {
                        $insert_data[] = [
                            'user_id' => intval($row['id']),
                            'api_type_id' => intval($typeid),
                            'api_rule_id' => $ruleId,
                            'rate' => $rate,
                            'status' => $status
                        ];
                    } else {
                        $update_data[] = [
                            'id' => $model->id,
                            'user_id' => intval($row['id']),
                            'api_type_id' => intval($typeid),
                            'api_rule_id' => $ruleId,
                            'rate' => $rate,
                            'status' => $status
                        ];
                    }
                }
                
                // 批量插入和更新
                $model = new UserApichannel();
                if ($insert_data) {
                    $model->insertAll($insert_data);
                }
                if ($update_data) {
                    $model->isUpdate()->saveAll($update_data);
                }
                
                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            
            return $this->success('设置成功!');
        }
        
        $user_id = $this->request->param('id/d', '');
        $user = \app\admin\model\User::get($user_id);
        
        if (is_null($user)) {
            $this->error('商户不存在!');
        }
        
        $apiTypeList = ApiType::getOpenListAndRule();
        $userChannelList = UserApichannel::getListByUser($user_id);
        
        $this->assign('api_type_list', $apiTypeList);
        $this->assign('user_channel_list', $userChannelList);
        $this->assign('user', $user->visible(['merchant_id', 'id'])->toArray());
        
        return $this->view->fetch();
    }

    /**
     * 获取用户的通道配置（Ajax）
     */
    public function getUserChannels()
    {
        $user_id = $this->request->param('user_id');
        if (!$user_id) {
            $this->error('参数错误');
        }
        
        // 获取用户的通道配置，按支付类型去重（每个支付类型只保留一条，取ID最大的）
        $list = UserApichannel::where('user_id', $user_id)
            ->order('id', 'desc')
            ->select();
        
        // 按支付类型去重，保留每个支付类型的第一条（ID最大的）
        $uniqueList = [];
        foreach ($list as $item) {
            $typeId = $item->api_type_id;
            if (!isset($uniqueList[$typeId])) {
                $uniqueList[$typeId] = $item;
            }
        }
        
        $result = [];
        foreach ($uniqueList as $item) {
            // 获取支付类型名称
            $apiType = \app\admin\model\api\Type::where('id', $item->api_type_id)->find();
            // 获取规则名称
            $apiRule = \app\admin\model\api\Rule::where('id', $item->api_rule_id)->find();
            
            $result[] = [
                'type_name' => $apiType ? $apiType->name : '-',
                'rate' => $item->rate,
                'rule_name' => $apiRule ? $apiRule->name : '系统默认',
                'status' => $item->status
            ];
        }
        
        $this->success('', null, $result);
    }

    /**
     * 批量设置通道费率
     */
    public function batch()
    {
        if ($this->request->isAjax()) {
            // Ajax 提交时也要求带上 code 并校验
            $code = $this->request->param('code', '');
            if ($code === '') {
                $this->error('请输入谷歌验证码');
            }
            $adminModel = \app\admin\model\Admin::get($this->auth->id);
            if (!$adminModel || !google_verify_code($adminModel, $code)) {
                $this->error(__('googleMFA error Please try again'));
            }

            $row = $this->request->param('row/a');
            
            if (empty($row['user_ids']) || !is_array($row['user_ids'])) {
                $this->error('请选择商户');
            }
            
            Db::startTrans();
            try {
                $insert_data = [];
                $update_data = [];
                
                foreach ($row['user_ids'] as $user_id) {
                    foreach ($row['types'] as $typeid) {
                        // 如果三个字段都为空，直接跳过（表示不修改）
                        $rate   = isset($row[$typeid]['rate']) && $row[$typeid]['rate'] !== '' ? $row[$typeid]['rate'] : null;
                        $rule   = isset($row[$typeid]['rule']) && $row[$typeid]['rule'] !== '' ? $row[$typeid]['rule'] : null;
                        $status = isset($row[$typeid]['status']) && $row[$typeid]['status'] !== '' ? $row[$typeid]['status'] : null;
                        
                        if ($rate === null && $rule === null && $status === null) {
                            continue; // 全部为空，跳过
                        }
                        
                        $model = UserApichannel::get(function ($query) use ($typeid, $user_id) {
                            $query->where([
                                'api_type_id' => $typeid,
                                'user_id' => $user_id
                            ]);
                        });
                        
                        if (is_null($model)) {
                            // 只有有值的才写入
                            $data = [
                                'user_id'     => $user_id,
                                'api_type_id' => $typeid,
                            ];
                            if ($rule   !== null) $data['api_rule_id'] = $rule;
                            if ($rate   !== null) $data['rate']        = $rate;
                            if ($status !== null) $data['status']      = $status;
                            $insert_data[] = $data;
                        } else {
                            // 更新时只更新有值的字段
                            $data = ['id' => $model->id];
                            if ($rule   !== null) $data['api_rule_id'] = $rule;
                            if ($rate   !== null) $data['rate']        = $rate;
                            if ($status !== null) $data['status']      = $status;
                            if (count($data) > 1) { // 至少有更新内容
                                $update_data[] = $data;
                            }
                        }
                    }
                }
                
                $model = new UserApichannel();
                if ($insert_data) $model->insertAll($insert_data);
                if ($update_data) $model->isUpdate()->saveAll($update_data);
                
                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            
            return $this->success('批量设置成功!');
        }
        
        // GET 页面渲染
        $user_ids = $this->request->param('user_ids', '');
        $user_ids = $user_ids ? explode(',', $user_ids) : [];
        
        // 用模型查出商户信息
        $users = \app\admin\model\User::whereIn('id', $user_ids)
            ->field('id, merchant_id, username')
            ->select();
        
        if (empty($user_ids)) {
            $this->error('请选择商户');
        }
        
        $apiTypeList = ApiType::getOpenListAndRule();
        
        $this->assign('api_type_list', $apiTypeList);
        $this->assign('users', $users);
        
        return $this->view->fetch();
    }
}

