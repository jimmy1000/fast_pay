define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/user/index',
                    add_url: 'user/user/add',
                    edit_url: 'user/user/edit',
                    del_url: 'user/user/del',
                    multi_url: 'user/user/multi',
                    table: 'ep_user',
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
                        {field: 'id', title: __('Id'), sortable: true},
                        {field: 'group.name', title: __('Group')},
                        {field: 'agent_id', title: __('Agent_id'), operate: 'BETWEEN'},
                        {field: 'merchant_id', title: __('Merchant_id'), operate: 'BETWEEN'},
                        {field: 'username', title: __('Username'), operate: 'LIKE'},
                        {field: 'nickname', title: __('Nickname'), operate: 'LIKE'},
                        {field: 'contacts', title: __('Contacts'), operate: 'LIKE'},
                        {field: 'money', title: __('Money'), operate: 'BETWEEN', sortable: true},
                        {field: 'recharge', title: __('Recharge'), operate: 'BETWEEN'},
                        {field: 'withdrawal', title: __('Withdrawal'), operate: 'BETWEEN'},
                        {field: 'loginip', title: __('Loginip'), operate: 'LIKE'},
                        {field: 'logintime', title: __('Logintime'), operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'createtime', title: __('Createtime'), operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'status', title: __('Status'), formatter: Table.api.formatter.status, searchList: {normal: __('Normal'), hidden: __('Hidden')}},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            // 当表格数据加载完成时，更新统计数据
            table.on('load-success.bs.table', function (e, data) {
                if (data.extend) {
                    // 更新统计卡片
                    $("#allMoney").text('₫' + (data.extend.allMoney || 0));
                    $("#allWithDrayMoney").text('₫' + (data.extend.allWithDrayMoney || 0));
                }
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
            
            // 手动开户按钮事件
            $(document).on('click', '.btn-openaccount', function () {
                var url = $(this).data('url') || $.fn.bootstrapTable.defaults.extend.add_url;
                Fast.api.open(url, '手动开户');
            });
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