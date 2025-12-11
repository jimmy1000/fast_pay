<?php
/**
 * Config.php
 * 易聚合支付系统
 * =========================================================
 * 请尊重开发人员劳动成果，严禁使用本系统转卖、销售或二次开发后转卖、销售等商业行为。
 * 本源码仅供技术学习研究使用,请勿用于非法用途,如产生法律纠纷与作者无关。
 * =========================================================
 * @author : 666666@qq.com
 * @date : 2019/2/1
 */

namespace app\api\controller;
use app\common\controller\Api;

class Config extends  Api{

    // 无需登录的接口,*表示全部
    protected $noNeedLogin = '*';
    // 无需鉴权的接口,*表示全部
    protected $noNeedRight = '*';

    public function index()
    {

        $site = \think\Config::get("site");

        $data = [
            'name'=>$site['name'],
            'beian'=>$site['beian'],
            'version'=>$site['version'],
            'reg_switch'=>$site['reg_switch'],
            'agent_switch'=>$site['agent_switch'],
            'minrecharge'=>$site['minrecharge'],
            'txpaytimestart'=>$site['txpaytimestart'],
            'txpaytimeend'=>$site['txpaytimeend'],
            'gateway'=>$site['gateway'],
            'public_key'=>$site['public_key'],
            'agent_change_rate'=>$site['agent_change_rate'],
            'notice'=>$site['notice'],
            'url'=>$site['url']
        ];
        $this->success('获取配置成功!',$data);
    }
}