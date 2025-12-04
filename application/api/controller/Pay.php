<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\model\ApiAccount;
use app\common\model\ApiChannel;
use app\common\model\ApiLog;
use app\common\model\RepayLog;
use app\common\model\ApiRule;
use app\common\model\ApiType;
use app\common\model\Bank;
use app\common\model\Order;
use app\common\model\User;
use app\common\model\UserLog;
use Carbon\Carbon;
use fast\Http;
use fast\Random;
use think\Cache;
use think\Db;
use think\Log;

class Pay extends Api
{


    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';


    /**
     * 引入后台控制器的traits
     */
    use \app\api\library\traits\Api;


    public function index()
    {


        //开始获取数据
        $data = $this->request->only([
            'merId', 'orderId', 'orderAmt', 'channel', 'desc', 'attch', 'smstyle', 'userId', 'ip', 'notifyUrl', 'returnUrl', 'nonceStr', 'sign', 'bankcode', 'gateway',
            'name','phone','email','productInfo','timestamp'
        ]);
        //记录请求日志（使用自定义 PAY_API 级别，生成 02_PAY_API.log）
        Log::record(urldecode(http_build_query($data)), 'PAY_API');
        
        //校验规则
        $rules = [
            'merId|商户号' => 'require|number',
            'orderId|订单号' => 'require|alphaDash|max:' . config('site.order_length'),
            'orderAmt|订单金额' => 'require|float|>:0',
            'channel|支付类型' => 'require|alphaDash|max:24',
            'name|姓名' => 'require|max:64',
            'phone|手机号' => 'require|regex:/^\d{6,15}$/',
            'email|电子邮件' => 'require|email|max:255',
            'productInfo|产品信息' => 'require|alphaDash|max:50',
            'timestamp|时间戳'=>'require|integer|gt:0',
            'userId|用户id' => 'requireIf:channel,ylkj',
            'ip|IP地址' => 'require|max:255',
            'notifyUrl|异步地址' => 'require|url',
            'returnUrl|同步地址' => 'require|url',
            'nonceStr|随机字符串' => 'require|max:32',
            'sign|签名' => 'require',
            'gateway|网关标志' => 'in:1',
            'bankcode|银行代码' => 'requireIf:gateway,1|alphaNum|max:16'
        ];

        $result = $this->validate($data, $rules);
        if ($result !== true) {
            Log::record('请求失败:' . $result, 'API');
            $this->error($result);
        }

        extract($data); //数组变量导出
        $user = User::getByMerchantId($merId);      //用户
        $reqip = $this->request->ip();              //获取请求过来的ip地址
        $order_no =  $orderId;              //商户订单号
        $sys_orderno = 'DS' . Order::createOrderNo();//商户订单号

        $domain = $this->request->domain();         //域名
        $style = '0';                               //订单类型
        //验证请求域名是否正确
        if (!checkApiDomain($domain, config('site.gateway'))) {
            $msg = '请求地址有误!';
            ApiLog::log($data, $msg);
            $this->error($msg);
        }

        //验证商户是否存在
        if (is_null($user)) {
            $msg = '商户号不存在!';
            ApiLog::log($data, $msg);
            $this->error($msg);
        }

        //查看商户是否锁定
        if ($user['status'] == 'hidden') {
            $msg = '商户已被锁定!';
            ApiLog::log($data, $msg);
            $this->error($msg);
        }

        //查看是否需要强制认证
        if (config('site.auth_switch') == '1') {
            if (empty($user->auth->status) || $user->auth->status != '1') {
                $msg = '请先完成认证!';
                ApiLog::log($data, $msg);
                $this->error($msg);
            }
        }

        if (empty($user['public_key'])) {
            $msg = '请设置开发公钥!';
            ApiLog::log($data, $msg);
            $this->error($msg);
        }

        //请求域名知否在列表中
        if (empty($user['req_url'])) {
            $msg = '请设置交易IP地址!';
            ApiLog::log($data, $msg);
            $this->error($msg);
        } else {
            if ($user['req_url'] != '*') {
                $allow_ips = explode(',', $user['req_url']);
                if (!in_array($reqip, $allow_ips)) {
                    $msg = '交易来源IP非法!';
                    ApiLog::log($data, $msg);
                    $this->error($msg);
                }
            }
        }
        $api_list = $user->getApiList2($orderAmt);
        if (empty($api_list[$channel])) {
            $msg = '该支付通道不存在，请联系商务!';
            ApiLog::log($data, $msg);
            $this->error($msg);
        }
      //  检查签名是否正确
        if (!$this->verifySign($data, $user['md5key'], $user['public_key'])) {
            $msg = '签名验证失败!';
            ApiLog::log($data, $msg);
            $this->error($msg);
        }
        //检查订单号是否重复
        if (!is_null(Order::getByOrderno($order_no))) {
            $msg = '订单号重复，请更换后重试!';
            ApiLog::log($data, $msg);
            $this->error($msg);
        }

        //同步返回地址不能带？
        if (strstr($returnUrl, '?') !== false) {
            $msg = '同步地址不能带问号，请确认。';
            ApiLog::log($data, $msg);
            $this->error($msg);
        }

        //检查请求类型是否合法
        $api_list = $user->getApiList2($orderAmt);

        if (empty($api_list[$channel])) {
            $msg = '该支付通道不存在，请联系商务!';
            ApiLog::log($data, $msg);
            $this->error($msg);
        }


        //检测交易ip是否在黑名单里面
        if ($this->ip_match($ip)) {
            $msg = '交易被禁止!';
            ApiLog::log($data, $msg);
            $this->error($msg);
        }
        //检测ip是否频繁拉取
        $cache_key = 'pullcount:' . $ip;
        $pull_count = Cache::get($cache_key);
        if (!$pull_count) {
            Cache::set($cache_key, 1, 60);
        } else {

            if ($pull_count >= config('site.ip_max_request')) {
                $msg = 'ip拉起次数过多';
                ApiLog::log($data, $msg);
                $this->error($msg);
            }
            Cache::inc($cache_key);
        }

        /**
         *
         *
         * 开始获取接口的信息
         *
         *
         */


        //网银如果传入bankcode 高于用户配置
        if ($channel == 'bank' && !empty($bankcode)) {
            $bankModel = Bank::get([
                'bankcode' => $bankcode,
                'status' => '1'
            ]);
            if (is_null($bankModel)) {
                $msg = '系统暂不支持该银行代码';
                ApiLog::log($data, $msg);
                $this->error($msg);
            }

            //是否设置了规则
            if (!empty($bankModel->api_rule_id)) {

                //修正规则
                $channel_info = ApiRule::getChannelInfo($bankModel->api_rule_id, false,true,$orderAmt);

                $apiTypeModel = ApiType::get(['code' => $channel]);
                $user_rate = $api_list['bank']['user_rate'];
                //规则信息下面需要
                $rule = [
                    'id' => $channel_info['info']['api_type_id'],
                    'account_id' => $channel_info['info']['api_account_ids']['id'],
                    'domain' => $apiTypeModel['domain'],
                    'account_weight' => $channel_info['info']['api_account_ids']['weight'],
                    'rule_type' => $channel_info['info']['type'], //规则
                    'rate' => $channel_info['rate_list'], //费率数组
                    'total' => $channel_info['total'],    //每天额度
                    'has' => $channel_info['has'],         //已用额度
                    'user_rate' => $user_rate
                ];
            } else {
                $rule = $api_list[$channel];
            }
        } else {
            $rule = $api_list[$channel];
        }

        $api_type_id = $rule['id'];
        $api_account_id = 0;
        $channel_rate = 0;
        $user_rate = 0;
        //如果单通道模式

        if ($rule['rule_type'] == '0') {

            if(empty($rule['account_id'][0])){
                $msg = '暂无可用通道，金额规则不匹配！';
                ApiLog::log($data, $msg);
                $this->error($msg);
            }


            $api_account_id = $rule['account_id'][0];
            $channel_rate = $rule['rate'][0];
            $user_rate = $rule['user_rate'] == '0' ? $channel_rate : $rule['user_rate'];
        }

        //顺序模式

        if ($rule['rule_type'] == '1') {

            $cache_key = 'ordercount:' . $user->merchant_id;
            $order_count = Cache::get($cache_key);
            if (!$order_count) {
                Cache::set($cache_key, 0, 86400); //一天过期
                $order_count = 0;
            }
            $account_index = $order_count % count($rule['account_id']);
            $api_account_id = $rule['account_id'][$account_index];
            $channel_rate = $rule['rate'][$account_index];
            $user_rate = $rule['user_rate'] == '0' ? $channel_rate : $rule['user_rate'];

            if (empty($gateway)) {
                //增加
                Cache::inc($cache_key);
            }
        }

        //随机轮询
        if ($rule['rule_type'] == '2') {
            $account_list = array_combine(array_values($rule['account_id']), array_values($rule['account_weight']));
            $api_account_id = Random::lottery($account_list);
            $rate_index = array_search($api_account_id, $rule['account_id']);
            $channel_rate = $rule['rate'][$rate_index];
            $user_rate = $rule['user_rate'] == '0' ? $channel_rate : $rule['user_rate'];
        }

        //获取到account 之后的逻辑
        $accountModel = ApiAccount::get($api_account_id);
        $upstreamModel = $accountModel->upstream;
        $channelModel = ApiChannel::get(['api_account_id' => $api_account_id, 'api_type_id' => $rule['id']]);
        if ($channelModel['status'] == '0') {
            $msg = '该通道已关闭，请联系商务';
            ApiLog::log($data, $msg);
            $this->error($msg);
        }
        /***
         * 检查限额
         */
        if($channelModel['daymoney'] > 0 ){
            $today = Carbon::now()->toDateString();
            if ($channelModel['today'] == $today && ($channelModel['todaymoney'] + $orderAmt) >= $channelModel['daymoney']) {
                $msg = '该通道额度不足，请联系商务';
                ApiLog::log($data, $msg);
                $this->error($msg);
            }
        }


        if ($channelModel['minmoney'] > 0 && $orderAmt < $channelModel['minmoney']) {
            $msg = '该通道最低充值金额为:' . $channelModel['minmoney'];
            ApiLog::log($data, $msg);
            $this->error($msg);
        }
        if ($channelModel['maxmoney'] > 0 && $orderAmt > $channelModel['maxmoney']) {
            $msg = '该通道最大充值金额为:' . $channelModel['maxmoney'];
            ApiLog::log($data, $msg);
            $this->error($msg);
        }

        //检查内充通道
        if ($accountModel['ifrecharge'] == '1' && $channel == 'bank') {
            $msg = '该通道为内充通道，禁止交易';
            ApiLog::log($data, $msg);
            $this->error($msg);
        }

        //组装参数
        $api_upstream_id = $upstreamModel->id;
        $api_upstream_code = $upstreamModel->code;
        $domain = config('site.gateway');   //返回的域名


        //获取域名
        if ($accountModel['domain']) {
            $pay_domain = $accountModel['domain'];
        } elseif ($rule['domain']) {
            $domain = $rule['domain'];
        }

        //如果接口跳转系统收银台
        if (empty($bankcode) && $channel == 'bank' && $channelModel['ifjump'] == '1') {
            $data['gateway'] = '1'; //收银台标识
            $gatewayUrl = url('/index/gateway', '', '', config('site.url')) . '?' . http_build_query($data);
            $this->success('请求成功!', [
                'payurl' => $gatewayUrl,           //支付地址
                'orderno' => $order_no,           //订单号
                'sysorderno' => ''                //系统订单号
            ]);
        }
        //提交给接口的参数
        $params = [
            'config' => $accountModel['params'], //配置参数
            'upstream_config' =>$upstreamModel['params'],//上游配置参数
            'pay_domain' => $pay_domain,
            'merId' => $merId,                //商户号
            'sys_orderno' => $sys_orderno,    //订单号
            'total_money' => $orderAmt,       //订单金额
            'channel' => $channel,            //通道代码
            'timestamp' => $timestamp,            //时间戳
            'name' => $name,            //姓名
            'phone' => $phone,            //手机号
            'email' => $email,            //邮件
            'productInfo' => $productInfo,            //产品信息
            'desc' => empty($desc) ? '' : $desc,                  //简单描述
            'bankcode' => empty($bankcode) ? '' : $bankcode,                                //银行代码
            'user_id' => empty($userId) ? '' : $userId,           //快捷模式必须
            'ip' => $ip,                                          //ip地址
            'domain' => $domain,                                 //地址信息
            'notify_url' => $domain . '/Pay/notify/code/' . $api_upstream_code.'/orderno/'.$sys_orderno,
            'return_url' => $domain . '/Pay/backurl/code/' . $api_upstream_code.'/orderno/'.$sys_orderno,
        ];
        $api = loadApi($api_upstream_code);
        $result = $api->pay($params);
        if ($result[0] == 0) {
            $msg = $result[1];
            ApiLog::log($data, $msg);
            $this->error($msg);
        }


        $payurl = $result[1];       //支付地址
        ApiLog::log($data, '', $payurl);


        //使用bc函数改写
        $rate_money = bcmul($orderAmt,$user_rate);
        $rate_money = bcdiv($rate_money,100);


        //用户获得多少
        $hava_money = bcsub($orderAmt,$rate_money);

        //给上游的钱
        $upstream_money = 0;
        //上游费率
        $upstream_rate = 0;
        if (!empty($channelModel['upstream_rate']) && $channelModel['upstream_rate'] > 0) {
            $upstream_money = bcmul($orderAmt,$channelModel['upstream_rate']);
            $upstream_money = bcdiv($upstream_money,100);
            $upstream_rate = $channelModel['upstream_rate'];
        }
        try{

            $data = [
                'merchant_id' => $merId,      //商户号
                'orderno' => $order_no,       //订单号 商户id+他自己的订单号
                'sys_orderno' => $sys_orderno,    //系统订单号
                'total_money' => sprintf('%.2f', $orderAmt),   //订单金额
                'have_money' => $hava_money,                            //获得金额
                'upstream_money' => $upstream_money,                      //上游金额
                'name' => $name,                      //姓名
                'email' => $email,                      //邮箱
                'phone' => $phone,                      //手机号
                'productInfo' => $productInfo,                      //产品信息
                'style' => $style,
                'rate' => $user_rate,             //用户费率
                'channel_rate' => $channel_rate,  //通道费率
                'upstream_rate' => $upstream_rate,//当前订单上游费率
                'api_upstream_id' => $api_upstream_id,    //上游id
                'api_account_id' => $api_account_id,      //账号id
                'api_type_id' => $api_type_id,            //上游类型
                'req_info' => urldecode(http_build_query($data)),  //请求报文
                'req_ip' => $ip
            ];

            Order::create($data);

        }catch (\Exception $e){

            ApiLog::log($data, $e->getMessage());
            $this->error('系统太火爆啦,请重新拉起~');

        }
        //如果是网关模式的话直接跳转
        if (!empty($gateway) && $gateway == '1') {
            return redirect($payurl);
        }

        //扫码跳转模式
        if (!empty($smstyle) && $smstyle == '1' && strstr($channel, 'sm')) {

            Cache::set('qr.'.$order_no,$payurl,3600);

            $payurl =  url('/index/ewm/show', ['orderno' => $order_no], '', config('site.url')) ;

//            $key = md5($order_no . $payurl . config('token.key'));
//            $payurl = url('/index/ewm/show', ['orderno' => $order_no], '', config('site.url')) . '?qr=' . urlencode($payurl) . '&key=' . $key;
        }


        //原始订单号
      //  $orderno = substr($order_no, strlen($merId));
        $this->success('请求成功!', [
            'payurl' => $payurl,              //支付地址
            'orderno' => $order_no,           //订单号
            'sysorderno' => $sys_orderno      //系统订单号
        ]);

    }


