<?php
/**
 * 商户后台出款管理api
 * @author : jimmy
 * @date : 2025-12-12

 */

namespace app\api\controller;

use app\common\controller\Api;
use app\common\model\User;
use app\common\model\UserLog;
use Carbon\Carbon;
use fast\Http;
use fast\Random;
use think\Cache;
use think\Db;
use think\Log;

class Withdrawal extends Api
{


    protected $noNeedLogin = [];        //不需要登录的方法
    protected $noNeedRight = '*';


    /**
     * 引入后台控制器的traits
     */
    use \app\api\library\traits\Api;

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 获取代付订单列表
     * 
     * @ApiMethod (GET)
     * @ApiParams (name="orderno", type="string", required=false, description="订单号")
     * @ApiParams (name="status", type="integer", required=false, description="订单状态 0=待处理,1=已支付,2=冻结,3=已取消,4=失败")
     * @ApiParams (name="date", type="array", required=false, description="创建时间范围")
     * @ApiParams (name="page", type="integer", required=false, description="页码，默认1")
     * @ApiParams (name="orderField", type="string", required=false, description="排序字段，默认id")
     */
    public function index()
    {
        $data = $this->request->only(['orderno', 'status', 'date','notify_status']);

        $rules = [
            'orderno|订单号' => 'alphaDash',
            'status|订单状态' => 'in:0,1,2,3,4',
            'date|日期范围' => 'array',
            'notify_status|通知状态' => 'in:0,1,2'
        ];
        
        $result = $this->validate($data, $rules);
        if ($result !== true) {
            $this->error($result);
        }

        $merchantId = $this->auth->merchant_id;

        // 构建查询条件
        $where = [
            'merchant_id' => $merchantId
        ];

        // 筛选订单号
        if (!empty($data['orderno'])) {
            $where['orderno'] = ['like', '%' . $data['orderno'] . '%'];
        }
        
        // 订单状态
        if (isset($data['status']) && $data['status'] !== '') {
            $where['status'] = $data['status'];
        }

        // 处理创建时间范围（自动识别毫秒或秒级时间戳）
        if (isset($data['date']) && is_array($data['date']) && count($data['date']) == 2) {
            $timeRange = $this->parseTimeRange($data['date']);
            $where['createtime'] = ['between time', $timeRange];
        }

        // 分页参数
        $orderField = $this->request->param('orderField', 'id');
        $sort = 'DESC';
        $page = $this->request->param('page/d', 1);
        $pageLimit = 10;
        $offset = ($page - 1) * $pageLimit;

        // 获取总数
        $total = \app\common\model\RepayOrder::where($where)->count();

        // 获取列表数据
        $list = \app\common\model\RepayOrder::where($where)
            ->order($orderField, $sort)
            ->limit($offset, $pageLimit)
            ->select();

        // 设置可见字段（直接返回时间戳，不进行格式转换）
        $visibleFields = [
            'orderno', 'style_text', 'money', 'name', 'account', 
            'bankname', 'phone', 'email', 'ifsc', 'bank_code',
            'status', 'daifustatus_text', 'notify_status',
            'utr', 'charge', 'upcharge', 'msg', 
            'caraddresstype', 'caraddress', 
            'createtime', 'paytime'
        ];
        foreach ($list as $v) {
            $v->visible($visibleFields);
        }
        $list = collection($list)->toArray();

        // 统计信息
        $extend = $this->calculateStatistics($where, $merchantId);

        $this->success('Data retrieved successfully', [
            'total' => $total,
            'list' => $list,
            'limit' => $pageLimit,
            'extend' => $extend
        ]);
    }

    /**
     * 解析时间范围（自动识别毫秒或秒级时间戳）
     * @param array $timeRange 时间范围数组 [开始时间, 结束时间]
     * @return array 转换后的时间范围（秒级）
     */
    private function parseTimeRange($timeRange)
    {
        $startTime = $timeRange[0];
        $endTime = $timeRange[1];
        
        // 判断是否为毫秒级时间戳（13位数字）
        if (strlen((string)$startTime) > 10) {
            $startTime = intval($startTime / 1000);
        }
        if (strlen((string)$endTime) > 10) {
            $endTime = intval($endTime / 1000);
        }
        
        return [$startTime, $endTime];
    }

