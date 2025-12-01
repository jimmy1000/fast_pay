define(['jquery', 'bootstrap', 'backend', 'table', 'form', 'echarts'], function ($, undefined, Backend, Table, Form, Echarts) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/repayset/index' + location.search,
                    assign_url: 'user/repayset/batchassign',
                    table: 'user',
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
                        {field: 'id', title: 'ID'},
                        {field: 'merchant_id', title: '商户号'},
                        {field: 'username', title: '昵称'},
                        {field: 'daifuid', title: '代付账户', searchList: $.getJSON('api/account/items'), visible: false},
                        {field: 'account.name', title: '代付账户', searchable: false},
                        {
                            field: 'ifdaifuauto',
                            title: '自动代付',
                            searchList: {"0": __('否'), "1": __('是')},
                            formatter: Table.api.formatter.status
                        },
                        {
                            field: 'payrate_percent',
                            title: '费率(%)',
                            formatter: function (value) {
                                // 小于 0 统一显示为“系统默认”
                                if (value === null || value === undefined || value === '') {
                                    return '';
                                }
                                return parseFloat(value) < 0 ? '系统默认' : value;
                            }
                        },
                        {
                            field: 'payrate_each',
                            title: '单笔费用',
                            formatter: function (value) {
                                // 小于 0 统一显示为“系统默认”
                                if (value === null || value === undefined || value === '') {
                                    return '';
                                }
                                return parseFloat(value) < 0 ? '系统默认' : value;
                            }
                        },
                        {
                            field: 'updatetime',
                            title: '更改时间',
                            operate: 'RANGE',
                            addclass: 'datetimerange',
                            formatter: Table.api.formatter.datetime
                        },
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);

            // 批量分配按钮（增加谷歌验证码弹窗）
            $(document).on("click", ".btn-assign", function () {
                var ids = Table.api.selectedids(table);
                if (ids.length === 0) {
                    Layer.alert("请先选择要分配的商户");
                    return;
                }
                Layer.prompt({
                    title: '请输入谷歌验证码',
                    formType: 0,
                    placeholder: '请输入谷歌验证码（未绑定可输入888888）'
                }, function (code, promptIndex) {
                    Layer.close(promptIndex);
                    if (!code) {
                        Toastr.error('请输入谷歌验证码');
                        return;
                    }
                    Fast.api.open(
                        'user/repayset/batchassign?ids=' + ids.join(",") + '&code=' + encodeURIComponent(code),
                        '分配代付账号',
                        {area: ['600px', '500px']}
                    );
                });
            });
        },
        batchassign: function () {
            Form.api.bindevent($("form[role=form]"));
        },
    };
    return Controller;
});


