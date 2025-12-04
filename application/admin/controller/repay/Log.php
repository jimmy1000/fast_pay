<?php

namespace app\admin\controller\repay;

use app\common\controller\Backend;

/**
 * api代付请求日志
 *
 * @icon fa fa-circle-o
 */
class Log extends Backend
{

    /**
     * Log模型对象
     * @var \app\admin\model\repay\Log
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\repay\Log;
        $this->view->assign("statusList", $this->model->getStatusList());
    }

    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */

    /**
     * 请求详情
     */
    public function detail($ids = null)
    {
        $row = $this->model->get(['id' => $ids]);
        if (!$row) {
            $this->error(__('No Results were found'));
        }

        // 解析请求参数
        $params = [];
        if (!empty($row->content)) {
            $content = @unserialize($row->content);
            if ($content !== false && is_array($content)) {
                $params = $content;
            }
        }

        // 格式化状态
        $statusText = $row->status == '1' ? '成功' : '失败';

        // 格式化时间
        $timeText = $row->createtime ? date('Y-m-d H:i:s', $row->createtime) : '';

        // 基本信息
        $data = [
            '订单号'   => $row->orderno ?: '-',
            '商户号'   => $row->merchant_id ?: '-',
            '来源'     => $row->http ?: '-',
            '状态'     => $statusText,
            '结果'     => $row->result ?: '-',
            '金额'     => $row->total_money ? number_format($row->total_money, 2, '.', '') : '0.00',
            '通道编码' => $row->channel ?: '-',
            'ip'       => $row->ip ?: '-',
            '时间'     => $timeText,
        ];

        // 请求参数
        $requestParams = [];
        if (!empty($params)) {
            // 参数名称映射（代付接口参数）
            $paramMap = [
                'merId'           => 'merId',
                'orderId'         => 'orderId',
                'money'           => 'money',
                'bankCode'        => 'bankCode',
                'bankname'        => 'bankname',
                'name'            => 'name',
                'phone'           => 'phone',
                'email'           => 'email',
                'caraddress'      => 'caraddress',
                'caraddresstype'  => 'caraddresstype',
                'ifsc'            => '识别码(ifsc)',
                'notifyUrl'       => 'notifyUrl',
                'timestamp'       => 'timestamp',
                'sign'            => 'sign',
            ];

            foreach ($paramMap as $key => $label) {
                if (isset($params[$key])) {
                    $requestParams[$label] = $params[$key];
                }
            }
        }

        $this->view->assign("data", $data);
        $this->view->assign("requestParams", $requestParams);
        return $this->view->fetch();
    }
}