    /**
     * 计算统计信息
     * @param array $where 查询条件
     * @param int $merchantId 商户ID
     * @return array 统计信息
     */
    private function calculateStatistics($where, $merchantId)
    {
        $extend = [];

        // 今日代付
        $extend['todayMoney'] = \app\common\model\RepayOrder::whereTime('createtime', 'today')
            ->where('merchant_id', $merchantId)
            ->sum('money') ?: 0;
        $extend['todayPoundage'] = \app\common\model\RepayOrder::whereTime('createtime', 'today')
            ->where('merchant_id', $merchantId)
            ->sum('charge') ?: 0;

        // 昨日代付
        $extend['yesterMoney'] = \app\common\model\RepayOrder::whereTime('createtime', 'yesterday')
            ->where('merchant_id', $merchantId)
            ->sum('money') ?: 0;
        $extend['yesterPoundage'] = \app\common\model\RepayOrder::whereTime('createtime', 'yesterday')
            ->where('merchant_id', $merchantId)
            ->sum('charge') ?: 0;

        // 总代付
        $extend['allMoney'] = \app\common\model\RepayOrder::where('merchant_id', $merchantId)
            ->sum('money') ?: 0;
        $extend['allPoundage'] = \app\common\model\RepayOrder::where('merchant_id', $merchantId)
            ->sum('charge') ?: 0;

        // 列表金额（符合筛选条件的）
        $extend['all'] = \app\common\model\RepayOrder::where($where)->sum('money') ?: 0;
        $extend['poundage'] = \app\common\model\RepayOrder::where($where)->sum('charge') ?: 0;

        return $extend;
    }

    /**
     * 批量代付
     *
     */

