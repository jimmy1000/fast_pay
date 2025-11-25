<?php

namespace app\admin\controller\user;

use app\common\controller\Backend;
use app\common\library\Auth;
use think\Exception;
use think\Db;

/**
 * 会员管理
 *
 * @icon fa fa-user
 */
class User extends Backend
{

    protected $relationSearch = true;
    protected $searchFields = 'id,username,nickname,merchant_id';

    /**
     * @var \app\admin\model\User
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\User;
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
                ->with('group')
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);
            foreach ($list as $k => $v) {
                $v->avatar = $v->avatar ? cdnurl($v->avatar, true) : letter_avatar($v->nickname);
                $v->hidden(['password', 'salt']);
            }
            
            // 统计数据（不受搜索条件影响，统计所有数据）
            $allMoney = $this->model->sum('money') ?: 0;
            $allWithDrayMoney = $this->model->sum('withdrawal') ?: 0;
            
            $result = array("total" => $list->total(), "rows" => $list->items());
            $result['extend'] = [
                'allMoney' => $allMoney,
                'allWithDrayMoney' => $allWithDrayMoney
            ];

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 添加
     */
    public function add()
    {
        $this->view->assign('groupList', build_select('row[group_id]', \app\admin\model\UserGroup::column('id,name'), 1, ['class' => 'form-control selectpicker']));
        return parent::add();
    }