    /**商户余额查询*/
    public function balance(){
        $data = $this->request->only(['merId', 'sign']);
        //记录请求日志
        Log::record('商户余额查询接口：' . urldecode(http_build_query($data)), 'API');

        //校验规则
        $rules = [
            'merId|商户号' => 'require|number',
            'sign|签名' => 'require',
        ];
        $result = $this->validate($data, $rules);
        if ($result !== true) {
            Log::record('查询接口请求失败:' . $result, 'API');
            $this->error($result);
        }
        extract($data); //数组变量导出
        $user = User::getByMerchantId($merId);      //用户
        //验证商户是否存在
        if (is_null($user)) {
            $msg = '商户号不存在!';
            ApiLog::log($data, $msg);
            $this->error($msg);
        }
        //查看商户是否锁定
        if ($user['status'] == 'hidden') {
            $msg = '商户已被锁定!';
            ApiLog::log($data, $msg);
            $this->error($msg);
        }

        //查看是否需要强制认证
        if (config('site.auth_switch') == '1') {
            if (empty($user->auth->status) || $user->auth->status != '1') {
                $msg = '请先完成认证!';
                ApiLog::log($data, $msg);
                $this->error($msg);
            }
        }
        // 检查是否设置好公钥
        if (empty($user['public_key'])) {
            $msg = '请设置开发公钥!';
            ApiLog::log($data, $msg);
            $this->error($msg);
        }
        //检查签名是否正确
        if (!$this->verifySign($data, $user['md5key'], $user['public_key'])) {
            $msg = '签名验证失败!';
            ApiLog::log($data, $msg);
            $this->error($msg);
        }
        $returnData = [
            'merId' => $user['merchant_id'],
            'username' => $user['username'],
            'money' => $user['money'],          //账户余额
            'recharge' => $user['recharge'],   //已内充
            'withdrawal' => $user['withdrawal'] //已结算
        ];
        $returnData['sign'] = makeApiSign($returnData, $user['md5key'], config('site.private_key'));
        $this->success('查询成功', $returnData);

    }
    /**
     * 订单查询接口
     * merId 商户号
     * orderId 订单号
     * nonceStr 随机字符串
     */
    public function query()
    {

        $data = $this->request->only(['merId', 'orderId', 'nonceStr', 'sign']);

        //记录请求日志
        Log::record('订单查询接口：' . urldecode(http_build_query($data)), 'API');

        //校验规则
        $rules = [
            'merId|商户号' => 'require|number',
            'orderId|订单号' => 'require|alphaDash|max:64',
            'nonceStr|随机字符串' => 'require|max:32',
            'sign|签名' => 'require',
        ];

        $result = $this->validate($data, $rules);
        if ($result !== true) {
            Log::record('查询接口请求失败:' . $result, 'API');
            $this->error($result);
        }

        extract($data); //数组变量导出
        $user = User::getByMerchantId($merId);      //用户
        $reqip = $this->request->ip();              //获取请求过来的ip地址
        $order_no =  $orderId;              //商户订单号

        $domain = $this->request->domain();         //域名

        //验证请求域名是否正确
        if (!checkApiDomain($domain, config('site.gateway'))) {
            $msg = '请求地址有误!';
            ApiLog::log($data, $msg);
            $this->error($msg);
        }

        //验证商户是否存在
        if (is_null($user)) {
            $msg = '商户号不存在!';
            ApiLog::log($data, $msg);
            $this->error($msg);
        }

        //查看商户是否锁定
        if ($user['status'] == 'hidden') {
            $msg = '商户已被锁定!';
            ApiLog::log($data, $msg);
            $this->error($msg);
        }

        //查看是否需要强制认证
        if (config('site.auth_switch') == '1') {
            if (empty($user->auth->status) || $user->auth->status != '1') {
                $msg = '请先完成认证!';
                ApiLog::log($data, $msg);
                $this->error($msg);
            }
        }
       // 检查是否设置好公钥
        if (empty($user['public_key'])) {
            $msg = '请设置开发公钥!';
            ApiLog::log($data, $msg);
            $this->error($msg);
        }


        if (empty($user['req_url'])) {
            $msg = '请设置交易IP地址!';
            ApiLog::log($data, $msg);
            $this->error($msg);
        } else {
            if ($user['req_url'] != '*') {
                $allow_ips = explode(',', $user['req_url']);
                if (!in_array($reqip, $allow_ips)) {
                    $msg = '交易来源IP非法!';
                    ApiLog::log($data, $msg);
                    $this->error($msg);
                }
            }
        }

        //检查签名是否正确
        if (!$this->verifySign($data, $user['md5key'], $user['public_key'])) {
            $msg = '签名验证失败!';
            ApiLog::log($data, $msg);
            $this->error($msg);
        }

        //查看订单号是否存在
        $orderModel = Order::getByOrderno($order_no);
        //检查订单号是否重复
        if (is_null($orderModel)) {
            $msg = '订单号不存在!';
            ApiLog::log($data, $msg);
            $this->error($msg);
        }
        $returnData = [
            'merId' => $merId,
            'status' => $orderModel['status'] == '1' ? '1' : '0',
            'orderId' => $orderModel['orderno'],
            'sysOrderId' => $orderModel['sys_orderno'],
            'orderAmt' => $orderModel['total_money'],
            'nonceStr' => Random::alnum('32')
        ];
        $returnData['sign'] = makeApiSign($returnData, $user['md5key'], config('site.private_key'));

        ApiLog::log($data, '', 'api查询成功');

        $this->success('查询成功', $returnData);
    }


