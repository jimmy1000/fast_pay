<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\library\Ems;
use app\common\library\Sms;
use app\common\model\ApiAccount;
use app\common\model\ApiChannel;
use app\common\model\ApiLog;
use app\common\model\ApiType;
use app\common\model\Order;
use Carbon\Carbon;
use fast\Random;
use think\Cache;
use think\Config;
use think\Validate;
use app\common\model\UserLog;
use google\GoogleAuthenticator;
/**
 * 会员接口
 */
class User extends Api
{
    protected $noNeedLogin = ['login', 'mobilelogin', 'register', 'resetpwd', 'changeemail', 'changemobile', 'third'];
    protected $noNeedRight = '*';

    public function _initialize()
    {
        parent::_initialize();

        if (!Config::get('fastadmin.usercenter')) {
            $this->error(__('User center already closed'));
        }

    }

    /**
     * 会员中心
     */
    public function index()
    {
        $this->success('', ['welcome' => $this->auth->nickname]);
    }

    /**
     * 会员登录
     *
     * @ApiMethod (POST)
     * @ApiParams (name="account", type="string", required=true, description="账号(商户号或用户名)")
     * @ApiParams (name="password", type="string", required=true, description="密码")
     * @ApiParams (name="googlemfa", type="string", required=true, description="Google MFA验证码")
     */
    public function login()
    {
        $account = $this->request->post('account');
        $password = $this->request->post('password');
        $googlemfa = $this->request->post('googlemfa', '');
        if (!$account || !$password) {
            $this->error(__('Invalid parameters'));
        }
        if (!$googlemfa) {
            $this->error(__('Google MFA code is required'));
        }
        $ret = $this->auth->login($account, $password, $googlemfa);
        if ($ret) {
            UserLog::addLog($this->auth->merchant_id, '用户登录');
            $data = ['userinfo' => $this->auth->getUserinfo()];
            $this->success(__('Logged in successful'), $data);
        } else {
            $this->error($this->auth->getError());
        }
    }
    /**
     * 用户信息包前端权限列表
     */
    public function info()
    {


        $user_info = $this->auth->getUserinfo();
        $user_info['avatar'] = cdnurl($user_info['avatar'], true);

        //是否设置了提现密码
        $user_info['setPayPassword'] = empty($user_info['paypassword']) ? false : true;
        $roles = $user_info['group_id'] == 2 ? 'agency' : 'member'; //代理还是会员
        $user_info['settle'] = $this->auth->getUser()->settle();
        $user_info['payrate_percent'] = $this->auth->getUser()->payrate_percent;
        $user_info['payrate_each'] = $this->auth->getUser()->payrate_each;
        $user_info['freezeMoney'] = $this->auth->getUser()->getFreezeMoney();   //用户冻结的金额
        $user_info['availMoney'] = bcsub($user_info['money'],$user_info['freezeMoney'],'2');
        $data = [
            'userinfo' => $user_info,
            'roles' => [$roles]
        ];
        $this->success('', $data);
    }
    /**
     * 手机验证码登录
     *
     * @ApiMethod (POST)
     * @ApiParams (name="mobile", type="string", required=true, description="手机号")
     * @ApiParams (name="captcha", type="string", required=true, description="验证码")
     */
    public function mobilelogin()
    {
        $mobile = $this->request->post('mobile');
        $captcha = $this->request->post('captcha');
        if (!$mobile || !$captcha) {
            $this->error(__('Invalid parameters'));
        }
        if (!Validate::regex($mobile, "^1\d{10}$")) {
            $this->error(__('Mobile is incorrect'));
        }
        if (!Sms::check($mobile, $captcha, 'mobilelogin')) {
            $this->error(__('Captcha is incorrect'));
        }
        $user = \app\common\model\User::getByMobile($mobile);
        if ($user) {
            if ($user->status != 'normal') {
                $this->error(__('Account is locked'));
            }
            //如果已经有账号则直接登录
            $ret = $this->auth->direct($user->id);
        } else {
            $ret = $this->auth->register($mobile, Random::alnum(), '', $mobile, []);
        }
        if ($ret) {
            Sms::flush($mobile, 'mobilelogin');
            $data = ['userinfo' => $this->auth->getUserinfo()];
            $this->success(__('Logged in successful'), $data);
        } else {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 注册会员
     *
     * @ApiMethod (POST)
     * @ApiParams (name="username", type="string", required=true, description="用户名")
     * @ApiParams (name="password", type="string", required=true, description="密码")
     * @ApiParams (name="email", type="string", required=true, description="邮箱")
     * @ApiParams (name="mobile", type="string", required=true, description="手机号")
     * @ApiParams (name="code", type="string", required=true, description="验证码")
     */
    public function register()
    {
        $username = $this->request->post('username');
        $password = $this->request->post('password');
        $email = $this->request->post('email');
        $mobile = $this->request->post('mobile');
        $code = $this->request->post('code');
        if (!$username || !$password) {
            $this->error(__('Invalid parameters'));
        }
        if ($email && !Validate::is($email, "email")) {
            $this->error(__('Email is incorrect'));
        }
        if ($mobile && !Validate::regex($mobile, "^1\d{10}$")) {
            $this->error(__('Mobile is incorrect'));
        }
        $ret = Sms::check($mobile, $code, 'register');
        if (!$ret) {
            $this->error(__('Captcha is incorrect'));
        }
        $ret = $this->auth->register($username, $password, $email, $mobile, []);
        if ($ret) {
            $data = ['userinfo' => $this->auth->getUserinfo()];
            $this->success(__('Sign up successful'), $data);
        } else {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 退出登录
     * @ApiMethod (POST)
     */
    /**
     * 注销登录
     */
    public function logout()
    {
        UserLog::addLog($this->auth->merchant_id, '用户登出');
        $this->auth->logout();
        $this->success(__('Logout successful'));
    }
    /**
     * 修改会员个人信息
     *
     * @ApiMethod (POST)
     * @ApiParams (name="avatar", type="string", required=true, description="头像地址")
     * @ApiParams (name="username", type="string", required=true, description="用户名")
     * @ApiParams (name="nickname", type="string", required=true, description="昵称")
     * @ApiParams (name="bio", type="string", required=true, description="个人简介")
     */
    public function profile()
    {
        $user = $this->auth->getUser();
        $username = $this->request->post('username');
        $nickname = $this->request->post('nickname');
        $bio = $this->request->post('bio');
        $avatar = $this->request->post('avatar', '', 'trim,strip_tags,htmlspecialchars');
        if ($username) {
            $exists = \app\common\model\User::where('username', $username)->where('id', '<>', $this->auth->id)->find();
            if ($exists) {
                $this->error(__('Username already exists'));
            }
            $user->username = $username;
        }
        if ($nickname) {
            $exists = \app\common\model\User::where('nickname', $nickname)->where('id', '<>', $this->auth->id)->find();
            if ($exists) {
                $this->error(__('Nickname already exists'));
            }
            $user->nickname = $nickname;
        }
        $user->bio = $bio;
        $user->avatar = $avatar;
        $user->save();
        $this->success();
    }

    /**
     * 修改邮箱
     *
     * @ApiMethod (POST)
     * @ApiParams (name="email", type="string", required=true, description="邮箱")
     * @ApiParams (name="captcha", type="string", required=true, description="验证码")
     */
    public function changeemail()
    {
        $user = $this->auth->getUser();
        $email = $this->request->post('email');
        $captcha = $this->request->post('captcha');
        if (!$email || !$captcha) {
            $this->error(__('Invalid parameters'));
        }
        if (!Validate::is($email, "email")) {
            $this->error(__('Email is incorrect'));
        }
        if (\app\common\model\User::where('email', $email)->where('id', '<>', $user->id)->find()) {
            $this->error(__('Email already exists'));
        }
        $result = Ems::check($email, $captcha, 'changeemail');
        if (!$result) {
            $this->error(__('Captcha is incorrect'));
        }
        $verification = $user->verification;
        $verification->email = 1;
        $user->verification = $verification;
        $user->email = $email;
        $user->save();

        Ems::flush($email, 'changeemail');
        $this->success();
    }

    /**
     * 修改手机号
     *
     * @ApiMethod (POST)
     * @ApiParams (name="mobile", type="string", required=true, description="手机号")
     * @ApiParams (name="captcha", type="string", required=true, description="验证码")
     */
    public function changemobile()
    {
        $user = $this->auth->getUser();
        $mobile = $this->request->post('mobile');
        $captcha = $this->request->post('captcha');
        if (!$mobile || !$captcha) {
            $this->error(__('Invalid parameters'));
        }
        if (!Validate::regex($mobile, "^1\d{10}$")) {
            $this->error(__('Mobile is incorrect'));
        }
        if (\app\common\model\User::where('mobile', $mobile)->where('id', '<>', $user->id)->find()) {
            $this->error(__('Mobile already exists'));
        }
        $result = Sms::check($mobile, $captcha, 'changemobile');
        if (!$result) {
            $this->error(__('Captcha is incorrect'));
        }
        $verification = $user->verification;
        $verification->mobile = 1;
        $user->verification = $verification;
        $user->mobile = $mobile;
        $user->save();

        Sms::flush($mobile, 'changemobile');
        $this->success();
    }

    /**
     * 第三方登录
     *
     * @ApiMethod (POST)
     * @ApiParams (name="platform", type="string", required=true, description="平台名称")
     * @ApiParams (name="code", type="string", required=true, description="Code码")
     */
    public function third()
    {
        $url = url('user/index');
        $platform = $this->request->post("platform");
        $code = $this->request->post("code");
        $config = get_addon_config('third');
        if (!$config || !isset($config[$platform])) {
            $this->error(__('Invalid parameters'));
        }
        $app = new \addons\third\library\Application($config);
        //通过code换access_token和绑定会员
        $result = $app->{$platform}->getUserInfo(['code' => $code]);
        if ($result) {
            $loginret = \addons\third\library\Service::connect($platform, $result);
            if ($loginret) {
                $data = [
                    'userinfo'  => $this->auth->getUserinfo(),
                    'thirdinfo' => $result
                ];
                $this->success(__('Logged in successful'), $data);
            }
        }
        $this->error(__('Operation failed'), $url);
    }

    /**
     * 修改密码
     *
     * @ApiMethod (POST)
     * @ApiParams (name="old", type="string", required=true, description="旧密码")
     * @ApiParams (name="password", type="string", required=true, description="新密码")
     * @ApiParams (name="confirmPassword", type="string", required=true, description="确认密码")
     * @ApiParams (name="googlemfa", type="string", required=true, description="Google MFA验证码")
     */
    public function changePassword()
    {
        $rules = [
            'old|Old Password' => 'require',
            'password|New Password' => 'require|regex:\S{6,30}',
            'confirmPassword|Confirm Password' => 'require|confirm:password',
            'googlemfa|Google MFA Code' => 'require',
        ];

        $message = [
            'old.require' => 'Old password is required',
            'password.require' => 'New password is required',
            'password.regex' => 'Password must be 6 to 30 characters',
            'confirmPassword.require' => 'Confirm password is required',
            'confirmPassword.confirm' => 'Confirm password does not match',
            'googlemfa.require' => 'Google MFA code is required',
        ];

        $data = [
            'old' => $this->request->post('old', ''),
            'password' => $this->request->post('password', ''),
            'confirmPassword' => $this->request->post('confirmPassword', ''),
            'googlemfa' => $this->request->post('googlemfa', ''),
        ];

        $result = $this->validate($data, $rules, $message);
        if ($result !== true) {
            $this->error($result);
        }

        // 验证Google MFA
        $user = $this->auth->getUser();
        if (!google_verify_code($user, $data['googlemfa'])) {
            $this->error(__('Google MFA code is incorrect'));
        }

        // 修改密码
        if ($this->auth->changepwd($data['password'], $data['old'])) {
            UserLog::addLog($this->auth->merchant_id, '修改登录密码');
            $this->success(__('Change password successful'));
        } else {
            $this->error(__('Change password failed: %s', $this->auth->getError()));
        }
    }

    /**
     * 设置用户的支付密码
     * 
     * @ApiMethod (POST)
     * @ApiParams (name="old", type="string", required=false, description="旧支付密码（修改时必填）")
     * @ApiParams (name="password", type="string", required=true, description="新支付密码（6-16位）")
     * @ApiParams (name="confirmPassword", type="string", required=true, description="确认支付密码")
     * @ApiParams (name="googlemfa", type="string", required=true, description="Google MFA验证码")
     */
    public function setPayPassword()
    {
        $rules = [
            'old|Old Payment Password' => 'length:6,16',
            'password|Payment Password' => 'require|regex:\S{6,16}',
            'confirmPassword|Confirm Payment Password' => 'require|confirm:password',
            'googlemfa|Google MFA Code' => 'require',
        ];

        $message = [
            'old.length' => 'Old payment password must be 6 to 16 characters',
            'password.require' => 'Payment password is required',
            'password.regex' => 'Payment password must be 6 to 16 characters',
            'confirmPassword.require' => 'Confirm payment password is required',
            'confirmPassword.confirm' => 'Confirm payment password does not match',
            'googlemfa.require' => 'Google MFA code is required',
        ];

        $data = [
            'old' => $this->request->post('old', ''),
            'password' => $this->request->post('password', ''),
            'confirmPassword' => $this->request->post('confirmPassword', ''),
            'googlemfa' => $this->request->post('googlemfa', ''),
        ];

        $result = $this->validate($data, $rules, $message);
        if ($result !== true) {
            $this->error($result);
        }

        // 验证Google MFA
        $user = $this->auth->getUser();
        if (!google_verify_code($user, $data['googlemfa'])) {
            $this->error(__('Google MFA code is incorrect'));
        }

        $user_info = $this->auth->getUserinfo();
        $hasPayPassword = !empty($user_info['paypassword']);

        //如果没有设置支付密码，则设置
        if (!$hasPayPassword) {
            \app\common\model\User::setPayPassword($data['password'], $this->auth->id);
            UserLog::addLog($this->auth->merchant_id, '初始化设置支付密码');
            $this->success(__('Set payment password successful, please keep it safe!'));
        }

        //如果有设置先看看原支付密码是否正确
        if (empty($data['old'])) {
            $this->error(__('Old payment password is required'));
        }

        //检测密码是否正确
        if (!\app\common\model\User::verifyPayPassword($data['old'], $this->auth->id)) {
            $this->error(__('Old payment password is incorrect'));
        }

        \app\common\model\User::setPayPassword($data['password'], $this->auth->id);

        UserLog::addLog($this->auth->merchant_id, '修改支付密码');

        $this->success(__('Set payment password successful, please keep it safe!'));
    }

    /**
     * 获取谷歌验证码二维码
     * 
     * @ApiMethod (GET)
     */
    public function getGoogleQrcode()
    {
        $user = $this->auth->getUser();
        
        // 如果已经绑定，返回错误
        if ($user->googlebind) {
            $this->error(__('Google MFA is already bound'));
        }
        
        // 生成密钥和二维码
        $secret = GoogleAuthenticator::generateSecret();
        $siteName = config('site.name') ?: 'FastPay';
        $qrUrl = GoogleAuthenticator::getQRCodeImageUrl(
            $user->username . '@' . $user->merchant_id,
            $secret,
            $siteName,
            200
        );
        
        $this->success(__('Get QR code successful'), [
            'secret' => $secret,
            'qrUrl' => $qrUrl,
            'qrCodeUrl' => GoogleAuthenticator::getQRCodeUrl(
                $user->username . '@' . $user->merchant_id,
                $secret,
                $siteName
            )
        ]);
    }

    /**
     * 绑定谷歌验证码
     * 
     * @ApiMethod (POST)
     * @ApiParams (name="secret", type="string", required=true, description="密钥（16位）")
     * @ApiParams (name="code", type="string", required=true, description="Google MFA验证码（6位）")
     */
    public function bindGoogleQrcode()
    {
        $rules = [
            'secret|Secret' => 'require|length:16',
            'code|Google MFA Code' => 'require|number|length:6'
        ];

        $message = [
            'secret.require' => 'Secret is required',
            'secret.length' => 'Secret must be 16 characters',
            'code.require' => 'Google MFA code is required',
            'code.number' => 'Google MFA code must be numeric',
            'code.length' => 'Google MFA code must be 6 digits',
        ];

        $data = [
            'secret' => $this->request->post('secret', ''),
            'code' => $this->request->post('code', ''),
        ];

        $result = $this->validate($data, $rules, $message);
        if ($result !== true) {
            $this->error($result);
        }

        $user = $this->auth->getUser();
        
        // 如果已经绑定，返回错误
        if ($user->googlebind) {
            $this->error(__('Google MFA is already bound'));
        }

        // 验证输入的code是否正确
        if (!GoogleAuthenticator::verifyCode($data['secret'], $data['code'])) {
            $this->error(__('Google MFA code is incorrect'));
        }

        // 给用户绑定上验证码
        \app\common\model\User::setGoogleSecret($data['secret'], $this->auth->id);
        UserLog::addLog($this->auth->merchant_id, '绑定谷歌验证器');
        
        $this->success(__('Bind Google MFA successful'));
    }

    /**
     * 解绑谷歌验证码
     * 
     * @ApiMethod (POST)
     * @ApiParams (name="code", type="string", required=true, description="Google MFA验证码（6位）")
     */
    public function unbindGoogleQrcode()
    {
        $rules = [
            'code|Google MFA Code' => 'require|number|length:6'
        ];

        $message = [
            'code.require' => 'Google MFA code is required',
            'code.number' => 'Google MFA code must be numeric',
            'code.length' => 'Google MFA code must be 6 digits',
        ];

        $data = [
            'code' => $this->request->post('code', ''),
        ];

        $result = $this->validate($data, $rules, $message);
        if ($result !== true) {
            $this->error($result);
        }

        // 重新从数据库获取最新的用户数据，避免缓存问题
        // 绑定后立即解绑时，$this->auth->getUser() 可能还是旧数据
        $user = \app\common\model\User::get($this->auth->id);
        if (!$user) {
            $this->error(__('User not found'));
        }
        
        // 如果未绑定，返回错误
        if (!$user->googlebind || empty($user->googlesecret)) {
            $this->error(__('Google MFA is not bound'));
        }

        // 验证输入的code是否正确
        if (!GoogleAuthenticator::verifyCode($user->googlesecret, $data['code'])) {
            $this->error(__('Google MFA code is incorrect'));
        }

        // 解绑谷歌验证码
        \app\common\model\User::clearGoogleSecret($this->auth->id);
        UserLog::addLog($this->auth->merchant_id, '解绑谷歌验证器');
        
        $this->success(__('Unbind Google MFA successful'));
    }
    /**
     * 获取用户的操作日志
     */
    public function logs()
    {

        //查询条件
        $where = [
            'merchantid' => $this->auth->merchant_id
        ];
        //排序字段
        $orderField = $this->request->param('orderField', 'id');
        $sort = 'DESC';
        //分页字段
        $page = $this->request->param('page/d', 1);
        $pageLimit = 10;    //每页显示10条数据
        $offset = ($page - 1) * $pageLimit;

        //数据总数
        $total = UserLog::where($where)->count();
        $list = UserLog::where($where)
            ->order($orderField, $sort)
            ->limit($offset, $pageLimit)
            ->field(['ip', 'createtime', 'content'])
            ->select();
        $list = collection($list)->toArray();

        $this->success('获取数据成功！', [
            'total' => $total,
            'list' => $list,
            'limit' => $pageLimit
        ]);
    }
    /**
     * 会员充值
     * 
     * @ApiMethod (POST)
     * @ApiParams (name="money", type="float", required=true, description="充值金额")
     * @ApiParams (name="channel", type="string", required=true, description="支付通道")
     */
    public function recharge()
    {
        $rules = [
            'money|Recharge Amount' => 'require|number|egt:' . \config('site.minrecharge'),
            'channel|Payment Channel' => 'require|alphaDash|max:24',
        ];

        $message = [
            'money.require' => 'Recharge amount is required',
            'money.number' => 'Recharge amount must be a number',
            'money.egt' => 'Recharge amount must be at least ' . \config('site.minrecharge'),
            'channel.require' => 'Payment channel is required',
            'channel.alphaDash' => 'Payment channel format is invalid',
            'channel.max' => 'Payment channel length cannot exceed 24 characters',
        ];

        $data = [
            'money' => $this->request->post('money', ''),
            'channel' => $this->request->post('channel', ''),
        ];

        $result = $this->validate($data, $rules, $message);
        if ($result !== true) {
            $this->error($result);
        }

        $user = $this->auth->getUser();
        $merId = $user['merchant_id'];
        $orderId = 'CZ' . time();                     //充值单号
        $reqip = $this->request->ip();              //获取请求过来的ip地址
        $order_no = $merId . $orderId;              //商户订单号
        $sys_orderno = 'CZ' . Order::createOrderNo();      //系统订单号
        $style = '1';                               //充值订单
        $returnUrl = url('/index/test/backurl', '', '', true);                            //同步跳转地址
        $notifyUrl = url('/index/test/notify', '', '', true);                             //异步通知地址
        $channel = $data['channel'];    //支付通道
        $orderAmt = $data['money'];     //金额
        //需要的请求信息
        $data = [
            'merId'=>$merId,
            'orderId'=>$orderId,
            'orderAmt'=>$orderAmt,
            'channel'=>$channel,
            'desc'=>'recharge',
            'notifyUrl'=>$notifyUrl,
            'returnUrl'=>$returnUrl
        ];


        //查看商户是否锁定
        if ($user['status'] == 'hidden') {
            $msg = '商户已被锁定!';
            ApiLog::log($data,$msg);
            $this->error($msg);
        }

        //查看是否需要强制认证
        if (config('site.auth_switch') == '1') {
            if (empty($user->auth->status) || $user->auth->status != '1') {
                $msg = '请先完成认证!';
                ApiLog::log($data,$msg);
                $this->error($msg);
            }
        }

        //检查订单号是否重复
        if (!is_null(Order::getByOrderno($order_no))) {
            $msg = '订单号重复，请更换后重试!';
            ApiLog::log($data,$msg);
            $this->error($msg);
        }

        if (strstr($returnUrl, '?') !== false) {
            $msg = '同步地址不能带问号，请确认。';
            ApiLog::log($data,$msg);
            $this->error($msg);
        }

        //检查请求类型是否合法
//        $api_list = $user->getApiList2();

        $api_list = $user->getApiList2($orderAmt);

        if (empty($api_list[$channel])) {
            $msg = __('Payment channel does not exist, please contact business');
            ApiLog::log($data, $msg);
            $this->error($msg);
        }

        // 获取通道规则
        $rule = $api_list[$channel];

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
            //增加
            Cache::inc($cache_key);
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


        /***
         * 检查限额
         */
        $today = Carbon::now()->toDateString();
        if($channelModel['today'] == $today && ($channelModel['todaymoney'] + $orderAmt) >= $channelModel['daymoney']){
            $msg = '该通道额度不足，请联系商务';
            ApiLog::log($data,$msg);
            $this->error($msg);
        }
        if ($channelModel['minmoney'] > 0 && $orderAmt < $channelModel['minmoney']) {
            $msg = '该通道最低充值金额为:' . $channelModel['minmoney'];
            ApiLog::log($data,$msg);
            $this->error($msg);
        }
        if ($channelModel['maxmoney'] > 0 && $orderAmt > $channelModel['maxmoney']) {
            $msg = '该通道最大充值金额为:' . $channelModel['maxmoney'];
            ApiLog::log($data,$msg);
            $this->error($msg);
        }



        //组装参数
        $api_upstream_id = $upstreamModel->id;

        $api_upstream_code = $upstreamModel->code;
        $domain = config('site.gateway');   //返回的域名
        if ($accountModel['domain']) {
            $pay_domain = $accountModel['domain'];
        } elseif ($rule['domain']) {
            $pay_domain = $rule['domain'];
        }
        //提交给接口的参数
        $params = [
            'config' => $accountModel['params'], //配置参数
            'pay_domain' => $pay_domain,
            'merId' => $merId,                //商户号
            'sys_orderno' => $sys_orderno,    //订单号
            'total_money' => $orderAmt,       //订单金额
            'channel' => $channel,            //通道代码
            'timestamp' => time(),//shijianchuo
            'name' => 'James Kanny',
            'phone' => '6130829630',
            'email' => 'wXg2RDUZzGzk@cwgsy.net',
            'productInfo' => 'XXXXXXX',
            'desc' => empty($desc) ? '' : $desc,                  //简单描述
            'user_id' => empty($userId) ? '' : $userId,              //快捷模式必须
            'ip' => $reqip,                                        //ip地址
            'notify_url' => $domain . '/Pay/notify/code/' . $api_upstream_code,
            'return_url' => $domain . '/Pay/return/code/' . $api_upstream_code,
        ];

        $api = loadApi($api_upstream_code);

        $result = $api->pay($params);
        if ($result[0] == 0) {
            $msg = $result[1];
            ApiLog::log($data,$msg);
            $this->error($msg);
        }
        $payurl = $result[1];       //支付地址
        ApiLog::log($data,'',$payurl);

        //使用bc函数改写
        $rate_money = bcmul($orderAmt,$user_rate);
        $rate_money = bcdiv($rate_money,100);
        //用户获得多少
        $hava_money = bcsub($orderAmt,$rate_money);

        //给上游的钱
        $upstream_money = 0;
        if (!empty($channelModel['upstream_rate']) && $channelModel['upstream_rate'] > 0) {
            $upstream_money = bcmul($orderAmt,$channelModel['upstream_rate']);
            $upstream_money = bcdiv($upstream_money,100);
        }

        $data = [
            'merchant_id' => $merId,      //商户号
            'orderno' => $order_no,       //订单号 商户id+他自己的订单号
            'sys_orderno' => $sys_orderno,    //系统订单号
            'total_money' => sprintf('%.2f', $orderAmt),   //订单金额
            'name' => 'James Kanny',
            'phone' => '6130829630',
            'email' => 'wXg2RDUZzGzk@cwgsy.net',
            'productInfo' => 'TianYaPay',
            'have_money' => $hava_money,     //获得金额
            'upstream_money' => $upstream_money,
            'style' => $style,
            'rate' => $user_rate,             //用户费率
            'channel_rate' => $channel_rate,  //上游费率
            'api_upstream_id' => $api_upstream_id,    //上游id
            'api_account_id' => $api_account_id,      //账号id
            'api_type_id' => $api_type_id,            //上游类型
            'req_info' => urldecode(http_build_query($data)),  //请求报文
            'req_ip'=>$reqip
        ];
        Order::create($data);


        //扫码跳转模式
        if (strstr($channel, 'sm')) {

            Cache::set('qr.'.$order_no,$payurl,3600);

            $payurl =  url('/index/ewm/show', ['orderno' => $order_no], '', config('site.url')) ;
        }

        $this->success('请求成功!', [
            'payurl' => $payurl,              //支付地址
            'orderno' => $order_no,           //订单号
            'sysorderno' => $sys_orderno      //系统订单号
        ]);
    }
    /**
     * 用户资金变动
     */
    public function moneylog()
    {

        $data = $this->request->only(['orderno', 'style','date']);

        $rules = [
            'orderno|订单号' => 'alphaDash',
            'style|类型' => 'in:1,2,3',
            'date|日期范围' => 'array'
        ];

        $result = $this->validate($data, $rules);
        if ($result !== true) {
            $this->error($result);
        }

        $where = [];

        if(!empty($data['orderno'])){
            $where['orderno'] = ['like','%'.$data['orderno'].'%'];
        }

        if(!empty($data['style'])){
            $where['style'] = $data['style'];
        }

        //时间
        if (isset($data['date']) && is_array($data['date']) && count($data['date']) == 2) {
            // 判断是毫秒还是秒级时间戳（大于 10 位数字的是秒级，13 位的是毫秒级）
            $startTime = $data['date'][0];
            $endTime = $data['date'][1];
            
            // 如果是毫秒级时间戳（13位），转换为秒级
            if (strlen((string)$startTime) > 10) {
                $startTime = $startTime / 1000;
                $endTime = $endTime / 1000;
            }
            
            $where['createtime'] = ['between', [$startTime, $endTime]];
        }


        //排序字段
        $orderField = $this->request->param('orderField', 'id');
        $sort = 'DESC';
        //分页字段
        $page = $this->request->param('page/d', 1);
        $pageLimit = 10;    //每页显示10条数据
        $offset = ($page - 1) * $pageLimit;

        $userModel = $this->auth->getUser();
        //数据总数
        $total = $userModel->moneylog()->where($where)->count();

        $list = $userModel->moneylog()
            ->where($where)
            ->order($orderField, $sort)
            ->limit($offset, $pageLimit)
            ->select();

        foreach ($list as $k => $v) {
            $v->hidden(['user_id']);
        }
        $list = collection($list)->toArray();

        $extend = [];
        $this->success('获取数据成功！', [
            'total' => $total,
            'list' => $list,
            'limit' => $pageLimit,
            'extend'=>$extend
        ]);

    }
}