    /**
     * 编辑
     */
    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        $this->modelValidate = true;
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $this->view->assign('groupList', build_select('row[group_id]', \app\admin\model\UserGroup::column('id,name'), $row['group_id'], ['class' => 'form-control selectpicker']));
        return parent::edit($ids);
    }

    /**
     * 删除
     */
    public function del($ids = "")
    {
        if (!$this->request->isPost()) {
            $this->error(__("Invalid parameters"));
        }
        $ids = $ids ? $ids : $this->request->post("ids");
        $row = $this->model->get($ids);
        $this->modelValidate = true;
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        Auth::instance()->delete($row['id']);
        $this->success();
    }

    /**
     * 重设md5密钥
     */
    public function resetMd5Key()
    {
        $data = [
            'id' => $this->request->param('id', '')
        ];
        //验证规则
        $result = $this->validate($data, 'User.reset_md5_key');
        if (true !== $result) {
            $this->error($result);
        }
        try {
            \app\admin\model\User::resetMd5Key($data['id']);
            $this->success(__('Reset Key Success'));
        } catch (Exception $e) {
            $this->error(__('Reset Key Error %s', $e->getMessage()));
        }
    }

    /**
     * 清除谷歌绑定
     */
    public function resetGoogleBind()
    {
        $data = [
            'id' => $this->request->param('id', '')
        ];
        $result = $this->validate($data, 'User.reset_google_bind');
        if (true !== $result) {
            $this->error($result);
        }
        try {
            \app\common\model\User::clearGoogleSecret($data['id']);
            $this->success(__('Reset Google Binding Success'));
        } catch (Exception $e) {
            $this->error(__('Reset Google Binding Error %s', $e->getMessage()));
        }
    }

    /**
     * 内充余额
     */
    public function recharge()
    {
        if ($this->request->isAjax()) {
            $data = $this->request->only(['merchant_id', 'money', 'code']);
            
            $rules = [
                'merchant_id|商户号' => 'require|integer|max:24',
                'money|充值金额' => 'require|float|>:0',
                'code|谷歌验证码' => 'require',
            ];
            $result = $this->validate($data, $rules);
            
            if (true !== $result) {
                $this->error($result);
            }
            
            // 验证管理员的谷歌验证码
            $adminModel = \app\admin\model\Admin::get($this->auth->id);
            if (!$adminModel) {
                $this->error('管理员信息不存在');
            }
            if (!google_verify_code($adminModel, $data['code'])) {
                $this->error(__('googleMFA error Please try again'));
            }
            
            $userModel = \app\common\model\User::get(['merchant_id' => $data['merchant_id']]);
            if (is_null($userModel)) {
                $this->error('商户不存在！');
            }
            
            $merchantId = $userModel['merchant_id'];
            $money = $data['money'];
            $commission = 0;
            $style = 1;
            
            if (!is_numeric($money) || $money <= 0) {
                $this->error('请填写正确的金额');
            }
            
            $money = sprintf('%.2f', $money);
            
            Db::startTrans();
            try {
                // 创建订单
                $orderData = [
                    'merchant_id' => $merchantId,
                    'orderno' => 'NC' . date('YmdHis') . mt_rand(100000, 999999),
                    'sys_orderno' => 'CZ' . date('YmdHis') . mt_rand(100000, 999999),
                    'total_money' => $money,
                    'have_money' => $money,
                    'email' => 'recharge@recharge.com',
                    'phone' => '0',
                    'productInfo' => '内充余额',
                    'name' => 'admin-recharge',
                    'utr' => '0',
                    'upstream_money' => 0,
                    'style' => $style,
                    'status' => 1,
                    'rate' => 0,
                    'channel_rate' => 0,
                    'api_upstream_id' => 0,
                    'api_account_id' => 0,
                    'api_type_id' => 0,
                    'req_info' => urldecode(http_build_query($data)),
                    'req_ip' => $this->request->ip(),
                ];
                $orderModel= \app\common\model\Order::create($orderData);
                
                // 更新用户内充金额
                $userModel->setInc('recharge', $money);
                
                // 资金变动
                \app\common\model\User::money($money, $userModel->id, '总后台内充：' . $money . '越南盾',$orderModel['sys_orderno'],1);
                
                // 写入用户日志表
                \app\common\model\UserLog::addLog(
                    $merchantId,
                    '管理员发起商户内充【' . $merchantId . '】【' . $money . '越南盾】手续费：' . $commission . '越南盾'
                );
                
                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            
            $this->success('内充成功！');
        }
        
        $id = $this->request->param('id/d', 0);
        $userModel = \app\common\model\User::get($id);
        if (is_null($userModel)) {
            $this->error('商户不存在！');
        }
        
        $this->assign('merchant_id', $userModel['merchant_id']);
        $this->assign('money', $userModel['money']);

        return $this->view->fetch();
    }

    /**
     * 结算余额
     */
    public function settlement()
    {
        if ($this->request->isAjax()) {
            $data = $this->request->only([
                'merchant_id',
                'money',
                'bankcardId',
                'status',
                'msg',
                'bankname',
                'usdt_rate',
                'usdt_money',
                'code',
            ]);
            $rules = [
                'merchant_id|商户号' => 'require|integer|max:24',
                'money|结算金额' => 'require|float|>:0',
                'bankcardId|结算卡id' => 'require|integer|max:12',
                'status|结算状态' => 'require|in:0,1,2',
                'code|谷歌验证码' => 'require',
            ];
            $result = $this->validate($data, $rules);
            if (true !== $result) {
                $this->error($result);
            }
            $adminModel = \app\admin\model\Admin::get($this->auth->id);
            if (!$adminModel) {
                $this->error('管理员信息不存在');
            }
            if (!google_verify_code($adminModel, $data['code'])) {
                $this->error(__('googleMFA error Please try again'));
            }
            $userModel = \app\common\model\User::get(['merchant_id' => $data['merchant_id']]);
            if (is_null($userModel)) {
                $this->error('商户不存在！');
            }
            $merchantId = $userModel['merchant_id'];
            $money = $data['money'];
            $balance = $userModel['money'];
            $freezeMoney = $userModel->getFreezeMoney();
            $userMoney = bcsub($balance, $freezeMoney, 2);
            $commission = config('site.back_rate_switch') == 1 ? $userModel->commission($money) : 0;
            $needMoney = bcadd($money, $commission, 2);

            if (!is_numeric($money) || $money <= 0) {
                $this->error('请填写正确的金额');
            }
            if ($needMoney > $userMoney) {
                $this->error('支付金额不足,当前需要' . $needMoney . '越南盾,手续费：' . $commission . '越南盾！');
            }

            $bankcardModel = (new \app\common\model\Bankcard)->where([
                'id' => $data['bankcardId'],
                'merchant_id' => $data['merchant_id'],
                'status' => '1'
            ])->find();
            if (is_null($bankcardModel)) {
                $this->error('银行卡无法支付，请更换');
            }
            $isUsdt = strtolower((string)$bankcardModel['bankcardtype']) === 'usdt';
            $usdtRate = 0;
            $usdtMoney = 0;
            if ($isUsdt) {
                $usdtRate = $data['usdt_rate'] ?? '';
                $usdtMoney = $data['usdt_money'] ?? '';
                if (!is_numeric($usdtRate) || $usdtRate <= 0) {
                    $this->error('请填写正确的汇率');
                }
                if (!is_numeric($usdtMoney) || $usdtMoney <= 0) {
                    $this->error('请填写正确的USDT金额');
                }
            }
            $redislock = redisLocker();
            $resource = $redislock->lock('pay.' . $merchantId, 3000);
            if (!$resource) {
                $this->error('系统繁忙，请重试');
            }

            Db::startTrans();
            try {
                $payData = [
                    'merchant_id'    => $merchantId,
                    'orderno'        => 'TX' . date('YmdHis') . mt_rand(100000, 999999),
                    'style'          => $isUsdt ? '1' : '0',
                    'apply_style'    => '1',
                    'money'          => $money,
                    'charge'         => $commission,
                    'caraddresstype' => $isUsdt ? ($bankcardModel['caraddresstype'] ?? '-') : '-',
                    'caraddress'     => $isUsdt ? ($bankcardModel['caraddress'] ?? '-') : '-',
                    'usdt_rate'      => $isUsdt ? $usdtRate : 0,
                    'usdt'           => $isUsdt ? $usdtMoney : 0,
                    'msg'            => $data['msg'] ?? '管理员给商户提现',
                    'image'          => '',
                    'status'         => $data['status'],
                    'account'        => $bankcardModel['bankaccount'] ?? '',
                    'name'           => $bankcardModel['name'] ?? '',
                    'phone'          => $bankcardModel['phone'] ?? '',
                    'email'          => $bankcardModel['email'] ?? '',
                    'bankname'       => $bankcardModel['bankname'] ?? ($data['bankname'] ?? ''),
                    'bic'            => $bankcardModel['bic'] ?? '',
                    'utr'            => '00000000',
                    'req_info'       => '总后台结算',
                    'req_ip'         => $this->request->ip(),
                ];
                $payModel = \app\common\model\RepaySettle::create($payData);
                $userModel->setInc('withdrawal', $money);
                \app\common\model\User::money(-$needMoney, $userModel->id, '提现：' . $money . '越南盾，手续费：' . $commission . '越南盾', $payModel['orderno'], '2');
                \app\common\model\UserLog::addLog($merchantId, '管理员发起商户提现【' . $merchantId . '】【' . $money . '越南盾】手续费：' . $commission . '越南盾');
                Db::commit();
                $redislock->unlock(['resource' => 'pay.' . $merchantId, 'token' => $resource['token']]);
            } catch (\Exception $e) {
                Db::rollback();
                $redislock->unlock(['resource' => 'pay.' . $merchantId, 'token' => $resource['token']]);
                $this->error($e->getMessage());
            }

            $this->success('结算成功！');
        }

        $id = $this->request->param('id/d', 0);
        $userModel = \app\common\model\User::get($id);
        if (is_null($userModel)) {
            $this->error('商户不存在！');
        }
        $this->assign('merchant_id', $userModel['merchant_id']);
        $this->assign('money', $userModel['money']);
        $this->assign('freezeMoney', $userModel->getFreezeMoney());
        $this->assign('settle', $userModel->settle());
        $bankcardList = (new \app\common\model\Bankcard)->where([
            'merchant_id' => $userModel['merchant_id'],
            'status' => '1'
        ])->field('id,name,bankaccount,bankcardtype,caraddresstype,caraddress')->select();
        $this->assign('bankcardList', collection((array)$bankcardList)->toArray());
        return $this->view->fetch();
    }
}
