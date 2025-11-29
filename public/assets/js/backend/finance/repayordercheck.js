define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'finance/repayordercheck/index' + location.search,
                    table: 'repay_order',
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
                    $("#charge").text('₫' + formatMoney(data.extend.charge));
                    $("#upstreamCharge").text('₫' + formatMoney(data.extend.upstreamCharge));
                    
                    var profitMoney = parseFloat(data.extend.allMoney || 0) - parseFloat(data.extend.charge || 0);
                    var profitCharg = parseFloat(data.extend.charge || 0) - parseFloat(data.extend.upstreamCharge || 0);
                    profitMoney = profitMoney.toFixed(2);
                    profitCharg = profitCharg.toFixed(2);
                    $("#profitMoney").text('₫' + formatMoney(profitMoney));
                    $("#profitCharg").text('₫' + formatMoney(profitCharg));
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
                    //强制设置只查询已支付订单
                    filter.status = "1";
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
                        {field: 'money', title: '订单金额', operate: 'BETWEEN', formatter: Table.api.formatter.amount},
                        {field: 'charge', title: '手续费', operate: 'BETWEEN', searchable: false, formatter: Table.api.formatter.amount},
                        {field: 'upcharge', title: '上游手续费', operate: 'BETWEEN', searchable: false, formatter: Table.api.formatter.amount},
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

