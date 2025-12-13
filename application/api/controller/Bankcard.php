<?php
/**
 * Bankcard.php
 * 易聚合支付系统
 * =========================================================

 * ----------------------------------------------
 *
 *
 * 请尊重开发人员劳动成果，严禁使用本系统转卖、销售或二次开发后转卖、销售等商业行为。
 * 本源码仅供技术学习研究使用,请勿用于非法用途,如产生法律纠纷与作者无关。
 * =========================================================
 * @author : 666666@qq.com
 * @date : 2019-05-09
 */
namespace app\api\controller;

use addons\goeasy\library\Goeasy;
use app\common\controller\Api;
use app\common\model\ApiChannel;
use app\common\model\UserApichannel;
use app\common\model\UserLog;
use easypay\Notify;
use think\Db;
use think\Exception;
use think\Validate;

class Bankcard extends Api{

    protected $noNeedLogin = [];

    //不需要权限检查的方法
    protected $noNeedRight = ['*'];


    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 银行卡列表
     */
    public function index(){
        $data = $this->request->only(['name', 'bankaccount', 'status','bankcardtype','bankname','caraddresstype','caraddress']);

        $rules = [
            'bankcardtype|结算卡类型' => 'chsAlpha|max:32',
            'name|账户姓名' => 'chsAlpha|max:32',
            'bankaccount|结算卡账号' => 'alphaNum|max:24',
            'bankname|银行' => 'chsAlpha|max:32',
            'ifsc|ifsc' => 'alphaNum|max:24',
            'email|邮箱' => 'email|max:24',
            'phone|手机号' => 'number|max:255',
            'caraddresstype|USDT地址类型' => 'chsAlphaNum|max:10',
            'caraddress|结算地址' => 'alphaNum|max:50',
        ];
        $messages = [
            'status.in'=>'状态错误'
        ];

        $result = $this->validate($data, $rules);
        if (true !== $result) {
            $this->error($result);
        }

        //查询条件
        $where = [
            'merchant_id' => $this->auth->merchant_id
        ];



        if(!empty($data['name'])){
            $where['name'] = ['like','%'.$data['name'].'%'];
        }

        if(!empty($data['bankaccount'])){
            $where['bankaccount'] = ['like','%'.$data['bankaccount'].'%'];
        }

        if (isset($data['status']) && $data['status'] != '') {
            $where['status'] = $data['status'];
        }
        if (isset($data['bankcardtype']) && $data['bankcardtype'] != '') {
            $where['bankcardtype'] = $data['bankcardtype'];
        }
        if(!empty($data['bankname'])){
            $where['bankname'] = ['like','%'.$data['bankname'].'%'];
        }
        if (isset($data['caraddresstype']) && $data['caraddresstype'] != '') {
            $where['caraddresstype'] = $data['caraddresstype'];
        }
        if(!empty($data['caraddress'])){
            $where['caraddress'] = ['like','%'.$data['caraddress'].'%'];
        }

        //排序字段
        $orderField = $this->request->param('orderField', 'id');
        $sort = 'DESC';
        //分页字段
        $page = $this->request->param('page/d', 1);
        $pageLimit = 10;    //每页显示10条数据
        $offset = ($page - 1) * $pageLimit;


        //数据总数
        $total = \app\common\model\Bankcard::where($where)->count();
        $list = \app\common\model\Bankcard::where($where)
            ->order($orderField, $sort)
            ->limit($offset, $pageLimit)
            ->select();
        $list = collection($list)->toArray();

        $this->success('获取数据成功！', [
            'total' => $total,
            'list' => $list,
            'limit' => $pageLimit
        ]);
    }
    /**
     * 银行卡添加
     */
    public function add()
    {
        $bankcardtype = $this->request->post('bankcardtype');

        $rules = [];

// 定义规则
        $allRules = [
            'bank' => [
                'bankcardtype|结算卡类型' => 'require|chsAlpha|max:32',
                'name|账户姓名' => 'require|chsAlpha|max:32',
                'bankaccount|结算卡账号' => 'require|number|max:24',
                'bankname|银行名' => 'require|chsAlpha|max:32',
//                'province|省份' => 'require|chs|max:24',
//                'city|城市' => 'require|chs|max:24',
//                'zhihang|支行' => 'require|chsAlphaNum|max:255',
                'ifsc|ifsc' => 'require|alphaNum|max:24',
                'email|邮箱' => 'require|email|max:24',
                'phone|手机号' => 'require|number|max:255',
            ],
            'alipay' => [
                'bankcardtype|结算卡类型' => 'require|chsAlpha|max:32',
                'name|账户姓名' => 'require|chsAlpha|max:32',
                'bankaccount|结算卡账号' => 'require|alphaNum|max:24',
            ],
            'usdt' => [
                'bankcardtype|结算卡类型' => 'require|chsAlpha|max:32',
                'caraddresstype|USDT地址类型' => 'require|chsAlphaNum|max:10',
                'caraddress|结算地址' => 'require|alphaNum|max:50',
            ],
            // 可以添加更多结算卡类型的规则
        ];
// 根据结算卡类型选择规则
        if (isset($allRules[$bankcardtype])) {
            $rules = $allRules[$bankcardtype];
        } else {
            // 默认规则，可以根据实际情况定义
            $rules = [
                'bankcardtype|结算卡类型' => 'require|in:bank,alipay,usdt',
            ];
        }
        $keys = array_map(function($key) {
            // 使用 explode() 函数分割键名，以 "|" 作为分隔符
            // 并返回分割后的数组的第一个元素，即 "|" 前面的部分
            return explode('|', $key)[0];
        }, array_keys($rules));

// 使用处理后的键名数组作为参数传递给 $this->request->only() 方法，以获取请求中的数据
        $data = $this->request->only($keys);
// 进行验证
        $result = $this->validate($data, $rules);
        if (true !== $result) {
            $this->error($result);
        }


        $data['merchant_id'] = $this->auth->merchant_id;

        //如果开启审核
        if(config('site.ifcheckka') == '1'){
            $data['status'] = '0';
        }else{
            $data['status'] = '1';      //自动通过
        }
        try {
            $bankcard = \app\common\model\Bankcard::create($data);
            return json(['status' => 'success', 'message' => '银行卡信息保存成功', 'data' => $bankcard]);
        } catch (\Exception $e) {
            return json(['status' => 'error', 'message' => $e->getMessage()]);
        }
        $value = isset($data['bank_account']) ? $data['bank_account'] : $data['caraddress'];
        UserLog::addLog($this->auth->merchant_id, '添加结算卡类型'.$bankcardtype.'【' . $value . '】');

        Notify::bankcard();

        $this->success('添加结算卡成功');

    }

