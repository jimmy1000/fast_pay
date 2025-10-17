define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'cardowner/card_repayorder/index' + location.search,
                    add_url: 'cardowner/card_repayorder/add',
                    edit_url: 'cardowner/card_repayorder/edit',
                    del_url: 'cardowner/card_repayorder/del',
                    multi_url: 'cardowner/card_repayorder/multi',
                    import_url: 'cardowner/card_repayorder/import',
                    table: 'card_repayorder',
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
                        {field: 'pay_method', title: __('Pay_method'), operate: 'LIKE'},
                        {field: 'amount', title: __('Amount'), operate:'BETWEEN'},
                        {field: 'fee', title: __('Fee'), operate:'BETWEEN'},
                        {field: 'real_amount', title: __('Real_amount'), operate:'BETWEEN'},
                        {field: 'payee_name', title: __('Payee_name'), operate: 'LIKE'},
                        {field: 'payee_account', title: __('Payee_account'), operate: 'LIKE'},
                        {field: 'payee_phone', title: __('Payee_phone'), operate: 'LIKE'},
                        {field: 'payee_id_type', title: __('Payee_id_type'), operate: 'LIKE'},
                        {field: 'payee_id_no', title: __('Payee_id_no'), operate: 'LIKE'},
                        {field: 'bank_name', title: __('Bank_name'), operate: 'LIKE'},
                        {field: 'bank_code', title: __('Bank_code'), operate: 'LIKE'},
                        {field: 'bank_swift', title: __('Bank_swift'), operate: 'LIKE'},
                        {field: 'bank_branch', title: __('Bank_branch'), operate: 'LIKE'},
                        {field: 'bank_branch_code', title: __('Bank_branch_code'), operate: 'LIKE'},
                        {field: 'province', title: __('Province'), operate: 'LIKE'},
                        {field: 'city', title: __('City'), operate: 'LIKE'},
                        {field: 'district', title: __('District'), operate: 'LIKE'},
                        {field: 'status', title: __('Status'), searchList: {"pending":__('Pending'),"processing":__('Processing'),"success":__('Success'),"failed":__('Failed'),"closed":__('Closed')}, formatter: Table.api.formatter.status},
                        {field: 'submit_time', title: __('Submit_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'success_time', title: __('Success_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'fail_reason', title: __('Fail_reason'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'batch_no', title: __('Batch_no'), operate: 'LIKE'},
                        {field: 'notify_url', title: __('Notify_url'), operate: 'LIKE', formatter: Table.api.formatter.url},
                        {field: 'notify_status', title: __('Notify_status'), searchList: {"none":__('None'),"doing":__('Doing'),"success":__('Success'),"failed":__('Failed')}, formatter: Table.api.formatter.status},
                        {field: 'notify_times', title: __('Notify_times')},
                        {field: 'last_notify_time', title: __('Last_notify_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'last_notify_result', title: __('Last_notify_result'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'client_ip', title: __('Client_ip'), operate: 'LIKE'},
                        {field: 'user_agent', title: __('User_agent'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'remark', title: __('Remark'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'ext', title: __('Ext')},
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
                url: 'cardowner/card_repayorder/recyclebin' + location.search,
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
                                    url: 'cardowner/card_repayorder/restore',
                                    refresh: true
                                },
                                {
                                    name: 'Destroy',
                                    text: __('Destroy'),
                                    classname: 'btn btn-xs btn-danger btn-ajax btn-destroyit',
                                    icon: 'fa fa-times',
                                    url: 'cardowner/card_repayorder/destroy',
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
