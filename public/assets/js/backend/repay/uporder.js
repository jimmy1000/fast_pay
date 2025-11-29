define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'repay/uporder/index' + location.search,
                    add_url: 'repay/uporder/add',
                    edit_url: 'repay/uporder/edit',
                    del_url: 'repay/uporder/del',
                    multi_url: 'repay/uporder/multi',
                    import_url: 'repay/uporder/import',
                    table: 'repay_uporder',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'pay_id', title: __('Pay_id'), operate: false, formatter: function(value, row, index) {
                            return row.repayorder && row.repayorder.orderno ? row.repayorder.orderno : (value || '-');
                        }},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1'),"2":__('Status 2'),"3":__('Status 3')}, formatter: Table.api.formatter.status},
                        {field: 'orderno', title: __('Orderno'), operate: 'LIKE'},
                        {field: 'api_account_id', title: __('Api_account_id'), operate: false, formatter: function(value, row, index) {
                            return row.apiaccount && row.apiaccount.name ? row.apiaccount.name : (value || '-');
                        }},
                        {field: 'outorderno', title: __('Outorderno'), operate: 'LIKE'},
                        {field: 'outdesc', title: __('Outdesc'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'paytime', title: __('Paytime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
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
