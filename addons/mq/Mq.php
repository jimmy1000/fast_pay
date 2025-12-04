<?php
/**
 * Mq.php
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

namespace addons\mq;

use app\common\library\Auth;
use app\common\library\Menu;
use fast\Tree;
use think\Addons;
use think\exception\PDOException;
use think\Request;
use think\View;

/**
 * 免签插件
 * Class Mq
 * @package addons\mq
 */
class Mq extends Addons
{


    public function install()
    {

        $menu = [
            'name' => 'mq',
            'title' => '支付宝微信免签',
            'sublist' => [

                [
                    'name' => 'mq/category',
                    'title' => '二维码分类',
                    'icon' => 'fa fa-list',
                    'sublist' => [
                        ['name' => 'mq/category/index', 'title' => '查看'],
                        ['name' => 'mq/category/add', 'title' => '添加'],
                        ['name' => 'mq/category/edit', 'title' => '修改'],
                        ['name' => 'mq/category/del', 'title' => '删除'],
                        ['name' => 'mq/category/multi', 'title' => '批量更新'],
                    ]
                ],

                [
                    'name' => 'mq/account',
                    'title' => '二维码列表',
                    'icon' => 'fa fa-list',
                    'sublist' => [
                        ['name' => 'mq/account/index', 'title' => '查看'],
                        ['name' => 'mq/account/add', 'title' => '添加'],
                        ['name' => 'mq/account/edit', 'title' => '修改'],
                        ['name' => 'mq/account/del', 'title' => '删除'],
                        ['name' => 'mq/account/multi', 'title' => '批量更新'],
                    ]
                ],

                [
                    'name' => 'mq/order',
                    'title' => '收款记录',
                    'icon' => 'fa fa-list',
                    'sublist' => [
                        ['name' => 'mq/order/index', 'title' => '查看']
                    ]
                ]

            ]
        ];

        Menu::create($menu);
        return true;

    }

    /**
     * 插件卸载方法
     * @return bool
     */
    public function uninstall()
    {
        Menu::delete('mq');
        return true;
    }


    /**
     * 插件启用方法
     */
    public function enable()
    {

        Menu::delete('mq');

        $menu = [

            [
                'name' => 'mq',
                'title' => '支付宝微信免签',
                'sublist' => [

                    [
                        'name' => 'mq/category',
                        'title' => '二维码分类',
                        'icon' => 'fa fa-list',
                        'sublist' => [
                            ['name' => 'mq/category/index', 'title' => '查看'],
                            ['name' => 'mq/category/add', 'title' => '添加'],
                            ['name' => 'mq/category/edit', 'title' => '修改'],
                            ['name' => 'mq/category/del', 'title' => '删除'],
                            ['name' => 'mq/category/multi', 'title' => '批量更新'],
                        ]
                    ],

                    [
                        'name' => 'mq/account',
                        'title' => '二维码列表',
                        'icon' => 'fa fa-list',
                        'sublist' => [
                            ['name' => 'mq/account/index', 'title' => '查看'],
                            ['name' => 'mq/account/add', 'title' => '添加'],
                            ['name' => 'mq/account/edit', 'title' => '修改'],
                            ['name' => 'mq/account/del', 'title' => '删除'],
                            ['name' => 'mq/account/multi', 'title' => '批量更新'],
                        ]
                    ],

                    [
                        'name' => 'mq/order',
                        'title' => '收款记录',
                        'icon' => 'fa fa-list',
                        'sublist' => [
                            ['name' => 'mq/order/index', 'title' => '查看']
                        ]
                    ]

                ]
            ]

        ];

        Menu::create($menu);
    }

    /**
     * 插件禁用方法
     */
    public function disable()
    {
        Menu::disable('mq');
    }


}