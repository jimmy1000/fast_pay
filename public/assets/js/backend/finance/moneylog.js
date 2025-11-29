define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'finance/moneylog/index' + location.search,
                    add_url: '',
                    edit_url: '',
                    del_url: '',
                    multi_url: 'finance/moneylog/multi',
                    import_url: 'finance/moneylog/import',
                    table: 'user_money_log',
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
                    $("#listMoney").text('₫' + formatMoney(data.extend.listMoney));
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
                        {field: 'id', title: __('Id')},
                        {field: 'user_id', title: __('User_id'), operate: false, formatter: function(value, row, index) {
                            return row.user && row.user.merchant_id ? row.user.merchant_id : (value || '-');
                        }},
                        {field: 'user.username', title: __('Username'), operate: 'LIKE'},
                        {field: 'orderno', title: __('Order'), operate: 'LIKE'},
                        {field: 'money', title: __('Money'), operate:'BETWEEN'},
                        {field: 'before', title: __('Before'), operate:'BETWEEN'},
                        {field: 'after', title: __('After'), operate:'BETWEEN'},
                        {field: 'memo', title: __('Memo'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table, formatter: function() { return ''; }}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});