    /**
     * 获取通过审核的卡
     */
    public function normal(){

        //查询条件
        $where = [
            'merchant_id' => $this->auth->merchant_id,
            'status'=>'1'
        ];
        //排序字段
        $orderField = $this->request->param('orderField', 'id');
        $sort = 'DESC';
        $list = \app\common\model\Bankcard::where($where)
            ->order($orderField, $sort)
            ->select();
        $list = collection($list)->toArray();

        $this->success('获取数据成功！', [
            'list' => $list,
        ]);
    }

    /**
     * 编辑银行卡
     */
    public function edit()
    {
        $bankcardtype = $this->request->post('bankcardtype');
        $allRules = [
            'bank' => [
                'bankcardtype|结算卡类型' => 'require|chsAlpha|max:32',
                'name|账户姓名' => 'require|chsAlpha|max:32',
                'bankaccount|结算卡账号' => 'require|regex:/^[a-zA-Z0-9@.]+$/|max:32',
                'bankname|银行名' => 'require|chsAlphaNum|max:32',
//                'province|省份' => 'require|chs|max:24',
//                'city|城市' => 'require|chs|max:24',
//                'zhihang|支行' => 'require|chsAlphaNum|max:255',
                'ifsc|ifsc' => 'require|alphaNum|max:24',
                'email|邮箱' => 'require|email|max:24',
                'phone|手机号' => 'require|number|max:255',
            ],
            'alipay' => [
                'bankcardtype|结算卡类型' => 'require|chsAlpha|max:32',
                'name|账户姓名' => 'require|chsAlpha|max:32',
                'bankaccount|结算卡账号' => 'require|alphaNum|max:24',
            ],
            'usdt' => [
                'bankcardtype|结算卡类型' => 'require|chsAlpha|max:32',
                'caraddresstype|USDT地址类型' => 'require|chsAlphaNum|max:10',
                'caraddress|结算地址' => 'require|alphaNum|max:50',
            ],
            // 可以添加更多结算卡类型的规则
        ];
        if (isset($allRules[$bankcardtype])) {
            $rules = $allRules[$bankcardtype];
        } else {
            // 默认规则，可以根据实际情况定义
            $rules = [
                'bankcardtype|结算卡类型' => 'require|in:bank,alipay,usdt',
            ];
        }
        $keys = array_map(function($key) {
            // 使用 explode() 函数分割键名，以 "|" 作为分隔符
            // 并返回分割后的数组的第一个元素，即 "|" 前面的部分
            return explode('|', $key)[0];
        }, array_keys($rules));

// 使用处理后的键名数组作为参数传递给 $this->request->only() 方法，以获取请求中的数据
        $data = $this->request->only($keys);
        $result = $this->validate($data, $rules);
        if (true !== $result) {
            $this->error($result);
        }

        if(config('site.ifcheckka') == '1'){
            $data['status'] = '0';
        }else{
            $data['status'] = '1';      //自动通过
        }
        $id=$this->request->only('id');
        $where = [
            'merchant_id'=>$this->auth->merchant_id,
            'id'=>$id['id']
        ];

        unset($data['id']);

        \app\common\model\Bankcard::update($data,$where);

        UserLog::addLog($this->auth->merchant_id, '修改编号为'.$where['id'].'的结算卡');

        Notify::bankcard();

        $this->success('修改成功');

    }
    /**
     * 删除银行卡
     */
    public function del()
    {
        $data = $this->request->only('id');
        $rules = [
            'id|编号'=>'require|number'
        ];
        $result = $this->validate($data, $rules);
        if (true !== $result) {
            $this->error($result);
        }

        $where = [
            'merchant_id'=>$this->auth->merchant_id,
            'id'=>$data['id']
        ];

        \app\common\model\Bankcard::destroy($where);

        UserLog::addLog($this->auth->merchant_id, '删除编号为'.$data['id'].'的结算卡');

        $this->success('删除成功');
    }


}