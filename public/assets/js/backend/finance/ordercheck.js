define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'finance/ordercheck/index' + location.search,
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
                    $("#total").text(data.total || 0);
                    $("#allMoney").text('₫' + formatMoney(data.extend.allMoney));
                    $("#haveMoney").text('₫' + formatMoney(data.extend.haveMoney));
                    $("#agentMoney").text('₫' + formatMoney(data.extend.agentMoney));
                    $("#upstreamMoney").text('₫' + formatMoney(data.extend.upstreamMoney));
                    
                    var profitMoney = parseFloat(data.extend.allMoney || 0) - parseFloat(data.extend.haveMoney || 0) - parseFloat(data.extend.agentMoney || 0) - parseFloat(data.extend.upstreamMoney || 0);
                    profitMoney = profitMoney.toFixed(2);
                    $("#profitMoney").text('₫' + formatMoney(profitMoney));
                }
            });

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                search: false,
                searchFormVisible: true,
                searchFormTemplate: 'customformtpl',
                //初始化查询条件
                queryParams: function (params) {
                    var filter = JSON.parse(params.filter || '{}');
                    var op = JSON.parse(params.op || '{}');
                    //强制设置只查询成功和扣量订单
                    filter.status = "1,2";
                    op.status = "IN";
                    params.filter = JSON.stringify(filter);
                    params.op = JSON.stringify(op);
                    return params;
                },
                columns: [
                    [
                        {checkbox: true},
                        {field: 'merchant_id', title: '商户号'},
                        {field: 'orderno', title: '订单号', searchable: false},
                        {field: 'sys_orderno', title: '系统单号', operate: 'LIKE'},
                        {field: 'up_orderno', title: '上游单号', operate: 'LIKE'},
                        {field: 'total_money', title: '订单金额', operate: 'BETWEEN', formatter: Table.api.formatter.amount},
                        {field: 'have_money', title: '支出金额', operate: 'BETWEEN', searchable: false, formatter: Table.api.formatter.amount},
                        {field: 'agent_money', title: '代理金额', operate: 'BETWEEN', searchable: false, formatter: Table.api.formatter.amount},
                        {
                            field: 'createtime',
                            title: '添加时间',
                            operate: 'RANGE',
                            addclass: 'datetimerange',
                            formatter: Table.api.formatter.datetime
                        },
                        {
                            field: 'paytime',
                            title: '支付时间',
                            operate: 'RANGE',
                            addclass: 'datetimerange',
                            formatter: Table.api.formatter.datetime
                        }
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
        events: {
            // 事件处理
            handler: {
            }
        }
    };
    return Controller;
});

