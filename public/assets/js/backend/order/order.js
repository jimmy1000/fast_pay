define(['jquery', 'bootstrap', 'backend', 'table', 'form', 'echarts', 'echarts-theme'], function ($, undefined, Backend, Table, Form, Echarts) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'order/order/index' + location.search,
                    table: 'order',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                fixedColumns: true,
                fixedRightNumber: 1,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id'),visible: false},
                        {field: 'merchant_id', title: __('Merchant_id')},
                        {field: 'orderno', title: __('Orderno'), operate: 'LIKE'},
                        {field: 'sys_orderno', title: __('Sys_orderno'), operate: 'LIKE'},
                        {field: 'up_orderno', title: __('Up_orderno'), operate: 'LIKE'},
                        {field: 'total_money', title: __('Total_money'), operate:'BETWEEN'},
                        {field: 'have_money', title: __('Have_money'), operate:'BETWEEN'},
                        {field: 'agent_money', title: __('Agent_money'), operate:'BETWEEN'},
                        {field: 'upstream_money', title: __('Upstream_money'), operate:'BETWEEN'},
                        {field: 'name', title: __('Name'), operate: 'LIKE', visible: false},
                        {field: 'utr', title: __('Utr'), operate: 'LIKE'},
                        {field: 'phone', title: __('Phone'), visible: false},
                        {field: 'productInfo', title: __('ProductInfo'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content, visible: false},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1'),"2":__('Status 2')}, formatter: Table.api.formatter.status},
                        {field: 'notify_status', title: __('Notify_status'), searchList: {"0":__('Notify_status 0'),"1":__('Notify_status 1'),"2":__('Notify_status 2')}, formatter: Table.api.formatter.status},
                        {field: 'notify_count', title: __('Notify_count')},
                        {field: 'style', title: __('Style'), searchList: {"0":__('Style 0'),"1":__('Style 1')}, formatter: Table.api.formatter.normal},
                        {field: 'rate', title: __('Rate'), operate:'BETWEEN'},
                        {field: 'channel_rate', title: __('Channel_rate'), operate:'BETWEEN'},
                        {field: 'upstream_rate', title: __('Upstream_rate'), operate:'BETWEEN'},
                        {field: 'upstream.name', title: '上游类型', operate: false, formatter: function(value, row, index) {
                            return row.upstream ? row.upstream.name : '-';
                        }},
                        {field: 'account.name', title: '接口账号', operate: false, formatter: function(value, row, index) {
                            return row.account ? row.account.name : '-';
                        }},
                        {field: 'apitype.name', title: '接口类型', operate: false, formatter: function(value, row, index) {
                            return row.apitype ? row.apitype.name : '-';
                        }},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'paytime', title: __('Paytime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'repair', title: __('Repair'), searchList: {"0":__('Repair 0'),"1":__('Repair 1')}, formatter: Table.api.formatter.normal},
                        {field: 'repair_admin_name', title: __('Repair_admin_id'), operate: false, formatter: function(value, row, index) {
                            return value || row.repair_admin_id || '-';
                        }},
                        {field: 'repair_time', title: __('Repair_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'req_ip', title: __('Req_ip'), operate: 'LIKE'},
                        {
                            field: 'operate',
                            title: __('Operate'),
                            table: table,
                            events: Controller.api.events.operate,
                            buttons: [
                                {
                                    name: 'notify',
                                    title: '重发通知',
                                    text: '重发通知',
                                    classname: 'btn btn-xs btn-info btn-dialog',
                                    icon: 'fa fa-send',
                                    url: function (row) {
                                        return 'order/order/notify/id/' + row.id;
                                    },
                                    visible: function(row) {
                                        // 只有支付成功的订单显示重发通知
                                        return row.status == '1' || row.status == '2';
                                    }
                                },
                                {
                                    name: 'chargeback',
                                    title: '手动退单',
                                    text: '手动退单',
                                    classname: 'btn btn-xs btn-warning btn-chargeback',
                                    icon: 'fa fa-undo',
                                    visible: function(row) {
                                        // 只有支付成功的订单显示手动退单
                                        return row.status == '1' || row.status == '2';
                                    }
                                },
                                {
                                    name: 'repair',
                                    title: '手动补单',
                                    text: '手动补单',
                                    classname: 'btn btn-xs btn-success btn-dialog',
                                    icon: 'fa fa-wrench',
                                    url: function (row) {
                                        return 'order/order/repair/ids/' + row.id;
                                    },
                                    visible: function(row) {
                                        // 只有未支付的订单显示手动补单
                                        return row.status == '0' || row.status == '2';
                                    }
                                }
                            ],
                            formatter: Table.api.formatter.operate
                        }
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
            
            // 当表格数据加载完成时，更新统计数据
            table.on('load-success.bs.table', function (e, data) {
                if (data.extend) {
                    // 更新统计卡片
                    $("#todayMoney").text('₫' + (parseFloat(data.extend.todayMoney || 0).toFixed(2)));
                    $("#todayExpenseMoney").text('₫' + (parseFloat(data.extend.todayExpenseMoney || 0).toFixed(2)));
                    $("#allMoney").text('₫' + (parseFloat(data.extend.allMoney || 0).toFixed(2)));
                    $("#allExpenseMoney").text('₫' + (parseFloat(data.extend.allExpenseMoney || 0).toFixed(2)));
                    // 更新工具栏统计信息
                    $("#listMoney").text('₫' + (parseFloat(data.extend.listMoney || 0).toFixed(2)));
                    $("#listHaveMoney").text('₫' + (parseFloat(data.extend.listHaveMoney || 0).toFixed(2)));
                }
            });
            
            // 初始化订单趋势图表
            Controller.initOrderTrendChart();
        },
        
        // 初始化订单趋势图表
        initOrderTrendChart: function() {
            var chartDom = document.getElementById('echart');
            if (!chartDom) {
                return;
            }
            
            var myChart = Echarts.init(chartDom, 'walden');
            
            // 获取图表数据
            var getChartData = function() {
                Fast.api.ajax(Fast.api.fixurl('order/order/chart'), function (result) {
                    // 指定图表的配置项和数据
                    var option = {
                        title: {
                            text: '实时订单趋势'
                        },
                        tooltip: {
                            trigger: 'axis',
                            axisPointer: {
                                type: 'cross',
                                crossStyle: {
                                    color: '#999'
                                }
                            }
                        },
                        legend: {
                            data: ['订单总量', '成功数量', '成功率']
                        },
                        toolbox: {
                            feature: {
                                dataView: {show: true, readOnly: false},
                                magicType: {show: true, type: ['line', 'bar']},
                                restore: {show: true},
                                saveAsImage: {show: true}
                            }
                        },
                        xAxis: {
                            data: result.mins,
                            type: 'category',
                            axisPointer: {
                                type: 'shadow'
                            }
                        },
                        yAxis: [
                            {
                                type: 'value',
                                name: '交易量',
                                min: 0,
                                max: 1000,
                                interval: 100,
                                axisLabel: {
                                    formatter: '{value} 单'
                                }
                            },
                            {
                                type: 'value',
                                name: '成功率',
                                min: 0,
                                max: 100,
                                interval: 10,
                                axisLabel: {
                                    formatter: '{value} %'
                                }
                            }
                        ],
                        series: [
                            {
                                name: '订单总量',
                                type: 'bar',
                                data: result.allList,
                                label: {
                                    normal: {
                                        show: true,
                                        position: 'top',
                                        formatter: "总数：{c}单"
                                    }
                                },
                            },
                            {
                                name: '成功数量',
                                type: 'bar',
                                data: result.succList,
                                label: {
                                    normal: {
                                        show: true,
                                        position: 'top',
                                        formatter: "成功：{c}单"
                                    },
                                },
                            },
                            {
                                name: '成功率',
                                type: 'line',
                                yAxisIndex: 1,
                                data: result.succRateList,
                                label: {
                                    normal: {
                                        show: true,
                                        position: 'top',
                                        formatter: "{c}%"
                                    }
                                },
                            }
                        ]
                    };
                    // 使用刚指定的配置项和数据显示图表。
                    myChart.setOption(option, true);
                    return false;
                });
            };
            
            getChartData();
            
            $(window).resize(function () {
                myChart.resize();
            });
            
            // 每5分钟获取一次
            setInterval(function () {
                getChartData();
            }, 1000 * 60 * 5);
        },
        chargeback:function(){
            Controller.api.bindevent();
        },
        repair:function(){
            Controller.api.bindevent();
        },
        notify: function () {
            Controller.api.bindevent();
            // 绑定重发通知按钮事件
            $(document).off('click.notify', '#notifyBtn').on('click.notify', '#notifyBtn', function () {
                var id = $("#id").val();
                if (!id) {
                    Toastr.error('订单ID不能为空');
                    return false;
                }
                var url = Fast.api.fixurl('order/order/notify/id/' + id);
                Fast.api.ajax(url, function (data, ret) {
                    // 在弹窗内显示成功提示
                    if (ret && ret.code === 1) {
                        Toastr.success(ret.msg || '手动通知成功');
                    }
                    // 刷新父页面表格
                    parent.$(".btn-refresh").trigger("click");
                    return false;
                }, function (data, ret) {
                    // 失败时显示错误提示（阻止默认错误提示，只显示自定义提示）
                    var msg = (ret && ret.msg) ? ret.msg : '手动通知失败';
                    Toastr.error(msg);
                    return false; // 阻止默认错误提示
                });
            });
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            },
            events: {
                operate: {
                    'click .btn-chargeback': function (e, value, row, index) {
                        e.stopPropagation();
                        // 手动退单逻辑
                        Layer.confirm('确认手动退单？已成功的订单退单非常危险！', function(confirmIndex) {
                            Layer.close(confirmIndex);
                            // 输入 Google MFA 验证码
                            Layer.prompt({
                                title: '请输入谷歌验证码',
                                formType: 0,
                                placeholder: '请输入谷歌验证码（未绑定可输入888888）'
                            }, function (code, promptIndex) {
                                Layer.close(promptIndex);
                                if (!code) {
                                    Toastr.error('请输入谷歌验证码');
                                    return;
                                }
                                Fast.api.ajax({
                                    url: 'order/order/chargeback',
                                    data: {
                                        id: row.id,
                                        code: code
                                    }
                                }, function () {
                                    // 刷新表格（和管理员解绑使用相同的方式）
                                    $("#table").bootstrapTable('refresh');
                                });
                            });
                        });
                    },
                }
            }
        }
    };
    return Controller;
});