    /**
     * 代付查询
     */
    public function repayquery(){

        $data = $this->request->only(['merId', 'orderId', 'nonceStr', 'sign']);

        //记录请求日志
        Log::record('代付查询接口：' . urldecode(http_build_query($data)), 'API');

        //校验规则
        $rules = [
            'merId|商户号' => 'require|number',
            'orderId|订单号' => 'require|alphaDash|max:64',
            'nonceStr|随机字符串' => 'require|max:32',
            'sign|签名' => 'require',
        ];

        $result = $this->validate($data, $rules);
        if ($result !== true) {
            Log::record('查询接口请求失败:' . $result, 'API');
            $this->error($result);
        }


        extract($data); //数组变量导出
        $user = User::getByMerchantId($merId);      //用户
        $reqip = $this->request->ip();              //获取请求过来的ip地址
        $order_no = $orderId;              //商户订单号


        $domain = $this->request->domain();         //域名

        //验证请求域名是否正确
        if (!checkApiDomain($domain, config('site.gateway'))) {
            $msg = '请求地址有误!';
            ApiLog::log($data, $msg);
            $this->error($msg);
        }

        //验证商户是否存在
        if (is_null($user)) {
            $msg = '商户号不存在!';
            ApiLog::log($data, $msg);
            $this->error($msg);
        }

        //查看商户是否锁定
        if ($user['status'] == 'hidden') {
            $msg = '商户已被锁定!';
            ApiLog::log($data, $msg);
            $this->error($msg);
        }

        //查看是否需要强制认证
        if (config('site.auth_switch') == '1') {
            if (empty($user->auth->status) || $user->auth->status != '1') {
                $msg = '请先完成认证!';
                ApiLog::log($data, $msg);
                $this->error($msg);
            }
        }
        //检查是否设置好公钥
        if (empty($user['public_key'])) {
            $msg = '请设置开发公钥!';
            ApiLog::log($data, $msg);
            $this->error($msg);
        }


        if (empty($user['req_url'])) {
            $msg = '请设置交易IP地址!';
            ApiLog::log($data, $msg);
            $this->error($msg);
        } else {
            if ($user['req_url'] != '*') {
                $allow_ips = explode(',', $user['req_url']);
                if (!in_array($reqip, $allow_ips)) {
                    $msg = '交易来源IP非法!';
                    ApiLog::log($data, $msg);
                    $this->error($msg);
                }
            }
        }

        //检查签名是否正确
        if (!$this->verifySign($data, $user['md5key'], $user['public_key'])) {
            $msg = '签名验证失败!';
            ApiLog::log($data, $msg);
            $this->error($msg);
        }


        //查看订单号是否存在
        $payModel = \app\common\model\Pay::get([
            'orderno'=>$order_no,
        ]);
        //检查订单号是否重复
        if (is_null($payModel)) {
            $msg = '订单号不存在!';
            ApiLog::log($data, $msg);
            $this->error($msg);
        }


        $returnData = [
            'merId' => $merId,
            'status' => $payModel['status'],
            'orderId' => $orderId,
            'money' => $payModel['money'],
            'bank_code' => $payModel['bank_code'],
            'account' => $payModel['account'],
            'charge' => $payModel['charge'],
            'nonceStr' => Random::alnum('32')
        ];
        $returnData['sign'] = makeApiSign($returnData, $user['md5key'], config('site.private_key'));

        ApiLog::log($data, '', '代付查询成功');

        $this->success('查询成功', $returnData);


    }

