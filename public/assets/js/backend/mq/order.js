define(['jquery', 'bootstrap', 'backend', 'table', 'form', 'template'], function ($, undefined, Backend, Table, Form, Template) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'mq/order/index' + location.search,
                    import_url: 'mq/order/import',
                    table: 'mq_order',
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
                commonSearch: true,  // 启用普通搜索
                searchFormVisible: true,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'orderno', title: __('Orderno'), operate: 'LIKE'},
                        {field: 'price', title: __('Price'), operate:'BETWEEN'},
                        {field: 'realprice', title: __('Realprice'), operate:'BETWEEN'},
                        {field: 'channel', title: __('Channel'), operate: 'LIKE'},
                        {field: 'bank_id', title: __('Bank_id'), 
                            searchList: function(column) {
                                return Template('banktpl', {});
                            },
                            operate: 'in',
                            formatter: function(value, row, index) {
                                return row.bank ? row.bank.name : '-';
                            }
                        },
                        {field: 'category_id', title: __('Category_id'), 
                            searchList: function(column) {
                                return Template('categorytpl', {});
                            },
                            operate: 'in',
                            formatter: function(value, row, index) {
                                return row.category ? row.category.name : '-';
                            }
                        },
                        {field: 'account_id', title: __('Account_id'), 
                            searchList: function(column) {
                                return Template('accounttpl', {});
                            },
                            operate: 'in',
                            formatter: function(value, row, index) {
                                return row.account ? row.account.name : '-';
                            }
                        },
                        {field: 'account.number', title: __('Number'), operate: 'LIKE'},
                        {field: 'account.qr', title: __('Qr'), events: Table.api.events.image, formatter: Table.api.formatter.image, operate: 'LIKE'},
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