    public function batchapply()
    {

        $data = $this->request->only(['payPassword', 'googlemfa', 'list']);
        $rules = [
            'list|提现列表' => 'require|array|length:1,20',
            'payPassword|支付密码' => 'require|length:6,16',
            'googlemfa|谷歌验证码' => 'require'
        ];

        $result = $this->validate($data, $rules);
        if ($result !== true) {
            $this->error($result);
        }

        $userModel = $this->auth->getUser();

        //商户号
        $merchant_id = $userModel['merchant_id'];


        //用户余额
        $balance = $userModel['money'];
        //冻结金额
        $freezeMoney = $userModel->getFreezeMoney();
        //可用金额
        $userMoney = bcsub($balance, $freezeMoney, 2);

        if ($userModel['batchrepay'] == '0') {
            $this->error('您当前不支持批量代付，如有需要请联系商务开通。');
        }

        //判断是否在代付时间内
        if (!checkRepayTime()) {
            $this->error('请在提现允许时间段内操作！');
        }


        $dataList = [];     //结算信息
        $totalMoney = 0; //总金额
        $tixianMoney = 0; //支付金额
        $shouxuMoney = 0; //手续金额

        $counter = 0;
        foreach ($data['list'] as $item) {

            $commission = 0;
            $money = 0;
            ++$counter;

            $money = $item['outAmount'];
            if (!is_numeric($money) || $money <= 0) {
                $this->error('金额格式有误，请确保金额为数字。');
            }

            if (empty($item['name']) || !preg_match('#^[A-Za-z\s-]+$#', $item['name'])) {
                $this->error('开户名格式有误，只能为字母、空格和连字符。');
            }


            if (empty($item['bankname']) || !\think\Validate::is($item['bankname'], 'alpha')) {
                $this->error('银行名格式有误，只能为汉字或者字母。');
            }


            if (empty($item['email']) || !\think\Validate::is($item['email'], 'email')) {
                $this->error('请输入正确的邮箱地址。');
            }


            if (empty($item['phone']) || !\think\Validate::is($item['phone'], 'number')) {
                $this->error('手机号正能为数字!');
            }

            $commission = $userModel->commission($money);;
            $needMoney = bcadd($money, $commission, 2);
            $tixianMoney = bcadd($tixianMoney, $money, 2);
            $totalMoney = bcadd($totalMoney, $needMoney, 2);
            $shouxuMoney = bcadd($shouxuMoney, $commission, 2);
            $dataList[] = [
                'merchant_id' => $merchant_id,
                'orderno' => $userModel->id.Random::getOrderId() . $counter,
                'style' => '0',
                'money' => $money,
                'name' => $item['name'],
                'account' => $item['bankaccount'],
                'bankname' => $item['bankname'],
                'email' => $item['email'],
                'phone' => $item['phone'],
                'bic' => $item['bic'] ?? '',
                'bank_code' => 'BANK_IN',
                'notifyUrl' => '',
                'status' => '0',
                'daifustatus' => '0',
                'charge' => $commission,
                'msg' => '',
                'req_info' => '',
                'req_ip' => ''
            ];
        }

        //判断结算金额是否超出
        if ($totalMoney > $userMoney) {
            $this->error('支付金额不足,当前需要' . $totalMoney . '越南盾,手续费：' . $shouxuMoney . '越南盾！');
        }

        //检查最小提现金额
        if ($tixianMoney < config('site.minpay')) {
            $this->error('支付金额小于最小要求金额！最低支付' . config('site.minpay') . '越南盾');
        }

        //验证Google MFA
        if (!google_verify_code($userModel, $data['googlemfa'])) {
            $this->error(__('Google MFA code is incorrect'));
        }

        //验证支付密码是否正确
        $flag = \app\common\model\User::verifyPayPassword($data['payPassword'], $this->auth->id);

        if (!$flag) {
            $this->error('支付密码输入不正确!');
        }


        $redislock = redisLocker();
        $resource = $redislock->lock('pay.' . $merchant_id, 3000);   //单位毫秒

        if ($resource) {
            //开始事务
            Db::startTrans();

            try {
                $payModel = new \app\common\model\RepayOrder();
                $payModel->saveAll($dataList);
                //更新用户金额
                $userModel->setInc('withdrawal', $tixianMoney);
                //资金变动
                User::money(-$totalMoney, $userModel->id, '提现：' . $tixianMoney . '越南盾，手续费：' . $shouxuMoney . '越南盾', '代付批量提交', '2');
                //写入用户日志表
                UserLog::addLog($this->auth->merchant_id, '批量提现：' . $tixianMoney . '越南盾，手续费：' . $shouxuMoney . '越南盾');
                Db::commit();
                $redislock->unlock(['resource' => 'pay.' . $merchant_id, 'token' => $resource['token']]);

            } catch (\Exception $e) {
                Db::rollback();
                $redislock->unlock(['resource' => 'pay.' . $merchant_id, 'token' => $resource['token']]);
                $this->error($e->getMessage());
            }

        } else {
            $this->error('系统繁忙,请重新提交');
        }


       //Notify::repay();到时候做百度语音暂时先注释


        //判断自动代付提交
        if ($userModel['ifdaifuauto'] == '1') {
            if ($userModel['daifuid'] > 0) {
                try {
                    foreach ($dataList as $item) {
                        \app\common\model\RepayOrder::dfSubmit($item['orderno'], $userModel['daifuid'], true);
                    }
                } catch (\Exception $e) {
                    Log::record('自动代付异常,商户号:' . $this->auth->merchant_id . '异常信息:' . $e->getMessage(), 'REPAY_ERROR');
                }
            }
        }
        $this->success('申请成功。');
    }
    /**
     * 申请结算
     */
    public function apply()
    {
        $data = $this->request->only(['money', 'bankcardId', 'payPassword', 'googlemfa','usdt','usdt_rate','msg']);
        $rules = [
            'money|提现金额' => 'require|float|>:0',
            'bankcardId|提款银行卡' => 'require|integer',
            'payPassword|支付密码' => 'require|length:6,16',
            'googlemfa|谷歌验证码' => 'require'
        ];
        $result = $this->validate($data, $rules);
        if ($result !== true) {
            $this->error($result);
        }

        $userModel = $this->auth->getUser();
        //商户号
        $merchant_id = $userModel['merchant_id'];
        //提多少钱
        $money = $data['money'];
        //用户余额
        $balance = $userModel['money'];
        //冻结金额
        $freezeMoney = $userModel->getFreezeMoney();
        //可用金额
        $userMoney = bcsub($balance, $freezeMoney, 2);
        
        //验证Google MFA
        if (!google_verify_code($userModel, $data['googlemfa'])) {
            $this->error(__('Google MFA code is incorrect'));
        }
        
        //验证支付密码是否正确
        $flag = \app\common\model\User::verifyPayPassword($data['payPassword'], $this->auth->id);
        if (!$flag) {
            $this->error('支付密码输入不正确!');
        }
        // 检查银行卡是否存在
        $bankcardModel = \app\common\model\Bankcard::where([
            'id' => $data['bankcardId'],
            'merchant_id' => $this->auth->merchant_id,
            'status' => '1'
        ])->find();
        if (is_null($bankcardModel)) {
            $this->error('银行卡无法支付，请更换');
        }
        $commission = 0;
        //手续费,usdt不用手续费
        if($bankcardModel['bankcardtype']!='usdt'){
            $commission = $userModel->commission($money);
        }
        $needMoney = bcadd($money, $commission, 2);

        //判断是否在代付时间内
        if (!checkRepayTime()) {
            $this->error('请在提现允许时间段内操作！');
        }

        //判断余额是否足够
        if (!is_numeric($money) || $money <= 0) {
            $this->error('请填写正确的金额');
        }

        if (floatval($needMoney) > floatval($userMoney)) {
            $this->error('支付金额不足,需要' . $needMoney . '越南盾,手续费：' . $commission . '越南盾，可用余额：'.$userMoney.'越南盾');
        }



        //检查最小提现金额
        if ($money < config('site.minpay')) {
            $this->error('支付金额小于最小要求金额！最低支付' . config('site.minpay') . '越南盾');
        }



        //为了避免同时修改数据 还是加一下锁

        // 判断是否为 USDT 类型
        $isUsdt = strtolower((string)$bankcardModel['bankcardtype']) === 'usdt';
        
        $redislock = redisLocker();
        $resource = $redislock->lock('pay.' . $merchant_id, 3000);   //单位毫秒

        if ($resource) {

            //开始事务
            Db::startTrans();

            try {
                // 生成订单号
                $orderno = 'TX' . date('YmdHis') . mt_rand(100000, 999999);
                
                $settleData = [
                    'merchant_id' => $merchant_id,
                    'orderno' => $orderno,
                    'style' => $isUsdt ? '1' : '0', // 0=法币结算, 1=USDT下发
                    'apply_style' => '0', // 0=商户后台, 1=系统后台
                    'money' => $money,
                    'charge' => $commission,
                    'caraddresstype' => $isUsdt ? ($bankcardModel['caraddresstype'] ?? '-') : '-',
                    'caraddress' => $isUsdt ? ($bankcardModel['caraddress'] ?? '-') : '-',
                    'usdt_rate' => isset($data['usdt_rate']) ? floatval($data['usdt_rate']) : ($isUsdt ? 0 : 0),
                    'usdt' => isset($data['usdt']) ? floatval($data['usdt']) : ($isUsdt ? 0 : 0),
                    'msg' => $data['msg'] ?? '商户后台提现申请',
                    'image' => '',
                    'status' => '0', // 0=审核中
                    'account' => $bankcardModel['bankaccount'] ?? '',
                    'name' => $bankcardModel['name'] ?? '',
                    'phone' => $bankcardModel['phone'] ?? '',
                    'email' => $bankcardModel['email'] ?? '',
                    'bankname' => $bankcardModel['bankname'] ?? '',
                    'bic' => $bankcardModel['bic'] ?? '',
                    'utr' => '0',
                    'req_info' => '',
                    'req_ip' => $this->request->ip()
                ];

                $payModel = \app\common\model\RepaySettle::create($settleData);

                //更新用户金额
                $userModel->setInc('withdrawal', $money);
                //资金变动
                User::money(-$needMoney, $userModel->id, '提现：' . $money . '越南盾，手续费：' . $commission . '越南盾', $payModel['orderno'], '2');

                //写入用户日志表
                UserLog::addLog($this->auth->merchant_id, '申请提现：' . $money . '越南盾，手续费：' . $commission . '越南盾');

                Db::commit();
                $redislock->unlock(['resource' => 'pay.' . $merchant_id, 'token' => $resource['token']]);
            } catch (\Exception $e) {
                Db::rollback();
                $redislock->unlock(['resource' => 'pay.' . $merchant_id, 'token' => $resource['token']]);
                $this->error($e->getMessage());
            }

        } else {
            $this->error('系统繁忙,请重新提交');
        }

        // 如果是 USDT 下发，发送 Telegram 通知
        if ($isUsdt && isset($payModel)) {
            try {
                $request_data = [
                    'merchant_id' => $merchant_id,
                    'username' => $userModel['username'],
                    'money' => $money,
                    'usdt_rate' => isset($data['usdt_rate']) ? floatval($data['usdt_rate']) : 0,
                    'usdt_amount' => isset($data['usdt']) ? floatval($data['usdt']) : 0,
                    'usdt_address' => $bankcardModel['caraddress'] ?? '',
                ];
                $url = "http://127.0.0.1:9000/repay_notify";
                Http::send_json($url, $request_data);
            } catch (\Exception $e) {
                // 通知失败不影响结算流程
                \think\Log::record('USDT下发通知失败: ' . $e->getMessage(), 'REPAY_NOTIFY');
            }
        }
        
        $this->success('申请成功。');
    }

