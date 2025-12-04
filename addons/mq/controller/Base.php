<?php
/**
 * Base.php
 * 易聚合支付系统
 * =========================================================

 * ----------------------------------------------
 *
 *
 * 请尊重开发人员劳动成果，严禁使用本系统转卖、销售或二次开发后转卖、销售等商业行为。
 * 本源码仅供技术学习研究使用,请勿用于非法用途,如产生法律纠纷与作者无关。
 * =========================================================
 * @author : 666666@qq.com
 * @date : 2019-06-09
 */


namespace addons\mq\controller;

/**
 * CMS控制器基类
 */
class Base extends \think\addons\Controller
{

    // 初始化
    public function __construct()
    {
        parent::__construct();
        $config = get_addon_config('mq');

        // 设定主题模板目录
        $this->view->engine->config('view_path', $this->view->engine->config('view_path') . $config['theme'] . DS);
        \think\Config::set('mq', $config);
    }

    public function _initialize()
    {
        parent::_initialize();
        // 如果请求参数action的值为一个方法名,则直接调用
        $action = $this->request->post("action");
        if ($action && $this->request->isPost()) {
            return $this->$action();
        }
    }
}