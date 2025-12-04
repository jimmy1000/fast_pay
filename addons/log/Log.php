<?php

namespace addons\log;

use app\common\library\Menu;
use think\Addons;

/**
 * 插件
 */
class Log extends Addons
{

    // 初始化更改日志级别配置项
    public function appInit(&$params)
    {
        $logConfig = get_addon_config('log');
        $pluginLevel = explode(',', $logConfig['level']);
        
        // 获取 config.php 里已有的 level 和 apart_level（可能包含自定义级别如 PAY_API）
        $existingLevel = config('log.level') ?: [];
        $apartLevel = config('log.apart_level') ?: [];
        
        // 合并插件配置的级别和 config.php 里的自定义级别
        $mergedLevel = array_unique(array_merge($existingLevel, $pluginLevel));
        
        // 设置合并后的 level，保留 apart_level（支持自定义级别）
        config('log.level', $mergedLevel);
        if (!empty($apartLevel)) {
            config('log.apart_level', $apartLevel);
        }
    }

    /**
     * 插件安装方法
     * @return bool
     */
    public function install()
    {
        $menu = [
            [
                'name'    => 'general/logs',
                'title'   => '日志管理',
                'ismenu'  => 1,
                'icon'    => 'fa fa-pied-piper-alt',
                'sublist' => [
                    ['name' => 'general/logs/index', 'title' => '查看'],
                    ['name' => 'general/logs/del', 'title' => '删除'],
                    ['name' => 'general/logs/detail', 'title' => '详情']
                ],
            ]
        ];
        Menu::create($menu, 'general');
        return true;
    }

    /**
     * 插件卸载方法
     * @return bool
     */
    public function uninstall()
    {
        Menu::delete('general/logs');
        return true;
    }

    /**
     * 插件启用方法
     * @return bool
     */
    public function enable()
    {
        Menu::enable('general/logs');
        return true;
    }

    /**
     * 插件禁用方法
     * @return bool
     */
    public function disable()
    {
        Menu::disable('general/logs');
        return true;
    }
}