    /**
     * 获取结算记录列表
     * 
     * @ApiMethod (GET)
     * @ApiParams (name="status", type="integer", required=false, description="状态 0=审核中,1=已支付,2=取消")
     * @ApiParams (name="style", type="integer", required=false, description="结算类型 0=法币结算,1=USDT下发")
     * @ApiParams (name="money", type="float", required=false, description="金额")
     * @ApiParams (name="date", type="array", required=false, description="创建时间范围")
     * @ApiParams (name="page", type="integer", required=false, description="页码，默认1")
     * @ApiParams (name="orderField", type="string", required=false, description="排序字段，默认id")
     */
    public function settle()
    {
        $data = $this->request->only(['status', 'style', 'money', 'date']);
        $rules = [
            'status|状态' => 'in:0,1,2',
            'style|结算类型' => 'in:0,1',
            'money|金额' => 'float|>:0',
            'date|日期范围' => 'array'
        ];
        
        $result = $this->validate($data, $rules);
        if ($result !== true) {
            $this->error($result);
        }

        $merchantId = $this->auth->merchant_id;

        // 构建查询条件
        $where = [
            'merchant_id' => $merchantId
        ];

        if (isset($data['status']) && $data['status'] !== '') {
            $where['status'] = $data['status'];
        }

        if (isset($data['style']) && $data['style'] !== '') {
            $where['style'] = $data['style'];
        }

        // 金额筛选
        if (isset($data['money']) && $data['money'] > 0) {
            $where['money'] = $data['money'];
        }

        // 处理创建时间范围（自动识别毫秒或秒级时间戳）
        if (isset($data['date']) && is_array($data['date']) && count($data['date']) == 2) {
            $timeRange = $this->parseTimeRange($data['date']);
            $where['createtime'] = ['between time', $timeRange];
        }

        // 分页参数
        $orderField = $this->request->param('orderField', 'id');
        $sort = 'DESC';
        $page = $this->request->param('page/d', 1);
        $pageLimit = 10;
        $offset = ($page - 1) * $pageLimit;

        // 获取总数
        $total = \app\common\model\RepaySettle::where($where)->count();

        // 获取列表数据
        $list = \app\common\model\RepaySettle::where($where)
            ->order($orderField, $sort)
            ->limit($offset, $pageLimit)
            ->select();

        // 设置可见字段（直接返回时间戳，不进行格式转换）
        $visibleFields = [
            'id', 'orderno', 'money', 'charge',
            'style', 'style_text', 'apply_style',
            'status', 'status_text',
            'name', 'account', 'bankname', 'phone', 'email', 'bic',
            'caraddresstype', 'caraddress',
            'usdt_rate', 'usdt',
            'msg', 'image', 'utr',
            'createtime', 'paytime'
        ];
        foreach ($list as $v) {
            $v->visible($visibleFields);
        }
        $list = collection($list)->toArray();

        // 统计信息
        $extend = $this->calculateSettleStatistics($where, $merchantId);

        $this->success('Data retrieved successfully', [
            'total' => $total,
            'list' => $list,
            'limit' => $pageLimit,
            'extend' => $extend
        ]);
    }

