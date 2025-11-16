define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'order/notifyLog/index' + location.search,
                    add_url: 'order/notifyLog/add',
                    edit_url: 'order/notifyLog/edit',
                    del_url: 'order/notifyLog/del',
                    multi_url: 'order/notifyLog/multi',
                    import_url: 'order/notifyLog/import',
                    table: 'notify_log',
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
                        {field: 'order.merchant_id', title: __('Merchantid')},
                        {field: 'order.orderno', title: __('Orderno'),operate:'LIKE'},
                        {field: 'notifyurl', title: __('Notifyurl'), operate: 'LIKE', formatter: Table.api.formatter.url},
                        {field: 'result', title: __('Result')},
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
