<?php

namespace app\admin\controller;

use app\admin\model\Admin;
use app\admin\model\User;
use app\admin\model\mq\Category;
use app\admin\model\mq\Account;
use app\admin\model\order\Order as OrderModel;
use app\admin\model\repay\Order as RepayOrderModel;
use app\common\controller\Backend;
use app\common\model\Attachment;
use fast\Date;
use think\Db;

/**
 * 控制台
 *
 * @icon   fa fa-dashboard
 * @remark 用于展示当前系统中的统计数据、统计报表及重要实时数据
 */
class Dashboard extends Backend
{

    /**
     * 查看
     */
    public function index()
    {
        try {
            \think\Db::execute("SET @@sql_mode='';");
        } catch (\Exception $e) {

        }
        $column = [];
        $starttime = Date::unixtime('day', -6);
        $endtime = Date::unixtime('day', 0, 'end');
        $joinlist = Db("user")->where('jointime', 'between time', [$starttime, $endtime])
            ->field('jointime, status, COUNT(*) AS nums, DATE_FORMAT(FROM_UNIXTIME(jointime), "%Y-%m-%d") AS join_date')
            ->group('join_date')
            ->select();
        for ($time = $starttime; $time <= $endtime;) {
            $column[] = date("Y-m-d", $time);
            $time += 86400;
        }
        $userlist = array_fill_keys($column, 0);
        foreach ($joinlist as $k => $v) {
            $userlist[$v['join_date']] = $v['nums'];
        }

        $dbTableList = Db::query("SHOW TABLE STATUS");
        $addonList = get_addon_list();
        $totalworkingaddon = 0;
        $totaladdon = count($addonList);
        foreach ($addonList as $index => $item) {
            if ($item['state']) {
                $totalworkingaddon += 1;
            }
        }
        $this->view->assign([
            'totaluser'         => User::count(),
            'totalagent'        => User::where('group_id', 2)->count(),
            'totaladmin'        => Admin::count(),
            'totalcategory'     => Category::count(),
            'totalusermoney'    => number_format(User::sum('money') ?: 0, 2, '.', ','),
            'totalwithdrawal'   => number_format(User::sum('withdrawal') ?: 0, 2, '.', ','),
            'totalmaxmoney'     => number_format(Account::sum('maxmoney') ?: 0, 2, '.', ','),
            'totaltodaymoney'   => number_format(Account::sum('todaymoney') ?: 0, 2, '.', ','),
            'todayusersignup'   => User::whereTime('jointime', 'today')->count(),
            'todayuserlogin'    => User::whereTime('logintime', 'today')->count(),
            'sevendau'          => User::whereTime('jointime|logintime|prevtime', '-7 days')->count(),
            'thirtydau'         => User::whereTime('jointime|logintime|prevtime', '-30 days')->count(),
            'threednu'          => User::whereTime('jointime', '-3 days')->count(),
            'sevendnu'          => User::whereTime('jointime', '-7 days')->count(),
            'dbtablenums'       => count($dbTableList),
            'dbsize'            => array_sum(array_map(function ($item) {
                return $item['Data_length'] + $item['Index_length'];
            }, $dbTableList)),
            'totalworkingaddon' => $totalworkingaddon,
            'attachmentnums'    => Attachment::count(),
            'attachmentsize'    => Attachment::sum('filesize'),
            'picturenums'       => Attachment::where('mimetype', 'like', 'image/%')->count(),
            'picturesize'       => Attachment::where('mimetype', 'like', 'image/%')->sum('filesize'),
        ]);

        $this->assignconfig('column', array_keys($userlist));
        $this->assignconfig('userdata', array_values($userlist));

        // 订单统计数据
        $orderModel = new OrderModel();
        
        // 今日订单统计
        $todayOrderTotal = $orderModel->whereTime('createtime', 'today')->count();
        $todayOrderSuccess = $orderModel->whereTime('createtime', 'today')->where('status', 'in', '1,2')->count();
        
        // 今日金额统计
        $todayMoney = $orderModel->whereTime('createtime', 'today')->where('status', 'in', '1,2')->sum('total_money') ?: 0;
        $todayExpense = $orderModel->whereTime('createtime', 'today')->where('status', 'in', '1,2')
            ->field('COALESCE(sum(have_money),0) + COALESCE(sum(agent_money),0) + COALESCE(sum(upstream_money),0) as expense')
            ->find();
        $todayExpenseMoney = $todayExpense ? $todayExpense->expense : 0;
        
        // 全部订单统计
        $allOrderTotal = $orderModel->count();
        $allOrderSuccess = $orderModel->where('status', 'in', '1,2')->count();
        
        // 全部金额统计
        $allMoney = $orderModel->where('status', 'in', '1,2')->sum('total_money') ?: 0;
        $allExpense = $orderModel->where('status', 'in', '1,2')
            ->field('COALESCE(sum(have_money),0) + COALESCE(sum(agent_money),0) + COALESCE(sum(upstream_money),0) as expense')
            ->find();
        $allExpenseMoney = $allExpense ? $allExpense->expense : 0;
        
        // 图表数据（最近7天）
        $chartColumn = [];
        $chartMoneyList = [];
        $chartOrderList = [];
        $chartRateList = [];
        
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $chartColumn[] = $date;
            
            $dayStart = strtotime($date . ' 00:00:00');
            $dayEnd = strtotime($date . ' 23:59:59');
            
            // 当日成功金额
            $dayMoney = $orderModel->where('createtime', '>=', $dayStart)
                ->where('createtime', '<=', $dayEnd)
                ->where('status', 'in', '1,2')
                ->sum('total_money') ?: 0;
            $chartMoneyList[] = $dayMoney;
            
            // 当日成功订单数
            $dayOrder = $orderModel->where('createtime', '>=', $dayStart)
                ->where('createtime', '<=', $dayEnd)
                ->where('status', 'in', '1,2')
                ->count();
            $chartOrderList[] = $dayOrder;
            
            // 当日成功率
            $dayTotal = $orderModel->where('createtime', '>=', $dayStart)
                ->where('createtime', '<=', $dayEnd)
                ->count();
            $dayRate = $dayTotal > 0 ? number_format($dayOrder / $dayTotal * 100, 2) : 0;
            $chartRateList[] = $dayRate;
        }
        
