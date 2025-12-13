define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置（仅保留查看功能，关闭新增/编辑/删除等操作）
            Table.api.init({
                extend: {
                    index_url: 'agent/agent/index' + location.search,
                    // 禁用增删改等操作 URL
                    add_url: '',
                    edit_url: '',
                    del_url: '',
                    multi_url: '',
                    import_url: '',
                    table: 'order_agent'
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                columns: [[
                    {checkbox: true},
                    {field: 'id', title: __('Id')},
                    {field: 'myorder.orderno', title: __('order_id')},
                    {field: 'level', title: __('Level')},
                    {field: 'merchant_id', title: __('Merchant_id')},
                    {field: 'money', title: __('Money'), operate: 'BETWEEN'},
                    {field: 'rate', title: __('Rate'), operate: 'BETWEEN'},
                    {
                        field: 'createtime',
                        title: __('Createtime'),
                        operate: 'RANGE',
                        addclass: 'datetimerange',
                        autocomplete: false,
                        formatter: Table.api.formatter.datetime
                    }
                ]]
            });

            // 为表格绑定事件（只读不会触发增删改）
            Table.api.bindevent(table);
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };

    return Controller;
});