    /**
     * 异步回调
     */
    public function notify()
    {

        $data = $this->request->only('code');

        $rules = [
            'code|编号' => 'require|alphaDash|max:24',
        ];

        $result = $this->validate($data, $rules);
        if ($result !== true) {
            $this->error($result);
        }

        $api = loadApi($data['code']);

        $result = $api->notify();

        return $result;

    }

    /**
     * 同步跳转
     */
    public function backurl()
    {

        $data = $this->request->only('code');

        $rules = [
            'code|编号' => 'require|alphaDash|max:24',
        ];

        $result = $this->validate($data, $rules);
        if ($result !== true) {
            $this->error($result);
        }

        $api = loadApi($data['code']);

        $result = $api->backurl();


        if ($result[0] == 1) {
            return redirect($result[1]);
        }
        return $result[1];
    }


    /**
     * 单笔代付接口
     */
    public function repay()
    {
        $data = $this->request->only(['merId','account', 'timestamp', 'orderId',  'email', 'caraddresstype','name','phone','caraddress','notifyUrl','sign','bic','money','bankCode','bankname']);
        Log::record(urldecode(http_build_query($data)), 'REPAY_API');
        $rules = [
            'money|提现金额' => 'require|float|>:0',
            'bankCode|银行通道编码' => 'require|in:BANK_IN',
            'orderId|代付订单号' => 'require|alphaDash|max:' . config('site.order_length'),
            'email|邮箱' => 'require|email',
            'name|姓名' => 'require|min:2|max:63',
            'bankname|银行名' => 'require|min:1|max:63',
            'phone|手机号' => ['require','regex' => '/^\d{8,}$/',],
            'bic|识别码' => function ($value) {
                // 允许为空
                if ($value === '' || $value === null) {
                    return true;
                }
                // 有值时必须是 3~16 位数字
                return preg_match('/^\d{3,16}$/', $value)
                    ? true
                    : '识别码必须为3-16位数字';
            },
        ];

        $result = $this->validate($data, $rules);
        if ($result !== true) {
            Log::record('代付请求失败:' . $result, 'API');
            $this->error($result);
        }
        extract($data); //数组变量导出
        $user = User::getByMerchantId($merId);      //用户
        $reqip = $this->request->ip();              //获取请求过来的ip地址

        $order_no = $orderId;              //商户订单号
        $domain = $this->request->domain();         //域名

        $style = '1';                               //订单类型


        //验证请求域名是否正确
        if (!checkApiDomain($domain, config('site.gateway'))) {
            $msg = '请求地址有误!';
            RepayLog::log($data, $msg);
            $this->error($msg);
        }

        //验证商户是否存在
        if (is_null($user)) {
            $msg = '商户号不存在!';
            RepayLog::log($data, $msg);
            $this->error($msg);
        }

        //查看商户是否锁定
        if ($user['status'] == 'hidden') {
            $msg = '商户已被锁定!';
            RepayLog::log($data, $msg);
            $this->error($msg);
        }

        //查看是否需要强制认证
        if (config('site.auth_switch') == '1') {
            if (empty($user->auth->status) || $user->auth->status != '1') {
                $msg = '请先完成认证!';
                RepayLog::log($data, $msg);
                $this->error($msg);
            }
        }
        //检查是否设置好公钥
        if (empty($user['public_key'])) {
            $msg = '请设置开发公钥!';
            RepayLog::log($data, $msg);
            $this->error($msg);
        }

        //请求域名知否在列表中
        if (empty($user['req_url'])) {
            $msg = '请设置交易IP地址!';
            RepayLog::log($data, $msg);
            $this->error($msg);
        } else {
            if ($user['req_url'] != '*') {
                $allow_ips = explode(',', $user['req_url']);
                if (!in_array($reqip, $allow_ips)) {
                    $msg = '交易来源IP非法!';
                    RepayLog::log($data, $msg);
                    $this->error($msg);
                }
            }
        }

        //检查签名是否正确
        if (!$this->verifySign($data, $user['md5key'], $user['public_key'])) {
            $msg = '签名验证失败!';
            RepayLog::log($data, $msg);
            $this->error($msg);
        }

        //检查订单号是否重复
        if (!is_null(\app\common\model\RepayOrder::get(['orderno'=>$order_no]))) {
            $msg = '订单号重复，请更换后重试!';
            RepayLog::log($data, $msg);
            $this->error($msg);
        }

        //查看是否具有api代付的权限
        if ($user['ifapirepay'] != '1') {
            $msg = 'API代付权限未开通!';
            RepayLog::log($data, $msg);
            $this->error($msg);
        }


        //判断是否在代付时间内
        if (!checkRepayTime()) {
            $msg = '请在提现允许时间段内操作!';
            RepayLog::log($data, $msg);
            $this->error($msg);
        }


        //开始计算用户余额够不够
        $userModel = $user;
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
        //手续费
        $commission = $userModel->commission($money);
        $needMoney = bcadd($money, $commission, 2);

        //判断余额是否足够
        if (!is_numeric($money) || $money <= 0) {

            $msg = '请填写正确的金额!';
            RepayLog::log($data, $msg);
            $this->error($msg);

        }
        if ($needMoney > $userMoney) {
            $msg = '支付金额不足,当前需要' . $needMoney . '越南盾,手续费：' . $commission . '越南盾！';
            RepayLog::log($data, $msg);
            $this->error($msg);
        }

        //检查最小提现金额
        if ($money < config('site.minpay')) {
            $msg = '支付金额小于最小要求金额！最低支付' . config('site.minpay') . '越南盾';
            RepayLog::log($data, $msg);
            $this->error($msg);
        }

        // 原始请求数据用于日志记录与 req_info 存储，避免后面覆盖 $data
        $requestLogData = $data;

        //开始事务
        Db::startTrans();
        try {
            // 订单表写入数据
            $orderData = [
                'merchant_id' => $merchant_id,
                'orderno' => $order_no,
                'style' => $style,
                'account'=>$account,
                'money' => $money,
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'bank_code' =>$bankCode,
                'bankname' => $bankname,
                'status' => '0',
                'msg' =>'商户发起代付请求',
                'daifustatus' => '0',
                'charge' => $commission,
                'bic' =>empty($bic) ? '' : $bic,
                // 这里记录的是原始请求参数
                'req_info' => urldecode(http_build_query($requestLogData)),
                'req_ip' => $this->request->ip(),
            ];
            $payModel = \app\common\model\RepayOrder::create($orderData);
            //更新用户金额
            $userModel->setInc('withdrawal', $needMoney);
            //资金变动
            User::money(-$needMoney, $userModel->id, '提现：' . $money . '越南盾，手续费：' . $commission . '越南盾', $payModel['orderno'], '2');
            //写入用户日志表
            UserLog::addLog($merchant_id, '申请提现：' . $money . '越南盾，手续费：' . $commission . '越南盾');
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            Log::record('api代付异常:' . $e->getMessage());
            $this->error($e->getMessage());
        }
        //检查是否风控
        $risk_control=$userModel->RiskControl($money,$order_no);
        ///Notify::repay();声音合成软件暂时还没有好
        //判断自动代付提交
        if ($userModel['ifdaifuauto'] == '1'&&$risk_control['status'] === false) {
            if ($userModel['daifuid'] > 0) {
                $result=  \app\common\model\RepayOrder::dfSubmit($payModel->id, $userModel['daifuid']);
                if ($result[0] == 0) {
                    // 原有日志
                    Log::record('自动代付异常,商户号:' . $merchant_id . ' 上游id:' . $userModel['daifuid'] . ' 异常信息:' . $result[1], 'REPAY_ERROR');

                    // 新增：通知 TG 中转群
                    try {
                        $payload = [
                            'merchant_id'   => $merchant_id,
                            'daifuid'       => $userModel['daifuid'],
                            'order_id'      => $order_no,
                            'error_message' => $result[1],
                        ];
                        $url = 'http://127.0.0.1:9000/repay_error_notify';
                        Http::send_json($url, $payload);
                    } catch (\Throwable $e) {
                        Log::record('自动代付异常通知TG失败: ' . $e->getMessage(), 'REPAY_ERROR');
                    }
                }
            }
        }
        $returnData = [
            'merId' => $merId,
            'orderNo' => $order_no,
        ];
        // 日志里存入原始请求参数 + 成功结果
        RepayLog::log($requestLogData, '', '代付申请成功');
        $this->success('代付申请成功!', $returnData);
    }


    /**
     * 代付异步通知
     */
    public function repaynotify()
    {

        $data = $this->request->only('code');

        $rules = [
            'code|编号' => 'require|alphaDash|max:24',
        ];

        $result = $this->validate($data, $rules);
        if ($result !== true) {
            $this->error($result);
        }

        $api = loadApi($data['code']);

        $result = $api->repaynotify();

        return $result;

    }

    /**
     * 原样输出html标签
     */
    public function html()
    {

        $orderno = $this->request->param('orderno');

        if(empty($orderno)){
            $this->error('订单不存在!');
        }

        $content = Cache::get('content.'.$orderno);

        if(!$content){
            $this->error('内容不存在!');
        }
        exit($content);
    }


    /**
     * 跳转
     */
    public function url()
    {

        $orderno = $this->request->param('orderno');

        if(empty($orderno)){
            $this->error('订单不存在!');
        }

        $content = Cache::get('url.'.$orderno);


        if(!$content){
            $this->error('内容不存在!');
        }
        return redirect($content);
    }

}