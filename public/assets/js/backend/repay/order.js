define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'repay/order/index' + location.search,
                    table: 'repay_order',
                    multi_url: 'repay/order/multi',
                }
            });

            var table = $("#table");

            //当表格数据加载完成时
            table.on('load-success.bs.table', function (e, data) {
                if (data.extend) {
                    // 格式化数字，去掉小数点后的.00
                    var formatMoney = function(value) {
                        var num = parseFloat(value || 0);
                        if (num % 1 === 0) {
                            return num.toString();
                        }
                        return num.toFixed(2);
                    };
                    $("#todayMoney").text('₫' + formatMoney(data.extend.todayMoney));
                    $("#todaySuccMoney").text('₫' + formatMoney(data.extend.todaySuccMoney));
                    $("#allMoney").text('₫' + formatMoney(data.extend.allMoney));
                    $("#allSuccMoney").text('₫' + formatMoney(data.extend.allSuccMoney));
                    $("#listMoney").text('₫' + formatMoney(data.extend.listMoney));
                    $("#listChargeMoney").text('₫' + formatMoney(data.extend.listChargeMoney));
                    $("#listUpChargeMoney").text('₫' + formatMoney(data.extend.listUpChargeMoney));
                    $("#todayCharge").text('₫' + formatMoney(data.extend.todayCharge));
                    $("#allCharge").text('₫' + formatMoney(data.extend.allCharge));
                    $("#todayUpCharge").text('₫' + formatMoney(data.extend.todayUpCharge));
                    $("#allUpCharge").text('₫' + formatMoney(data.extend.allUpCharge));
                }
            });

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
                        {field: 'merchant_id', title: __('Merchant_id')},
                        {field: 'orderno', title: __('Orderno'), operate: 'LIKE'},
                        {
                            field: 'style',
                            title: __('Style'),
                            searchList: {"0": __('Style 0'), "1": __('Style 1')},
                            formatter: Table.api.formatter.normal
                        },
                        {field: 'bank_code', title: __('BankCode')},
                        {field: 'money', title: __('Money'), operate: 'BETWEEN'},
                        {field: 'name', title: __('Name')},
                        {field: 'account', title: __('Account')},
                        {field: 'phone', title: __('Phone')},
                        {field: 'email', title: __('Email')},
                        {field: 'charge', title: __('Charge'), operate:'BETWEEN'},
                        {
                            field: 'status',
                            title: __('Status'),
                            searchList: {
                                "0": __('Status 0'),
                                "1": __('Status 1'),
                                "2": __('Status 2'),
                                "3": __('Status 3'),
                                "4": __('Status 4'),
                            },
                            formatter: Table.api.formatter.status
                        },
                        {field: 'notify_count', title: __('Notify_count')},
                        {
                            field: 'notify_status',
                            title: __('Notify_status'),
                            searchList: {
                                "0": __('Notify_status 0'),
                                "1": __('Notify_status 1'),
                                "2": __('Notify_status 2')
                            },
                            formatter: Table.api.formatter.status
                        },
                        {
                            field: 'daifustatus',
                            title: __('Daifustatus'),
                            searchList: {
                                "0": __('Daifustatus 0'),
                                "1": __('Daifustatus 1'),
                                "2": __('Daifustatus 2'),
                                "3": __('Daifustatus 3'),
                                "4": __('Daifustatus 4')
                            },
                            formatter: Table.api.formatter.status
                        },
                        {field: 'upcharge', title: __('UpCharge'), operate: 'BETWEEN'},
                        {field: 'req_ip', title: __('Req_ip')},
                        {field: 'utr', title: __('Utr')},
                        {
                            field: 'createtime',
                            title: __('Createtime'),
                            operate: 'RANGE',
                            addclass: 'datetimerange',
                            formatter: Table.api.formatter.datetime
                        },
                        {
                            field: 'paytime',
                            title: __('Paytime'),
                            operate: 'RANGE',
                            addclass: 'datetimerange',
                            formatter: Table.api.formatter.datetime
                        },
                        {
                            field: 'operate',
                            title: __('Operate'),
                            table: table,
                            events: $.extend({}, Table.api.events.operate, Controller.events.handler),
                            formatter: Table.api.formatter.operate,
                            buttons: [
                                {
                                    name: 'repay',
                                    title: '代付',
                                    text: '已取消订单',
                                    classname: 'btn btn-xs btn-danger btn-cancelled',
                                    icon: 'fa fa-ban',
                                    visible: function (row, j) {
                                        return row.status == '3'
                                    }
                                },
                                {
                                    name: 'repay',
                                    title: '代付提交',
                                    text: '代付提交',
                                    classname: 'btn btn-xs btn-primary btn-dialog',
                                    icon: 'fa fa-upload',
                                    visible: function (row, j) {
                                        return row.status != '3'
                                    },
                                    url: function (row, j) {
                                        return 'repay/order/handle/id/' + row.id;
                                    }
                                },
                                //取消按钮
                                {
                                    name: 'cancel',
                                    title: '取消订单',
                                    text: '取消订单',
                                    classname: 'btn btn-xs btn-danger btn-cancel',
                                    icon: 'fa fa-times-circle',
                                    visible: function (row, j) {
                                        return (row.status == '0' || row.status == '2') && (row.daifustatus != '1' && row.daifustatus != '3')
                                    }
                                },
                                //重发通知按钮
                                {
                                    name: 'notify',
                                    title: '重发通知',
                                    text: '重发通知',
                                    classname: 'btn btn-xs btn-success btn-dialog',
                                    icon: 'fa fa-bell',
                                    url: function (row, j) {
                                        return 'repay/order/notify/id/'+row.id;
                                    },
                                    hidden:function (row, j) {
                                        return row.status == '0' || row.style=='0' ;
                                    },
                                }
                            ]
                        }
                    ]
                ]
            });

            // 需要输入谷歌验证码的批量操作（冻结、手动成功、驳回）
            var toolbar = $("#toolbar");
            var needMfaSelector = '.btn-multi[data-params*="status=1"],' +
                '.btn-multi[data-params*="status=2"],' +
                '.btn-multi[data-params*="status=3"]';

            var submitMultiWithCode = function (element, ids, code) {
                var options = table.bootstrapTable('getOptions');
                var data = $(element).data() || {};
                var action = typeof data.action !== 'undefined' ? data.action : '';
                var url = typeof data.url !== 'undefined'
                    ? data.url
                    : (action === 'del' ? options.extend.del_url : options.extend.multi_url);
                var params = typeof data.params !== 'undefined'
                    ? (typeof data.params === 'object' ? $.param(data.params) : data.params)
                    : '';
                params = params ? params + '&code=' + code : 'code=' + code;

                Fast.api.ajax({
                    url: url,
                    data: {
                        action: action,
                        ids: ids.join(','),
                        params: params
                    }
                }, function () {
                    table.trigger('uncheckbox');
                    table.bootstrapTable('refresh');
                });
            };

            toolbar.on('click.mfa', needMfaSelector, function (e) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                var ids = Table.api.selectedids(table);
                if (ids.length === 0) {
                    Toastr.warning('请选择需要处理的订单');
                    return false;
                }
                var that = this;
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
                    submitMultiWithCode(that, ids, code);
                });
            });

            // 为表格绑定事件（放在批量操作逻辑之后，确保事件顺序正确）
            Table.api.bindevent(table);

            $(document).on('click', '.btn-batch-repay', function () {
                var ids = Table.api.selectedids($("#table"));
                if (ids.length === 0) {
                    Toastr.warning('请选择要处理的订单');
                    return;
                }
                Fast.api.open('repay/order/batchhandle?ids=' + ids.join(','), '批量代付提交', {
                    area: ['400px', '400px'],
                });
            });

            $(document).on('click', '.btn-batch-notify', function () {
                var ids = Table.api.selectedids($("#table"));
                if (ids.length === 0) {
                    Toastr.warning('请选择要通知的订单');
                    return;
                }
                Layer.confirm('确定要对选中的订单批量发送通知吗？', {icon: 3, title: '批量重发通知'}, function (index) {
                    Fast.api.ajax({
                        url: 'repay/order/batchnotify',
                        data: { ids: ids.join(',') }
                    }, function (data, ret) {
                        Layer.msg(ret.msg || '批量通知已发送', {time: 2000}, function () {
                            $("#table").bootstrapTable('refresh');
                        });
                    });
                    Layer.close(index);
                });
            });
        },
        batchhandle: function () {
            Form.api.bindevent($("form[role=form]"), function (data, ret) {
                var msg = (ret && ret.msg) ? ret.msg : '批量代付提交成功';
                Layer.msg(msg, {time: 2000}, function () {
                    parent.$("#table").bootstrapTable('refresh');
                    var index = parent.Layer.getFrameIndex(window.name);
                    parent.Layer.close(index);
                });
                return false;
            });
        },
        handle: function () {
            Form.api.bindevent($("form[role=form]"), function (data, ret) {
                Layer.msg(data.msg, {
                    time: 3000
                }, function () {
                    if (parent && parent.$("#table").length) {
                        parent.$("#table").bootstrapTable('refresh');
                    }
                    window.location.reload();
                });
                return false;
            });
        },
        notify:function(){
            Controller.api.bindevent();
            $(document).on('click','#notifyBtn',function () {
                var id = $("#id").val();
                var url = Fast.api.fixurl('repay/order/notify/id/'+id);
                Fast.api.ajax(url,function (data) {
                    parent.$(".btn-refresh").trigger("click");
                });
            });
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        },
        events: {
            handler: {
                'click .btn-cancelled': function (e, value, row, index) {
                    Layer.msg('已取消订单禁止任何操作。');
                },
                'click .btn-cancel': function (e, value, row, index) {
                    e.stopPropagation();
                    e.preventDefault();
                    var that = this;
                    var top = $(that).offset().top - $(window).scrollTop();
                    var left = $(that).offset().left - $(window).scrollLeft() - 260;
                    if (top + 154 > $(window).height()) {
                        top = top - 154;
                    }
                    if ($(window).width() < 480) {
                        top = left = undefined;
                    }
                    Layer.confirm(
                        __('是否确定取消【%s】该笔订单，金额:%s 手续费:%s。', row['orderno'], row['money'], row['charge']),
                        {icon: 3, title: __('Warning'), offset: [top, left], shadeClose: true},
                        function (index) {
                            var table = $(that).closest('table');
                            var options = table.bootstrapTable('getOptions');
                            Fast.api.ajax({
                                url: Fast.api.fixurl('repay/order/cancel'),
                                data: {
                                    id: row[options.pk]
                                }
                            }, function (data, ret) {
                                table.bootstrapTable('refresh');
                                Layer.close(index);
                            })
                        }
                    );
                }
            }
        }
    };
    return Controller;
});