    /**
     * 计算结算记录统计信息
     * @param array $where 查询条件
     * @param int $merchantId 商户ID
     * @return array 统计信息
     */
    private function calculateSettleStatistics($where, $merchantId)
    {
        $extend = [];

        // 今日结算金额
        $extend['todayMoney'] = \app\common\model\RepaySettle::whereTime('createtime', 'today')
            ->where('merchant_id', $merchantId)
            ->sum('money') ?: 0;

        // 昨日结算金额
        $extend['yesterMoney'] = \app\common\model\RepaySettle::whereTime('createtime', 'yesterday')
            ->where('merchant_id', $merchantId)
            ->sum('money') ?: 0;

        // 总结算金额
        $extend['allMoney'] = \app\common\model\RepaySettle::where('merchant_id', $merchantId)
            ->sum('money') ?: 0;

        // 列表手续费（符合筛选条件的）
        $extend['listCharge'] = \app\common\model\RepaySettle::where($where)
            ->sum('charge') ?: 0;

        // 总手续费（所有记录的）
        $extend['allCharge'] = \app\common\model\RepaySettle::where('merchant_id', $merchantId)
            ->sum('charge') ?: 0;

        // 列表USDT（符合筛选条件的）
        $extend['listUsdt'] = \app\common\model\RepaySettle::where($where)
            ->sum('usdt') ?: 0;

        // 总USDT（所有记录的）
        $extend['allUsdt'] = \app\common\model\RepaySettle::where('merchant_id', $merchantId)
            ->sum('usdt') ?: 0;

        return $extend;
    }

