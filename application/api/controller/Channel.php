<?php
/**
 * Channel.php
 * @author : jimmy
 * @date : 2025-12-11
 */

namespace app\api\controller;

use app\common\controller\Api;



class Channel extends Api {


    protected $noNeedLogin = [];

    //不需要权限检查的方法
    protected $noNeedRight = ['index','md5','changeapi'];


    public function _initialize()
    {
        parent::_initialize();
    }

    public function index(){
        $user = $this->auth->getUser();
        $list = $user->getApiList();


        $this->success('',$list);
    }

    /**
     * 获取用户的md5key
     */
    public function md5(){

        $rules = [
            'paypassword|支付密码' => 'require|length:6,16'
        ];

        $data = [
            'paypassword' => $this->request->param('paypassword', '')
        ];

        $result = $this->validate($data, $rules);
        if ($result !== true) {
            $this->error($result);
        }

        //验证支付密码是否正确
        $flag = \app\common\model\User::verifyPayPassword($data['paypassword'],$this->auth->id);

        if(!$flag){
            $this->error('支付密码输入不正确!');
        }

        //获取用户的md5key
        $this->success('获取成功!',['md5'=>$this->auth->getUser()->md5key]);

    }

    /**
     * 开发设置修改
     * 
     * @ApiMethod (POST)
     * @ApiParams (name="public_key", type="string", required=true, description="商户公钥")
     * @ApiParams (name="req_url", type="string", required=true, description="请求地址")
     * @ApiParams (name="googlemfa", type="string", required=true, description="Google MFA验证码")
     */
    public function changeapi()
    {
        $rules = [
            'public_key|Public Key' => 'require',
            'req_url|Request URL' => 'require',
            'googlemfa|Google MFA Code' => 'require',
        ];

        $message = [
            'public_key.require' => 'Public key is required',
            'req_url.require' => 'Request URL is required',
            'googlemfa.require' => 'Google MFA code is required',
        ];

        $data = [
            'public_key' => $this->request->post('public_key', ''),
            'req_url' => $this->request->post('req_url', ''),
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

        // 验证公钥格式
        $pem = chunk_split($data['public_key'], 64, "\n");
        $pem = "-----BEGIN PUBLIC KEY-----\n" . $pem . "-----END PUBLIC KEY-----";
        $public_key = openssl_pkey_get_public($pem);
        if (!$public_key) {
            $this->error(__('Public key format is incorrect, please check and re-enter'));
        }

        // 保存设置
        $user->save([
            'public_key' => $data['public_key'],
            'req_url' => $data['req_url']
        ]);

        $this->success(__('Settings saved successfully'));
    }
}