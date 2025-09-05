define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'cardowner/card_owner/index' + location.search,
                    add_url: 'cardowner/card_owner/add',
                    edit_url: 'cardowner/card_owner/edit',
                    del_url: 'cardowner/card_owner/del',
                    multi_url: 'cardowner/card_owner/multi',
                    import_url: 'cardowner/card_owner/import',
                    table: 'card_owner',
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
                        {field: 'owner_id', title: __('Owner_sn'), operate: 'LIKE'},
                        {field: 'username', title: __('Username'), operate: 'LIKE'},
                        {field: 'nickname', title: __('Nickname'), operate: 'LIKE'},
                        {field: 'daily_withdraw_ratio', title: __('Daily_withdraw_ratio'), operate:'BETWEEN'},
                        {field: 'deposit_fee_rate', title: __('Deposit_fee_rate'), operate:'BETWEEN'},
                        {field: 'balance', title: __('Balance'), operate:'BETWEEN'},
                        {field: 'total_deposit', title: __('Total_deposit'), operate:'BETWEEN'},
                        {field: 'total_withdraw', title: __('Total_withdraw'), operate:'BETWEEN'},
                        {field: 'contact', title: __('Contact'), operate: 'LIKE'},
                        {field: 'frozen_amount', title: __('Frozen_amount'), operate:'BETWEEN'},
                        {field: 'commission', title: __('Commission'), operate:'BETWEEN'},
                        {field: 'deposit_money', title: __('Deposit_money'), operate:'BETWEEN'},
                        {field: 'matchable_amount', title: __('Matchable_amount'), operate:'BETWEEN'},
                        {field: 'daily_limit', title: __('Daily_limit'), operate:'BETWEEN'},
                        {field: 'remaining_amount', title: __('Remaining_amount'), operate:'BETWEEN'},
                        {field: 'google_bind', title: __('Google_bind'), searchList: {"0":__('Google_bind 0'),"1":__('Google_bind 1')}, formatter: Table.api.formatter.normal},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1'),"2":__('Status 2')}, formatter: Table.api.formatter.status, custom: {'0': 'danger', '1': 'success', '2': 'warning'}},
                        {field: 'last_login_time', title: __('Last_login_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'last_login_ip', title: __('Last_login_ip'), operate: 'LIKE'},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
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
