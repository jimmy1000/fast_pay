<?php
/**
 * 结算卡管理控制器
 * @author : jimmy
 * @date : 2025-12-13
 */
namespace app\api\controller;

use app\common\controller\Api;
use app\common\model\UserLog;

class Bankcard extends Api
{
    protected $noNeedLogin = [];
    protected $noNeedRight = ['*'];

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 结算卡列表
     */
    public function index(){
        $data = $this->request->only(['name', 'bankaccount', 'status','bankcardtype','bankname','caraddresstype','caraddress']);

        $rules = [
            'bankcardtype|结算卡类型' => 'in:bank,usdt,alipay',
            'name|账户姓名' => 'chsAlpha|max:30',
            'bankaccount|结算卡账号' => 'max:30',
            'bankname|银行名' => 'chsAlphaNum|max:50',
            'bic|银行识别码' => 'alphaNum|max:20',
            'email|邮箱' => 'email|max:20',
            'phone|手机号' => 'max:255',
            'status|状态' => 'in:0,1',
            'caraddresstype|USDT地址类型' => 'in:ERC20,TRC20,-',
            'caraddress|USDT结算地址' => 'max:255',
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

        $allRules = [
            'bank' => [
                'bankcardtype|结算卡类型' => 'require|chsAlpha|max:32',
                'name|账户姓名' => 'require|chsAlpha|max:32',
                'bankaccount|结算卡账号' => 'require|number|max:24',
                'bankname|银行名' => 'require|chsAlpha|max:32',
                'bic|银行码' => 'require|alphaNum|max:24',
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
            \app\common\model\Bankcard::create($data);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
        $value = isset($data['bankaccount']) ? $data['bankaccount'] : $data['caraddress'];
        UserLog::addLog($this->auth->merchant_id, '添加结算卡类型  '.$bankcardtype.'【' . $value . '】');


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
     * 删除结算卡
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