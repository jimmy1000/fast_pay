define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'repay/settle/index' + location.search,
                    add_url: 'repay/settle/add',
                    edit_url: 'repay/settle/edit',
                    del_url: 'repay/settle/del',
                    multi_url: 'repay/settle/multi',
                    import_url: 'repay/settle/import',
                    table: 'repay_settle',
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
                        {field: 'id', title: __('Id')},
                        {field: 'merchant_id', title: __('Merchant_id')},
                        {field: 'orderno', title: __('Orderno'), operate: 'LIKE'},
                        {field: 'style', title: __('Style'), searchList: {"0":__('Style 0'),"1":__('Style 1')}, formatter: Table.api.formatter.normal},
                        {field: 'money', title: __('Money'), operate:'BETWEEN'},
                        {field: 'charge', title: __('Charge'), operate:'BETWEEN'},
                        {field: 'caraddresstype', title: __('Caraddresstype'), searchList: {"TRC20":__('TRC20'),"ERC20":__('ERC20'),"-":__('-')}, formatter: Table.api.formatter.normal},
                        {field: 'caraddress', title: __('Caraddress'), operate: 'LIKE'},
                        {field: 'usdt_rate', title: __('Usdt_rate'), operate:'BETWEEN'},
                        {field: 'usdt', title: __('Usdt'), operate:'BETWEEN'},
                        {field: 'msg', title: __('Msg'), operate: 'LIKE'},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1'),"2":__('Status 2'),"3":__('Status 3'),"4":__('Status 4')}, formatter: Table.api.formatter.status},
                        {field: 'account', title: __('Account'), operate: 'LIKE'},
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'phone', title: __('Phone'), operate: 'LIKE'},
                        {field: 'email', title: __('Email'), operate: 'LIKE'},
                        {field: 'bankname', title: __('Bankname'), operate: 'LIKE'},
                        {field: 'bic', title: __('Bic'), operate: 'LIKE'},
                        {field: 'utr', title: __('Utr'), operate: 'LIKE'},
                        {field: 'req_ip', title: __('Req_ip'), operate: 'LIKE'},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'paytime', title: __('Paytime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
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
