define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'api/rule/index' + location.search,
                    add_url: 'api/rule/add',
                    edit_url: 'api/rule/edit',
                    del_url: 'api/rule/del',
                    multi_url: 'api/rule/multi',
                    import_url: 'api/rule/import',
                    table: 'api_rule',
                }
            });

            var table = $("#table");

            // 生成详情表格 HTML（平均 5 列，左对齐）
            function renderDetailTable(list) {
                if (!list || !list.length) {
                    return '<div class="alert alert-info" style="margin:10px;">暂无配置的接口账号</div>';
                }
                var html = [];
                html.push('<table class="table table-bordered table-hover" style="margin:0;width:100%;table-layout:fixed;">');
                html.push('<colgroup>');
                for (var i = 0; i < 5; i++) html.push('<col style="width:20%">');
                html.push('</colgroup>');
                html.push('<thead><tr>');
                html.push('<th style="width:80px;text-align:left;">序号</th>');
                html.push('<th style="text-align:left;">接口账号ID</th>');
                html.push('<th style="text-align:left;">接口账号名称</th>');
                html.push('<th style="width:100px;text-align:left;">权重</th>');
                html.push('<th style="text-align:left;">说明</th>');
                html.push('</tr></thead><tbody>');
                for (var j = 0; j < list.length; j++) {
                    var it = list[j];
                    var desc = it.weight > 1 ? '权重越大，被选中的几率越高' : '默认权重';
                    html.push('<tr>');
                    html.push('<td style="text-align:left;">' + (j + 1) + '</td>');
                    html.push('<td style="text-align:left;">' + it.id + '</td>');
                    html.push('<td style="text-align:left;"><strong>' + it.name + '</strong></td>');
                    html.push('<td style="text-align:left;"><span class="label label-primary">' + it.weight + '</span></td>');
                    html.push('<td class="text-muted" style="text-align:left;"><small>' + desc + '</small></td>');
                    html.push('</tr>');
                }
                html.push('</tbody></table>');
                return html.join('');
            }

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                // 表格结构保持不变，由操作列的 + 按钮控制详情行
                detailView: false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        // 去掉会员ID列
                        // {field: 'user_id', title: __('User_id')},
                        // 支付类型显示为名字
                        {field: 'apitype.name', title: '支付类型', operate: 'LIKE'},
                        {field: 'account_weight_list', title: '接口账号与权重', operate:false, formatter: function (value, row) {
                            var list = value || [];
                            if (!list.length) return '-';
                            var html = [];
                            for (var i = 0; i < list.length; i++) {
                                var item = list[i];
                                html.push(item.name + ' × ' + item.weight);
                            }
                            return html.join('<br/>');
                        }},
                        {field: 'type', title: __('Type'), searchList: {"0":__('Type 0'),"1":__('Type 1'),"2":__('Type 2')}, formatter: Table.api.formatter.normal},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'updatetime', title: __('Updatetime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        // 操作列：新增“详情”按钮
                        // 操作列：新增“展开/收起”按钮，在当前行下方插入详情行
                        {field: 'operate', title: __('Operate'), table: table, events: $.extend({}, Table.api.events.operate, {
                            'click .btn-toggle-detail': function (e, value, row) {
                                if (e && e.preventDefault) e.preventDefault();
                                if (e && e.stopPropagation) e.stopPropagation();
                                if (e && e.stopImmediatePropagation) e.stopImmediatePropagation();
                                var $tr = $(this).closest('tr');
                                var colCount = $tr.children('td').length;
                                var $next = $tr.next('.detail-row');
                                if ($next.length) {
                                    $next.remove();
                                    $(this).find('i').removeClass('fa-minus').addClass('fa-plus');
                                    return false;
                                }
                                var html = renderDetailTable(row.account_weight_list || []);
                                var $detailTr = $('<tr class="detail-row"><td colspan="' + colCount + '">' + html + '</td></tr>');
                                $tr.after($detailTr);
                                $(this).find('i').removeClass('fa-plus').addClass('fa-minus');
                                return false;
                            }
                        }), buttons: [
                            {
                                name: 'toggle',
                                text: '',
                                title: '展开/收起详情',
                                icon: 'fa fa-plus',
                                classname: 'btn btn-xs btn-primary btn-toggle-detail'
                            }
                        ], formatter: Table.api.formatter.operate}
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
                // FastAdmin的Form.api.bindevent默认会在成功后触发刷新
                // 但为了确保所有记录都显示，我们显式触发刷新按钮
                Form.api.bindevent($form, function(data, ret) {
                    // 延迟刷新，确保弹窗已完全关闭
                    setTimeout(function() {
                        if (typeof parent !== 'undefined' && parent.$(".btn-refresh").length > 0) {
                            // 弹窗模式：触发父窗口的刷新按钮
                            parent.$(".btn-refresh").trigger("click");
                        } else if ($(".btn-refresh").length > 0) {
                            // 直接页面模式
                            $(".btn-refresh").trigger("click");
                        }
                    }, 200);
                    return true;
                });

                function dragevent() {
                    require(['dragsort'], function () {
                        $("#account_ids table").dragsort({
                            itemSelector: 'tr',
                            dragSelector: '.btn-dragsort',
                            dragEnd: function () {},
                            placeHolderTemplate: '<tr></tr>'
                        });
                    });
                }

                // 根据支付类型加载账号
                function loadAccountsByType(typeId) {
                    if (!typeId) {
                        $("#account_ids").html('');
                        return;
                    }
                    Fast.api.ajax({
                        url: Fast.api.fixurl('api/type/getAccount'),
                        data: {id: typeId},
                        type: 'POST'
                    }, function (resp) {
                        var list = resp && resp.data ? resp.data : resp;
                        var html = Template('params-tpl', {params: list || []});
                        $("#account_ids").html(html || '');
                        dragevent();
                        return false;
                    });
                }

                // 选择支付类型
                $(document).on('change', '#api_type_container .sp_input', function () {
                    var typeId = $(this).closest('.sp_container').find('.sp_hidden').val();
                    loadAccountsByType(typeId);
                    // 已取消“根据支付类型刷新调用方式”的自动请求
                });

                // 某些版本 selectpage 不触发 .sp_input 的 change，这里兜底直接监听隐藏input本体
                $(document).on('change', '#c-api_type_id', function(){
                    var typeId = $(this).val();
                    loadAccountsByType(typeId);
                });

                dragevent();
            }
        }
    };
    return Controller;
});
