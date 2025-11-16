define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/bankcard/index' + location.search,
                    add_url: 'user/bankcard/add',
                    edit_url: 'user/bankcard/edit',
                    del_url: 'user/bankcard/del',
                    multi_url: 'user/bankcard/multi',
                    import_url: 'user/bankcard/import',
                    table: 'bankcard',
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
                        {field: 'id', title: __('Id')},
                        {field: 'merchant_id', title: __('Merchant_id')},
                        {field: 'bankcardtype', title: __('Bankcardtype'), searchList: {"bank":__('Bank'),"usdt":__('Usdt'),"alipay":__('Alipay')}, formatter: Table.api.formatter.normal},
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'bankaccount', title: __('Bankaccount'), operate: 'LIKE'},
                        {field: 'bankname', title: __('Bankname'), operate: 'LIKE'},
                        {field: 'bic', title: __('Bic'), operate: 'LIKE'},
                        {field: 'email', title: __('Email'), operate: 'LIKE'},
                        {field: 'phone', title: __('Phone'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'caraddresstype', title: __('Caraddresstype'), searchList: {"ERC20":__('ERC20'),"TRC20":__('TRC20'),"-":__('-')}, formatter: Table.api.formatter.normal},
                        {field: 'caraddress', title: __('Caraddress'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1')}, formatter: Table.api.formatter.status},
                        {field: 'checktime', title: __('Checktime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
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
                var $form = $("form[role=form]");
                Form.api.bindevent($form);

                // 选择“支付类型”下拉框与所有声明了可见性规则的表单组
                // 表单组在模板中通过 data-toggle="visible" + data-visible="bankcardtype=..." 定义显示条件
                var $typeSelect = $("#c-bankcardtype");
                var $visibleGroups = $form.find('[data-toggle="visible"][data-visible]');

                if ($typeSelect.length && $visibleGroups.length) {
                    // 根据 bankcardtype 当前值应用显示/隐藏
                    // 支持的表达式示例：bankcardtype=usdt 或 bankcardtype=alipay,bank
                    var applyVisible = function () {
                        var typeVal = ($typeSelect.val() || '').toString().toLowerCase();
                        $visibleGroups.each(function () {
                            var $group = $(this);
                            var expr = ($group.attr('data-visible') || '').trim();
                            var parts = expr.split('=');
                            if (parts.length < 2) {
                                $group.show();
                                return;
                            }
                            var field = (parts[0] || '').trim();
                            var values = (parts.slice(1).join('=') || '').split(',').map(function (s) {
                                return s.trim().toLowerCase();
                            });
                            if (field === 'bankcardtype' && $.inArray(typeVal, values) !== -1) {
                                $group.show();
                            } else {
                                $group.hide();
                            }
                        });
                    };

                    // 监听 bootstrap-select 的 changed.bs.select 以及原生 change 事件
                    $typeSelect.on('changed.bs.select change', applyVisible);
                    applyVisible();

                }
            }
        }
    };
    return Controller;
});
