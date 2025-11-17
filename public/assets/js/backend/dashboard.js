define(['jquery', 'bootstrap', 'backend', 'addtabs', 'table', 'echarts', 'echarts-theme', 'template'], function ($, undefined, Backend, Datatable, Table, Echarts, undefined, Template) {

    var Controller = {
        index: function () {
            // 基于准备好的dom，初始化echarts实例
            var myChart = Echarts.init(document.getElementById('echart'), 'walden');

            // 指定图表的配置项和数据
            var option = {
                title: {
                    text: '',
                    subtext: ''
                },
                color: [
                    "#18d1b1",
                    "#3fb1e3",
                    "#626c91",
                    "#a0a7e6",
                    "#c4ebad",
                    "#96dee8"
                ],
                tooltip: {
                    trigger: 'axis'
                },
                legend: {
                    data: [__('Register user')]
                },
                toolbox: {
                    show: false,
                    feature: {
                        magicType: {show: true, type: ['stack', 'tiled']},
                        saveAsImage: {show: true}
                    }
                },
                xAxis: {
                    type: 'category',
                    boundaryGap: false,
                    data: Config.column
                },
                yAxis: {},
                grid: [{
                    left: 'left',
                    top: 'top',
                    right: '10',
                    bottom: 30
                }],
                series: [{
                    name: __('Register user'),
                    type: 'line',
                    smooth: true,
                    areaStyle: {
                        normal: {}
                    },
                    lineStyle: {
                        normal: {
                            width: 1.5
                        }
                    },
                    data: Config.userdata
                }]
            };

            // 使用刚指定的配置项和数据显示图表。
            myChart.setOption(option);

            $(window).resize(function () {
                myChart.resize();
            });

            $(document).on("click", ".btn-refresh", function () {
                setTimeout(function () {
                    myChart.resize();
                }, 0);
            });

            // 初始化代收订单趋势图表
            Controller.initOrderChart();
            
            // 初始化代付订单趋势图表
            Controller.initRepayChart();
        },
        
        // 初始化代收订单趋势图表
        initOrderChart: function() {
            // 这句话在多选项卡统计表时必须存在，否则会导致影响的图表宽度不正确
            $(document).on("click", ".charts-custom a[data-toggle=\"tab\"]", function () {
                var that = this;
                setTimeout(function () {
                    var id = $(that).attr("href");
                    var chart = Echarts.getInstanceByDom($(id)[0]);
                    if (chart) {
                        chart.resize();
                    }
                }, 0);
            });
            
            // 折线图
            var lineChart = Echarts.init(document.getElementById('order-line-chart'), 'walden');
            if (lineChart) {
                var option = {
                    tooltip: {
                        trigger: 'axis',
                        axisPointer: {
                            type: 'cross'
                        }
                    },
                    legend: {
                        data: ['成功金额', '成功订单数', '成功率']
                    },
                    grid: {
                        left: '3%',
                        right: '4%',
                        bottom: '3%',
                        containLabel: true
                    },
                    xAxis: {
                        type: 'category',
                        boundaryGap: false,
                        data: Config.orderChartColumn || []
                    },
                    yAxis: [
                        {
                            type: 'value',
                            name: '金额/订单数',
                            position: 'left'
                        },
                        {
                            type: 'value',
                            name: '成功率(%)',
                            position: 'right',
                            max: 100
                        }
                    ],
                    series: [
                        {
                            name: '成功金额',
                            type: 'line',
                            data: Config.orderChartMoney || [],
                            itemStyle: {
                                color: '#F05050'
                            },
                            lineStyle: {
                                color: '#F05050'
                            }
                        },
                        {
                            name: '成功订单数',
                            type: 'line',
                            data: Config.orderChartOrder || [],
                            itemStyle: {
                                color: '#3fb1e3'
                            },
                            lineStyle: {
                                color: '#3fb1e3'
                            }
                        },
                        {
                            name: '成功率',
                            type: 'line',
                            yAxisIndex: 1,
                            data: Config.orderChartRate || [],
                            itemStyle: {
                                color: '#27C24C'
                            },
                            lineStyle: {
                                color: '#27C24C'
                            }
                        }
                    ]
                };
                lineChart.setOption(option);
            }
            
            // 柱状图
            var barChart = Echarts.init(document.getElementById('order-bar-chart'), 'walden');
            if (barChart) {
                var option = {
                    tooltip: {
                        trigger: 'axis',
                        axisPointer: {
                            type: 'shadow'
                        }
                    },
                    legend: {
                        data: ['成功金额', '成功订单数']
                    },
                    grid: {
                        left: '3%',
                        right: '4%',
                        bottom: '3%',
                        containLabel: true
                    },
                    xAxis: {
                        type: 'category',
                        data: Config.orderChartColumn || []
                    },
                    yAxis: {
                        type: 'value'
                    },
                    series: [
                        {
                            name: '成功金额',
                            type: 'bar',
                            data: Config.orderChartMoney || [],
                            itemStyle: {
                                color: '#F05050'
                            }
                        },
                        {
                            name: '成功订单数',
                            type: 'bar',
                            data: Config.orderChartOrder || [],
                            itemStyle: {
                                color: '#3fb1e3'
                            }
                        }
                    ]
                };
                barChart.setOption(option);
            }
            
            // 成功金额趋势（块状图）
            var moneyChart = Echarts.init(document.getElementById('order-money-chart'));
            if (moneyChart) {
                var option = {
                    xAxis: {
                        type: 'category',
                        axisLine: {
                            lineStyle: {
                                color: "#fff"
                            }
                        },
                        data: Config.orderChartColumn || []
                    },
                    yAxis: {
                        type: 'value',
                        axisLine: {
                            lineStyle: {
                                color: "#fff"
                            }
                        }
                    },
                    series: [{
                        data: Config.orderChartMoney || [],
                        type: 'bar',
                        itemStyle: {
                            color: "#fff",
                            opacity: 0.6
                        }
                    }]
                };
                moneyChart.setOption(option);
            }
            
            // 成功率趋势（块状图）
            var rateChart = Echarts.init(document.getElementById('order-rate-chart'));
            if (rateChart) {
                var option = {
                    textStyle: {
                        color: "#fff"
                    },
                    color: ['#fff'],
                    xAxis: {
                        type: 'category',
                        boundaryGap: false,
                        data: Config.orderChartColumn || [],
                        axisLine: {
                            lineStyle: {
                                color: "#fff"
                            }
                        }
                    },
                    yAxis: {
                        type: 'value',
                        max: 100,
                        splitLine: {
                            show: false
                        },
                        axisLine: {
                            lineStyle: {
                                color: "#fff"
                            }
                        }
                    },
                    series: [{
                        data: Config.orderChartRate || [],
                        type: 'line',
                        smooth: true,
                        areaStyle: {
                            opacity: 0.4
                        }
                    }]
                };
                rateChart.setOption(option);
            }
            
            // 成功订单数趋势（块状图）
            var countChart = Echarts.init(document.getElementById('order-count-chart'), 'walden');
            if (countChart) {
                var option = {
                    xAxis: {
                        type: 'category',
                        data: Config.orderChartColumn || []
                    },
                    yAxis: {
                        type: 'value'
                    },
                    series: [{
                        data: Config.orderChartOrder || [],
                        type: 'line'
                    }]
                };
                countChart.setOption(option);
            }
            
            // 窗口大小改变时调整所有图表
            $(window).resize(function () {
                if (lineChart) lineChart.resize();
                if (barChart) barChart.resize();
                if (moneyChart) moneyChart.resize();
                if (rateChart) rateChart.resize();
                if (countChart) countChart.resize();
            });
            
            // 监听标签页切换，重新调整图表大小
            $(document).on("shown.bs.tab", 'a[data-toggle="tab"]', function () {
                setTimeout(function () {
                    if (lineChart) lineChart.resize();
                    if (barChart) barChart.resize();
                    if (moneyChart) moneyChart.resize();
                    if (rateChart) rateChart.resize();
                    if (countChart) countChart.resize();
                }, 100);
            });
        },
        
        // 初始化代付订单趋势图表
        initRepayChart: function() {
            // 折线图
            var repayLineChart = Echarts.init(document.getElementById('repay-line-chart'), 'walden');
            if (repayLineChart) {
                var option = {
                    tooltip: {
                        trigger: 'axis',
                        axisPointer: {
                            type: 'cross'
                        }
                    },
                    legend: {
                        data: ['成功金额', '成功订单数', '成功率']
                    },
                    grid: {
                        left: '3%',
                        right: '4%',
                        bottom: '3%',
                        containLabel: true
                    },
                    xAxis: {
                        type: 'category',
                        boundaryGap: false,
                        data: Config.repayChartColumn || []
                    },
                    yAxis: [
                        {
                            type: 'value',
                            name: '金额/订单数',
                            position: 'left'
                        },
                        {
                            type: 'value',
                            name: '成功率(%)',
                            position: 'right',
                            max: 100
                        }
                    ],
                    series: [
                        {
                            name: '成功金额',
                            type: 'line',
                            data: Config.repayChartMoney || [],
                            itemStyle: {
                                color: '#F05050'
                            },
                            lineStyle: {
                                color: '#F05050'
                            }
                        },
                        {
                            name: '成功订单数',
                            type: 'line',
                            data: Config.repayChartOrder || [],
                            itemStyle: {
                                color: '#3fb1e3'
                            },
                            lineStyle: {
                                color: '#3fb1e3'
                            }
                        },
                        {
                            name: '成功率',
                            type: 'line',
                            yAxisIndex: 1,
                            data: Config.repayChartRate || [],
                            itemStyle: {
                                color: '#27C24C'
                            },
                            lineStyle: {
                                color: '#27C24C'
                            }
                        }
                    ]
                };
                repayLineChart.setOption(option);
            }
            
            // 柱状图
            var repayBarChart = Echarts.init(document.getElementById('repay-bar-chart'), 'walden');
            if (repayBarChart) {
                var option = {
                    tooltip: {
                        trigger: 'axis',
                        axisPointer: {
                            type: 'shadow'
                        }
                    },
                    legend: {
                        data: ['成功金额', '成功订单数']
                    },
                    grid: {
                        left: '3%',
                        right: '4%',
                        bottom: '3%',
                        containLabel: true
                    },
                    xAxis: {
                        type: 'category',
                        data: Config.repayChartColumn || []
                    },
                    yAxis: {
                        type: 'value'
                    },
                    series: [
                        {
                            name: '成功金额',
                            type: 'bar',
                            data: Config.repayChartMoney || [],
                            itemStyle: {
                                color: '#F05050'
                            }
                        },
                        {
                            name: '成功订单数',
                            type: 'bar',
                            data: Config.repayChartOrder || [],
                            itemStyle: {
                                color: '#3fb1e3'
                            }
                        }
                    ]
                };
                repayBarChart.setOption(option);
            }
            
            // 成功代付金额趋势（块状图）
            var repayMoneyChart = Echarts.init(document.getElementById('repay-money-chart'));
            if (repayMoneyChart) {
                var option = {
                    xAxis: {
                        type: 'category',
                        axisLine: {
                            lineStyle: {
                                color: "#fff"
                            }
                        },
                        data: Config.repayChartColumn || []
                    },
                    yAxis: {
                        type: 'value',
                        axisLine: {
                            lineStyle: {
                                color: "#fff"
                            }
                        }
                    },
                    series: [{
                        data: Config.repayChartMoney || [],
                        type: 'bar',
                        itemStyle: {
                            color: "#fff",
                            opacity: 0.6
                        }
                    }]
                };
                repayMoneyChart.setOption(option);
            }
            
            // 代付成功率趋势（块状图）
            var repayRateChart = Echarts.init(document.getElementById('repay-rate-chart'));
            if (repayRateChart) {
                var option = {
                    textStyle: {
                        color: "#fff"
                    },
                    color: ['#fff'],
                    xAxis: {
                        type: 'category',
                        boundaryGap: false,
                        data: Config.repayChartColumn || [],
                        axisLine: {
                            lineStyle: {
                                color: "#fff"
                            }
                        }
                    },
                    yAxis: {
                        type: 'value',
                        max: 100,
                        splitLine: {
                            show: false
                        },
                        axisLine: {
                            lineStyle: {
                                color: "#fff"
                            }
                        }
                    },
                    series: [{
                        data: Config.repayChartRate || [],
                        type: 'line',
                        smooth: true,
                        areaStyle: {
                            opacity: 0.4
                        }
                    }]
                };
                repayRateChart.setOption(option);
            }
            
            // 成功代付订单数趋势（折线图）
            var repayCountChart = Echarts.init(document.getElementById('repay-count-chart'), 'walden');
            if (repayCountChart) {
                var option = {
                    xAxis: {
                        type: 'category',
                        data: Config.repayChartColumn || []
                    },
                    yAxis: {
                        type: 'value'
                    },
                    series: [{
                        data: Config.repayChartOrder || [],
                        type: 'line'
                    }]
                };
                repayCountChart.setOption(option);
            }
            
            // 窗口大小改变时调整所有图表
            $(window).resize(function () {
                if (repayLineChart) repayLineChart.resize();
                if (repayBarChart) repayBarChart.resize();
                if (repayMoneyChart) repayMoneyChart.resize();
                if (repayRateChart) repayRateChart.resize();
                if (repayCountChart) repayCountChart.resize();
            });
            
            // 监听标签页切换，重新调整图表大小
            $(document).on("shown.bs.tab", 'a[data-toggle="tab"]', function () {
                setTimeout(function () {
                    if (repayLineChart) repayLineChart.resize();
                    if (repayBarChart) repayBarChart.resize();
                    if (repayMoneyChart) repayMoneyChart.resize();
                    if (repayRateChart) repayRateChart.resize();
                    if (repayCountChart) repayCountChart.resize();
                }, 100);
            });
        }
    };

    return Controller;
});

