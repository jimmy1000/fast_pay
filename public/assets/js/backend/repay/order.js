define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'repay/order/index' + location.search,
                    add_url: 'repay/order/add',
                    edit_url: 'repay/order/edit',
                    del_url: 'repay/order/del',
                    multi_url: 'repay/order/multi',
                    import_url: 'repay/order/import',
                    table: 'repay_order',
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
                        {field: 'account', title: __('Account'), operate: 'LIKE'},
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'phone', title: __('Phone'), operate: 'LIKE'},
                        {field: 'email', title: __('Email'), operate: 'LIKE'},
                        {field: 'bank_code', title: __('Bank_code'), operate: 'LIKE'},
                        {field: 'notifyUrl', title: __('NotifyUrl'), operate: 'LIKE', formatter: Table.api.formatter.url},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1'),"2":__('Status 2'),"3":__('Status 3'),"4":__('Status 4')}, formatter: Table.api.formatter.status},
                        {field: 'daifustatus', title: __('Daifustatus'), searchList: {"0":__('Daifustatus 0'),"1":__('Daifustatus 1'),"2":__('Daifustatus 2'),"3":__('Daifustatus 3'),"4":__('Daifustatus 4')}, formatter: Table.api.formatter.status},
                        {field: 'charge', title: __('Charge'), operate:'BETWEEN'},
                        {field: 'upcharge', title: __('Upcharge'), operate:'BETWEEN'},
                        {field: 'msg', title: __('Msg'), operate: 'LIKE'},
                        {field: 'bankname', title: __('Bankname'), operate: 'LIKE'},
                        {field: 'utr', title: __('Utr'), operate: 'LIKE'},
                        {field: 'bic', title: __('Bic'), operate: 'LIKE'},
                        {field: 'notify_status', title: __('Notify_status'), searchList: {"0":__('Notify_status 0'),"1":__('Notify_status 1'),"2":__('Notify_status 2')}, formatter: Table.api.formatter.status},
                        {field: 'notify_count', title: __('Notify_count')},
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
