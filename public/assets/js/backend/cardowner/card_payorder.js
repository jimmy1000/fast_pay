define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'cardowner/card_payorder/index' + location.search,
                    add_url: 'cardowner/card_payorder/add',
                    edit_url: 'cardowner/card_payorder/edit',
                    del_url: 'cardowner/card_payorder/del',
                    multi_url: 'cardowner/card_payorder/multi',
                    import_url: 'cardowner/card_payorder/import',
                    table: 'card_payorder',
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
                        {field: 'order_no', title: __('Order_no'), operate: 'LIKE'},
                        {field: 'out_trade_no', title: __('Out_trade_no'), operate: 'LIKE'},
                        {field: 'card_owner_id', title: __('Card_owner_id')},
                        {field: 'card_bank_id', title: __('Card_bank_id')},
                        {field: 'channel', title: __('Channel'), operate: 'LIKE'},
                        {field: 'amount', title: __('Amount'), operate:'BETWEEN'},
                        {field: 'fee', title: __('Fee'), operate:'BETWEEN'},
                        {field: 'real_amount', title: __('Real_amount'), operate:'BETWEEN'},
                        {field: 'status', title: __('Status'), searchList: {"pending":__('Pending'),"success":__('Success'),"failed":__('Failed'),"closed":__('Closed'),"refunded":__('Refunded')}, formatter: Table.api.formatter.status},
                        {field: 'pay_method', title: __('Pay_method'), operate: 'LIKE'},
                        {field: 'pay_time', title: __('Pay_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'expired_time', title: __('Expired_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'notify_url', title: __('Notify_url'), operate: 'LIKE', formatter: Table.api.formatter.url},
                        {field: 'return_url', title: __('Return_url'), operate: 'LIKE', formatter: Table.api.formatter.url},
                        {field: 'notify_status', title: __('Notify_status'), searchList: {"none":__('None'),"doing":__('Doing'),"success":__('Success'),"failed":__('Failed')}, formatter: Table.api.formatter.status},
                        {field: 'notify_times', title: __('Notify_times')},
                        {field: 'last_notify_time', title: __('Last_notify_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'last_notify_result', title: __('Last_notify_result'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'payer_name', title: __('Payer_name'), operate: 'LIKE'},
                        {field: 'payer_account', title: __('Payer_account'), operate: 'LIKE'},
                        {field: 'user_agent', title: __('User_agent'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'remark', title: __('Remark'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'updatetime', title: __('Updatetime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        recyclebin: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    'dragsort_url': ''
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: 'cardowner/card_payorder/recyclebin' + location.search,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {
                            field: 'deletetime',
                            title: __('Deletetime'),
                            operate: 'RANGE',
                            addclass: 'datetimerange',
                            formatter: Table.api.formatter.datetime
                        },
                        {
                            field: 'operate',
                            width: '140px',
                            title: __('Operate'),
                            table: table,
                            events: Table.api.events.operate,
                            buttons: [
                                {
                                    name: 'Restore',
                                    text: __('Restore'),
                                    classname: 'btn btn-xs btn-info btn-ajax btn-restoreit',
                                    icon: 'fa fa-rotate-left',
                                    url: 'cardowner/card_payorder/restore',
                                    refresh: true
                                },
                                {
                                    name: 'Destroy',
                                    text: __('Destroy'),
                                    classname: 'btn btn-xs btn-danger btn-ajax btn-destroyit',
                                    icon: 'fa fa-times',
                                    url: 'cardowner/card_payorder/destroy',
                                    refresh: true
                                }
                            ],
                            formatter: Table.api.formatter.operate
                        }
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