    /**
     * 获取订单日志
     * 
     * @ApiMethod (GET)
     * @ApiParams (name="orderno", type="string", required=false, description="订单号")
     * @ApiParams (name="status", type="integer", required=false, description="状态 0=失败 1=成功")
     * @ApiParams (name="createtime", type="array", required=false, description="创建时间范围")
     * @ApiParams (name="page", type="integer", required=false, description="页码，默认1")
     * @ApiParams (name="orderField", type="string", required=false, description="排序字段，默认id")
     */
    public function repayLog()
    {
        $data = $this->request->only(['orderno', 'status', 'createtime']);

        $rules = [
            'orderno|订单号' => 'alphaDash',
            'status|状态' => 'in:0,1',
            'createtime|创建时间' => 'array',
        ];

        $result = $this->validate($data, $rules);
        if ($result !== true) {
            $this->error($result);
        }

        $merchantId = $this->auth->merchant_id;

        // 构建查询条件
        $where = [
            'merchant_id' => $merchantId
        ];

        // 筛选订单号
        if (!empty($data['orderno'])) {
            $where['orderno'] = ['like', '%' . $data['orderno'] . '%'];
        }

        // 筛选状态
        if (isset($data['status']) && $data['status'] !== '') {
            $where['status'] = $data['status'];
        }

        // 处理创建时间范围
        if (isset($data['createtime']) && is_array($data['createtime']) && count($data['createtime']) == 2) {
            $timeRange = $this->parseTimeRange($data['createtime']);
            $where['createtime'] = ['between time', $timeRange];
        }

        // 分页参数
        $orderField = $this->request->param('orderField', 'id');
        $sort = 'DESC';
        $page = $this->request->param('page/d', 1);
        $pageLimit = 10;
        $offset = ($page - 1) * $pageLimit;

        // 获取总数
        $total = \app\common\model\RepayLog::where($where)->count();

        // 获取列表数据
        $list = \app\common\model\RepayLog::where($where)
            ->order($orderField, $sort)
            ->limit($offset, $pageLimit)
            ->select();

        // 设置可见字段（content 会自动反序列化，createtime 返回时间戳）
        $visibleFields = [
            'id', 'orderno', 'total_money', 'channel', 
            'content', 'result', 'status', 'status_text', 
            'ip', 'http', 'createtime'
        ];
        foreach ($list as $v) {
            $v->visible($visibleFields);
        }
        $list = collection($list)->toArray();

        $this->success('Data retrieved successfully', [
            'total' => $total,
            'list' => $list,
            'limit' => $pageLimit
        ]);
    }

}