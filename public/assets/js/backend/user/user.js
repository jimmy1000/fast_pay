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
                        {
                            field: 'handler',
                            title: '业务处理',
                            table: table,
                            operate: false,
                            events: Controller.events.handler,
                            buttons: [
                                {
                                    name: 'reset-key',
                                    title: '重置MD5密钥',
                                    text: '重置秘钥',
                                    classname: 'btn btn-xs btn-primary reset-key',
                                    icon: 'fa fa-key'
                                },
                                {
                                    name: 'clear-google',
                                    title: '解除谷歌令牌绑定',
                                    text: '解除谷歌令牌',
                                    classname: 'btn btn-xs btn-warning clear-googlesecret',
                                    icon: 'fa fa-unlock',
                                    visible: function(row) {
                                        return row.googlebind == '1' || row.googlebind == 1;
                                    }
                                },
                                {
                                    name: 'recharge',
                                    title: '内充余额',
                                    text: '内充余额',
                                    classname: 'btn btn-xs btn-success btn-recharge',
                                    icon: 'fa fa-money'
                                },
                                {
                                    name: 'settlement',
                                    title: '结算余额',
                                    text: '结算余额',
                                    classname: 'btn btn-xs btn-info btn-settlement',
                                    icon: 'fa fa-exchange'
                                }
                            ],
                            formatter: Table.api.formatter.buttons
                        },
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
        recharge: function () {
            Controller.api.bindevent();
        },
        settlement: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        },
        events: {
            handler: {
                'click .reset-key': function (e, value, row) {
                    e.stopPropagation();
                    Layer.confirm(
                        '确定要重置商户【' + (row.merchant_id || row.id) + '】的MD5密钥吗？',
                        {icon: 3, title: '警告', shadeClose: true},
                        function (index) {
                            Fast.api.ajax({
                                url: Fast.api.fixurl('user/user/resetmd5key'),
                                data: {id: row.id}
                            }, function () {
                                Layer.close(index);
                            });
                        }
                    );
                },
                'click .clear-googlesecret': function (e, value, row) {
                    e.stopPropagation();
                    var that = this;
                    var table = $(that).closest('table');
                    Layer.confirm(
                        '确定要解除商户【' + (row.merchant_id || row.id) + '】的谷歌令牌绑定吗？',
                        {icon: 3, title: '警告', shadeClose: true},
                        function (index) {
                            Fast.api.ajax({
                                url: Fast.api.fixurl('user/user/resetGoogleBind'),
                                data: {id: row.id}
                            }, function () {
                                Layer.close(index);
                                table.bootstrapTable('refresh');
                            });
                        }
                    );
                },
                'click .btn-recharge': function (e, value, row) {
                    e.stopPropagation();
                    Fast.api.open(Fast.api.fixurl('user/user/recharge/id/' + row.id), '内充余额');
                },
                'click .btn-settlement': function (e, value, row) {
                    e.stopPropagation();
                    Fast.api.open(Fast.api.fixurl('user/user/settlement/id/' + row.id), '结算余额');
                }
            }
        }
    };
    return Controller;
});