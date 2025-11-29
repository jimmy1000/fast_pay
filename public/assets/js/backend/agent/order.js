define(['jquery', 'bootstrap', 'backend', 'table', 'form','echarts'], function ($, undefined, Backend, Table, Form, Echarts) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'agent/order/index' + location.search,
                    table: 'order',
                }
            });

            var table = $("#table");

            //当表格数据加载完成时
            table.on('load-success.bs.table', function (e, data) {
                if (data.extend) {
                    // 格式化数字，去掉小数点后的.00
                    var formatMoney = function(value) {
                        var num = parseFloat(value || 0);
                        if (num % 1 === 0) {
                            return num.toString();
                        }
                        return num.toFixed(2);
                    };
                    $("#todayMoney").text('₫' + formatMoney(data.extend.todayMoney));
                    $("#todayAgentMoney").text('₫' + formatMoney(data.extend.todayAgentMoney));
                    $("#allMoney").text('₫' + formatMoney(data.extend.allMoney));
                    $("#allAgentMoney").text('₫' + formatMoney(data.extend.allAgentMoney));
                    $("#listMoney").text('₫' + formatMoney(data.extend.listMoney));
                    $("#listAgentMoney").text('₫' + formatMoney(data.extend.listAgentMoney));
                }
            });

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'user.agent_id', title: __('Agent_id')},
                        {field: 'merchant_id', title: __('Merchant_id')},
                        {field: 'orderno', title: __('Orderno'), operate: 'LIKE'},
                        {field: 'sys_orderno', title: __('Sys_orderno'), operate: 'LIKE'},
                        {field: 'total_money', title: __('Total_money'), operate: 'BETWEEN'},
                        {field: 'have_money', title: __('Have_money'), operate: 'BETWEEN', searchable: false},
                        {field: 'agent_money', title: __('Agent_money'), operate: 'BETWEEN'},
                        {field: 'apitype.name', title: '交易类型', searchable: false},
                        {
                            field: 'status',
                            title: __('Status'),
                            searchList: {"0": __('Status 0'), "1": __('Status 1'), "2": __('Status 2')},
                            formatter: Table.api.formatter.status
                        },
                        {field: 'notify_status', title: __('Notify_status'),
                            searchList: {
                                "0": __('Notify_status 0'),
                                "1": __('Notify_status 1'),
                                "2": __('Notify_status 2')
                            },
                            formatter: Table.api.formatter.status
                        },
                        {
                            field: 'api_type_id', title: '接口类型',
                            searchList: $.getJSON('api/type/items'),
                            visible: false
                        },
                        {
                            field: 'createtime',
                            title: __('Createtime'),
                            operate: 'RANGE',
                            addclass: 'datetimerange',
                            formatter: Table.api.formatter.datetime
                        },
                        {
                            field: 'paytime',
                            title: __('Paytime'),
                            operate: 'RANGE',
                            addclass: 'datetimerange',
                            formatter: Table.api.formatter.datetime
                        },
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        },
    };
    return Controller;
});

