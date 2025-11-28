define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'repay/settle/index' + location.search,
                    add_url: 'repay/settle/add',
                    edit_url: 'repay/settle/edit',
                    del_url: 'repay/settle/del',
                    multi_url: 'repay/settle/multi',
                    import_url: 'repay/settle/import',
                    table: 'repay_settle',
                }
            });

            var table = $("#table");

            // 心情语列表
            var quotes = [
                '努力工作，认真生活',
                '细节决定成败，态度决定一切',
                '今天的努力，是明天的收获',
                '用心服务，诚信经营',
                '每一笔结算都值得认真对待',
                '效率与准确并重',
                '专业、高效、可靠',
                '让每一笔交易都安全可靠',
                '持续改进，追求卓越',
                '客户满意是我们的目标'
            ];

            // 名言名句鸡汤列表
            var famousQuotes = [
                '成功不是终点，失败也不是末日',
                '路虽远行则将至，事虽难做则必成',
                '不积跬步，无以至千里',
                '宝剑锋从磨砺出，梅花香自苦寒来',
                '天行健，君子以自强不息',
                '千里之行，始于足下',
                '有志者事竟成，破釜沉舟，百二秦关终属楚',
                '业精于勤荒于嬉，行成于思毁于随',
                '世上无难事，只怕有心人',
                '山重水复疑无路，柳暗花明又一村',
                '不经一番寒彻骨，怎得梅花扑鼻香',
                '长风破浪会有时，直挂云帆济沧海',
                '精诚所至，金石为开',
                '锲而不舍，金石可镂',
                '只要功夫深，铁杵磨成针',
                '世上无难事，只怕有心人',
                '路漫漫其修远兮，吾将上下而求索'
            ];

            // 初始化心情语
            function initDailyQuote() {
                var today = new Date().getDate();
                var quoteIndex = today % quotes.length;
                $("#quoteText").text(quotes[quoteIndex]);
            }

            // 初始化名言名句
            function initFamousQuote() {
                var today = new Date().getDate();
                var quoteIndex = today % famousQuotes.length;
                $("#famousQuote").text(famousQuotes[quoteIndex]);
            }

            // 初始化心情语和名言名句
            initDailyQuote();
            initFamousQuote();

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
                    $("#todayCharge").text('₫' + formatMoney(data.extend.todayCharge));
                    $("#allCharge").text('₫' + formatMoney(data.extend.allCharge));
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
                        {field: 'style', title: __('Style'), searchList: {"0":__('法币结算'),"1":__('USDT下发')}, formatter: Table.api.formatter.normal},
                        {field: 'apply_style', title: __('Apply_style'), searchList: {"0":__('商户后台'),"1":__('系统后台')}, formatter: Table.api.formatter.normal},
                        {field: 'money', title: __('Money'), operate:'BETWEEN'},
                        {field: 'charge', title: __('Charge'), operate:'BETWEEN'},
                        {field: 'caraddresstype', title: __('Caraddresstype'), searchList: {"TRC20":__('TRC20'),"ERC20":__('ERC20'),"-":__('-')}, formatter: Table.api.formatter.normal},
                        {field: 'caraddress', title: __('Caraddress'), operate: 'LIKE'},
                        {field: 'usdt_rate', title: __('Usdt_rate'), operate:'BETWEEN'},
                        {field: 'usdt', title: __('Usdt'), operate:'BETWEEN'},
                        {field: 'msg', title: __('Msg'), operate: 'LIKE'},
                        {field: 'image', title: __('Image'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'status', title: __('Status'), searchList: {"0":__('审核中'),"1":__('已支付'),"2":__('取消'),"3":__('打款中'),"4":__('关闭')}, formatter: Table.api.formatter.status},
                        {field: 'account', title: __('Account'), operate: 'LIKE'},
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'phone', title: __('Phone'), operate: 'LIKE'},
                        {field: 'email', title: __('Email'), operate: 'LIKE'},
                        {field: 'bankname', title: __('Bankname'), operate: 'LIKE'},
                        {field: 'bic', title: __('Bic'), operate: 'LIKE'},
                        {field: 'utr', title: __('Utr'), operate: 'LIKE'},
                        {field: 'req_ip', title: __('Req_ip'), operate: 'LIKE'},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'paytime', title: __('Paytime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {
                            field: 'operate',
                            title: __('Operate'),
                            table: table,
                            events: Controller.events.handler,
                            formatter: Table.api.formatter.buttons,
                            buttons: [
                                // 手动成功按钮
                                {
                                    name: 'success',
                                    title: '手动成功',
                                    text: '手动成功',
                                    classname: 'btn btn-xs btn-success btn-success',
                                    icon: 'fa fa-check',
                                    visible: function (row, j) {
                                        return row.status == '0' || row.status == '3'
                                    }
                                },
                                // 驳回结算按钮
                                {
                                    name: 'cancel',
                                    title: '驳回结算',
                                    text: '驳回结算',
                                    classname: 'btn btn-xs btn-danger btn-cancel',
                                    icon: 'fa fa-times-circle',
                                    visible: function (row, j) {
                                        return row.status == '0' || row.status == '3'
                                    }
                                },
                                // 上传截图按钮（使用编辑功能）
                                {
                                    name: 'edit',
                                    title: '上传截图',
                                    text: '上传截图',
                                    classname: 'btn btn-xs btn-info btn-dialog',
                                    icon: 'fa fa-image',
                                    visible: function (row, j) {
                                        return row.status == '1' || row.status == '2'
                                    },
                                    url: function (row, j) {
                                        return 'repay/settle/edit/ids/' + row.id;
                                    }
                                }
                            ]
                        }
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
        },
        events: {
            handler: {
                // 手动成功按钮事件
                'click .btn-success': function (e, value, row, index) {
                    e.stopPropagation();
                    e.preventDefault();
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
                        var table = $(that).closest('table');
                        var options = table.bootstrapTable('getOptions');
                        Fast.api.ajax({
                            url: Fast.api.fixurl('repay/settle/manualSuccess'),
                            data: {
                                id: row[options.pk],
                                code: code
                            }
                        }, function (data, ret) {
                            table.bootstrapTable('refresh');
                        });
                    });
                },
                // 驳回结算按钮事件
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
                        __('是否确定驳回【%s】该笔结算，金额:%s 手续费:%s。', row['orderno'], row['money'], row['charge']),
                        {icon: 3, title: __('Warning'), offset: [top, left], shadeClose: true},
                        function (index) {
                            Layer.prompt({
                                title: '请输入谷歌验证码',
                                formType: 0,
                                placeholder: '请输入谷歌验证码（未绑定可输入888888）'
                            }, function (code, promptIndex) {
                                Layer.close(promptIndex);
                                if (!code) {
                                    Toastr.error('请输入谷歌验证码');
                                    Layer.close(index);
                                    return;
                                }
                                var table = $(that).closest('table');
                                var options = table.bootstrapTable('getOptions');
                                Fast.api.ajax({
                                    url: Fast.api.fixurl('repay/settle/cancel'),
                                    data: {
                                        id: row[options.pk],
                                        code: code
                                    }
                                }, function (data, ret) {
                                    table.bootstrapTable('refresh');
                                    Layer.close(index);
                                });
                            });
                        }
                    );
                }
            }
        }
    };
    return Controller;
});