        $this->view->assign([
            'todayOrderTotal' => $todayOrderTotal,
            'todayOrderSuccess' => $todayOrderSuccess,
            'todayMoney' => number_format($todayMoney, 2, '.', ','),
            'todayExpenseMoney' => number_format($todayExpenseMoney, 2, '.', ','),
            'allOrderTotal' => $allOrderTotal,
            'allOrderSuccess' => $allOrderSuccess,
            'allMoney' => number_format($allMoney, 2, '.', ','),
            'allExpenseMoney' => number_format($allExpenseMoney, 2, '.', ','),
        ]);
        
        $this->assignconfig('orderChartColumn', $chartColumn);
        $this->assignconfig('orderChartMoney', $chartMoneyList);
        $this->assignconfig('orderChartOrder', $chartOrderList);
        $this->assignconfig('orderChartRate', $chartRateList);

        // 代付订单统计数据
        $repayOrderModel = new RepayOrderModel();
        
        // 今日代付统计
        $todayRepayTotal = $repayOrderModel->whereTime('createtime', 'today')->count();
        $todayRepaySuccess = $repayOrderModel->whereTime('createtime', 'today')->where('daifustatus', '3')->count();
        
        // 今日代付金额统计
        $todayRepayMoney = $repayOrderModel->whereTime('createtime', 'today')->where('daifustatus', '3')->sum('money') ?: 0;
        $todayRepayCharge = $repayOrderModel->whereTime('createtime', 'today')->where('daifustatus', '3')->sum('charge') ?: 0;
        
        // 全部代付统计
        $allRepayTotal = $repayOrderModel->count();
        $allRepaySuccess = $repayOrderModel->where('daifustatus', '3')->count();
        
        // 全部代付金额统计
        $allRepayMoney = $repayOrderModel->where('daifustatus', '3')->sum('money') ?: 0;
        $allRepayCharge = $repayOrderModel->where('daifustatus', '3')->sum('charge') ?: 0;
        
        // 代付图表数据（最近7天）
        $repayChartColumn = [];
        $repayChartMoneyList = [];
        $repayChartOrderList = [];
        $repayChartRateList = [];
        
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $repayChartColumn[] = $date;
            
            $dayStart = strtotime($date . ' 00:00:00');
            $dayEnd = strtotime($date . ' 23:59:59');
            
            // 当日成功代付金额
            $dayMoney = $repayOrderModel->where('createtime', '>=', $dayStart)
                ->where('createtime', '<=', $dayEnd)
                ->where('daifustatus', '3')
                ->sum('money') ?: 0;
            $repayChartMoneyList[] = $dayMoney;
            
            // 当日成功代付订单数
            $dayOrder = $repayOrderModel->where('createtime', '>=', $dayStart)
                ->where('createtime', '<=', $dayEnd)
                ->where('daifustatus', '3')
                ->count();
            $repayChartOrderList[] = $dayOrder;
            
            // 当日成功率
            $dayTotal = $repayOrderModel->where('createtime', '>=', $dayStart)
                ->where('createtime', '<=', $dayEnd)
                ->count();
            $dayRate = $dayTotal > 0 ? number_format($dayOrder / $dayTotal * 100, 2) : 0;
            $repayChartRateList[] = $dayRate;
        }
        
        $this->view->assign([
            'todayRepayTotal' => $todayRepayTotal,
            'todayRepaySuccess' => $todayRepaySuccess,
            'todayRepayMoney' => number_format($todayRepayMoney, 2, '.', ','),
            'todayRepayCharge' => number_format($todayRepayCharge, 2, '.', ','),
            'allRepayTotal' => $allRepayTotal,
            'allRepaySuccess' => $allRepaySuccess,
            'allRepayMoney' => number_format($allRepayMoney, 2, '.', ','),
            'allRepayCharge' => number_format($allRepayCharge, 2, '.', ','),
        ]);
        
        $this->assignconfig('repayChartColumn', $repayChartColumn);
        $this->assignconfig('repayChartMoney', $repayChartMoneyList);
        $this->assignconfig('repayChartOrder', $repayChartOrderList);
        $this->assignconfig('repayChartRate', $repayChartRateList);

        return $this->view->fetch();
    }

}
