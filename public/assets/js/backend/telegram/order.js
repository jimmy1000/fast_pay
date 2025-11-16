define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'telegram/order/index' + location.search,
                    add_url: 'telegram/order/add',
                    edit_url: 'telegram/order/edit',
                    del_url: 'telegram/order/del',
                    multi_url: 'telegram/order/multi',
                    import_url: 'telegram/order/import',
                    table: 'telegram_bot_order',
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
                        {field: 'group_chat_id', title: __('Group_chat_id'), operate: 'LIKE'},
                        {field: 'group_title', title: __('Group_title'), operate: 'LIKE'},
                        {field: 'message_id', title: __('Message_id'), operate: 'LIKE'},
                        {field: 'orderno', title: __('Orderno'), operate: 'LIKE'},
                        {field: 'sys_orderno', title: __('Sys_orderno'), operate: 'LIKE'},
                        {field: 'utr', title: __('Utr'), operate: 'LIKE'},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1'),"2":__('Status 2')}, formatter: Table.api.formatter.status},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'updatetime', title: __('Updatetime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
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
