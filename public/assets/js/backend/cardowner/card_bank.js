define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'cardowner/card_bank/index' + location.search,
                    add_url: 'cardowner/card_bank/add',
                    edit_url: 'cardowner/card_bank/edit',
                    del_url: 'cardowner/card_bank/del',
                    multi_url: 'cardowner/card_bank/multi',
                    import_url: 'cardowner/card_bank/import',
                    table: 'card_bank',
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
                        {field: 'owner_id', title: __('Owner_id')},
                        {field: 'card_number', title: __('Card_number'), operate: 'LIKE'},
                        {field: 'bank_type', title: __('Bank_type'), operate: 'LIKE'},
                        {field: 'card_type', title: __('Card_type'), searchList: {'0': __('Card_type_0'), '1': __('Card_type_1'), '2': __('Card_type_3')}, formatter: Table.api.formatter.normal},
                        {field: 'card_status', title: __('Card_status'), searchList: {'0': __('Card_status_0'), '1': __('Card_status_1'), '2': __('Card_status_2'), '3': __('Card_status_3')}, formatter: Table.api.formatter.status, custom: {'1': 'success', '2': 'warning', '3': 'danger', '4': 'gray'}},
                        {field: 'qr_code', title: __('Qr_code'), events: Table.api.events.image, formatter: Table.api.formatter.image, operate: false},
                        {field: 'pay_amount', title: __('Pay_amount'), operate:'BETWEEN'},
                        {field: 'repay_amount', title: __('Repay_amount'), operate:'BETWEEN'},
                        {field: 'daily_limit', title: __('Daily_limit'), operate:'BETWEEN'},
                        {field: 'monthly_limit', title: __('Monthly_limit'), operate:'BETWEEN'},
                        {field: 'expiry_date', title: __('Expiry_date'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'phone', title: __('Phone'), operate: 'LIKE'},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
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
