define(['jquery', 'bootstrap', 'backend', 'table', 'form', 'template'], function ($, undefined, Backend, Table, Form, Template) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'mq/account/index' + location.search,
                    add_url: 'mq/account/add',
                    edit_url: 'mq/account/edit',
                    del_url: 'mq/account/del',
                    multi_url: 'mq/account/multi',
                    import_url: 'mq/account/import',
                    table: 'mq_account',
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
                commonSearch: true,  // 启用普通搜索
                searchFormVisible: false,  // 默认隐藏搜索表单
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'category_id', title: __('Category_id'), 
                            searchList: function(column) {
                                return Template('categorytpl', {});
                            },
                            operate: 'in',
                            formatter: function(value, row, index) {
                                return row.category ? row.category.name : '-';
                            }
                        },
                        {field: 'channel_id', title: __('Channel_id'), 
                            searchList: function(column) {
                                return Template('channeltpl', {});
                            },
                            operate: 'in',
                            formatter: function(value, row, index) {
                                return row.channel ? row.channel.name : '-';
                            }
                        },
                        {field: 'bank_id', title: __('Bank_id'), 
                            searchList: function(column) {
                                return Template('banktpl', {});
                            },
                            operate: 'in',
                            formatter: function(value, row, index) {
                                return row.bank ? row.bank.name : '-';
                            }
                        },
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'number', title: __('Number'), operate: 'LIKE'},
                        {field: 'password', title: __('Password'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'qr', title: __('Qr'), events: Table.api.events.image, formatter: Table.api.formatter.image, operate: 'LIKE'},
                        {field: 'todaymoney', title: __('Todaymoney'), operate:'BETWEEN'},
                        {field: 'maxmoney', title: __('Maxmoney'), operate:'BETWEEN'},
                        {field: 'status', title: __('Status'), formatter: Table.api.formatter.toggle,searchable:false},
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
