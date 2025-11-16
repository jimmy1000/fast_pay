define(['jquery', 'bootstrap', 'backend', 'table', 'form', 'template'], function ($, undefined, Backend, Table, Form, Template) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'api/type/index' + location.search,
                    add_url: 'api/type/add',
                    edit_url: 'api/type/edit',
                    del_url: 'api/type/del',
                    multi_url: 'api/type/multi',
                    import_url: 'api/type/import',
                    table: 'api_type',
                }
            });

            var table = $("#table");
            
            // 在普通搜索渲染后初始化下拉框
            table.on('post-common-search.bs.table', function (event, table) {
                var form = $("form", table.$commonsearch);
                Form.events.selectpicker(form);
            });

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                fixedColumns: true,
                fixedRightNumber: 1,
                commonSearch: true,
                searchFormVisible: false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'code', title: __('Code'), operate: 'LIKE'},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1')}, formatter: Table.api.formatter.status},
                        {field: 'default', title: __('Default'), searchList: {"0":__('Default 0'),"1":__('Default 1')}, formatter: Table.api.formatter.normal},
                        {field: 'domain', title: __('Domain'), operate: 'LIKE'},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'api_rule_id', title: __('Api_rule_id'), 
                            searchList: function(column) {
                                return Template('apiruletpl', {});
                            },
                            operate: 'in',
                            formatter: function(value, row, index) {
                                return row.rule ? row.rule.name : '-';
                            }
                        },
                        {field: 'weight', title: __('Weight')},
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
