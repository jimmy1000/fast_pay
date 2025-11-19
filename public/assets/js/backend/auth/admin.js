define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'auth/admin/index',
                    add_url: 'auth/admin/add',
                    edit_url: 'auth/admin/edit',
                    del_url: 'auth/admin/del',
                    multi_url: 'auth/admin/multi',
                }
            });

            var table = $("#table");

            //在表格内容渲染完成后回调的事件
            table.on('post-body.bs.table', function (e, json) {
                $("tbody tr[data-index]", this).each(function () {
                    if (parseInt($("td:eq(1)", this).text()) == Config.admin.id) {
                        $("input[type=checkbox]", this).prop("disabled", true);
                    }
                });
            });

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                columns: [
                    [
                        {field: 'state', checkbox: true, },
                        {field: 'id', title: 'ID'},
                        {field: 'username', title: __('Username')},
                        {field: 'nickname', title: __('Nickname')},
                        {field: 'groups_text', title: __('Group'), operate:false, formatter: Table.api.formatter.label},
                        {field: 'contacts', title: __('Contacts TG')},
                        {field: 'status', title: __("Status"), searchList: {"normal":__('Normal'),"hidden":__('Hidden')}, formatter: Table.api.formatter.status},
                        {field: 'logintime', title: __('Login time'), formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange', sortable: true},
                        {
                            field: 'operate',
                            title: __('Operate'),
                            table: table,
                            events: $.extend({}, Table.api.events.operate, Controller.events.handler),
                            buttons: [
                                {
                                    name: 'mfabind',
                                    text: '绑定谷歌MFA',
                                    title: '绑定谷歌MFA',
                                    classname: 'btn btn-xs btn-info btn-mfabind',
                                    icon: 'fa fa-lock',
                                    hidden: function (row) {
                                        return row.googlebind == 1 && row.googlesecret;
                                    }
                                },
                                {
                                    name: 'mfaunbind',
                                    text: '解绑谷歌MFA',
                                    title: '解绑谷歌MFA',
                                    classname: 'btn btn-xs btn-warning btn-mfaunbind',
                                    icon: 'fa fa-unlock',
                                    hidden: function (row) {
                                        return !(row.googlebind == 1 && row.googlesecret);
                                    }
                                }
                            ],
                            formatter: Table.api.formatter.operate
                        }
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Form.api.bindevent($("form[role=form]"));
        },
        edit: function () {
            Form.api.bindevent($("form[role=form]"));
        },
        mfabind: function () {
            Controller.api.bindevent();
        },
        mfaunbind: function () {
            Controller.api.bindevent();
        },
        events: {
            handler: {
                'click .btn-mfabind': function (e, value, row, index) {
                    e.stopPropagation();
                    Fast.api.open('auth/admin/mfabind?ids=' + row.id, '绑定谷歌MFA');
                },
                'click .btn-mfaunbind': function (e, value, row, index) {
                    e.stopPropagation();
                    Layer.prompt({
                        title: '请输入谷歌验证码解绑!',
                        formType: 0
                    }, function (code, promptIndex) {
                        Layer.close(promptIndex);
                        Fast.api.ajax({
                            url: 'auth/admin/mfaunbind',
                            data: {id: row.id, code: code}
                        }, function () {
                            $("#table").bootstrapTable('refresh');
                        });
                    });
                }
            }
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});
