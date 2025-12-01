define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'user/apichannel/index',
                    table: 'user',
                }
            });
            
            var table = $("#table");
            
            // 添加全部展开/收起功能
            var isAllExpanded = false;
            
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'user.id',
                detailView: true,
                detailFormatter: Controller.api.detailFormatter,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id'), visible: false},
                        {field: 'merchant_id', title: __('商户号')},
                        {field: 'agent_id', title: '上级代理'},
                        {field: 'group.name', title: __('Group')},
                        {field: 'username', title: __('Username'), operate: 'LIKE'},
                        {field: 'nickname', title: __('Nickname'), operate: 'LIKE'},
                        {field: 'status', title: __('Status'), formatter: Table.api.formatter.status, searchList: {normal: __('Normal'), hidden: __('Hidden')}},
                        {
                            field: 'handler', 
                            title: __('业务处理'), 
                            table: table, 
                            searchable: false,
                            events: Controller.events.handler,
                            buttons: [
                                {
                                    name: 'content',
                                    text: '<i class="fa fa-sliders"></i> 费率规则设置',
                                    classname: 'btn btn-xs btn-warning setting-channel',
                                    title: '设置用户费率规则'
                                }
                            ],
                            formatter: Table.api.formatter.buttons
                        }
                    ]
                ]
            });
            
            Table.api.bindevent(table);
            
            // 全部展开/收起切换按钮
            $(document).on('click', '.btn-toggle-expand', function (e) {
                e.preventDefault();
                var $btn = $(this);
                var $icon = $btn.find('i');
                var rows = table.bootstrapTable('getData');
                
                if (!isAllExpanded) {
                    // 展开所有
                    $icon.removeClass('fa-plus-square-o').addClass('fa-minus-square-o');
                    $btn.html('<i class="fa fa-minus-square-o"></i> 收起所有');
                    $.each(rows, function(index, row) {
                        table.bootstrapTable('expandRow', index);
                    });
                    isAllExpanded = true;
                } else {
                    // 收起所有
                    $icon.removeClass('fa-minus-square-o').addClass('fa-plus-square-o');
                    $btn.html('<i class="fa fa-plus-square-o"></i> 展开所有');
                    $.each(rows, function(index, row) {
                        table.bootstrapTable('collapseRow', index);
                    });
                    isAllExpanded = false;
                }
            });
            
            // 批量设置按钮点击事件（增加谷歌验证码弹窗）
            $(document).on('click', '.btn-batch-channel', function () {
                var ids = Table.api.selectedids(table);
                if (ids.length === 0) {
                    Layer.alert("请先选择商户");
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
                    var url = Fast.api.fixurl('user/apichannel/batch?user_ids=' + ids.join(',') + '&code=' + encodeURIComponent(code));
                    Fast.api.open(url, "批量设置通道费率", {
                        area: ['90%', '90%']
                    });
                });
            });
        },
        
        batch: function () {
            Form.api.bindevent($("form[role=form]"));
        },
        
        single: function () {
            Form.api.bindevent($("form[role=form]"));
        },
        
        api: {
            // 子表格式化函数
            detailFormatter: function (index, row) {
                var html = '<div class="detail-loading">加载中...</div>';
                
                // 异步加载该用户的通道配置
                $.ajax({
                    url: Fast.api.fixurl('user/apichannel/getUserChannels'),
                    type: 'GET',
                    data: {user_id: row.id},
                    dataType: 'json',
                    success: function(ret) {
                        var detailDiv = $('tr[data-index="' + index + '"]').next().find('.detail-loading');
                        
                        if (ret.code === 1 && ret.data && ret.data.length > 0) {
                            var table = '<table class="table table-bordered table-striped" style="margin:10px;">';
                            table += '<thead>';
                            table += '<tr>';
                            table += '<th>支付类型</th>';
                            table += '<th>费率(%)</th>';
                            table += '<th>接口规则</th>';
                            table += '<th>状态</th>';
                            table += '</tr>';
                            table += '</thead>';
                            table += '<tbody>';
                            
                            $.each(ret.data, function(i, item) {
                                var statusClass = item.status == '1' ? 'success' : 'danger';
                                var statusText = item.status == '1' ? '开启' : '关闭';
                                
                                table += '<tr>';
                                table += '<td>' + (item.type_name || '-') + '</td>';
                                table += '<td>' + item.rate + '</td>';
                                table += '<td>' + (item.rule_name || '系统默认') + '</td>';
                                table += '<td><span class="label label-' + statusClass + '">' + statusText + '</span></td>';
                                table += '</tr>';
                            });
                            
                            table += '</tbody>';
                            table += '</table>';
                            
                            detailDiv.html(table);
                        } else {
                            detailDiv.html('<div class="alert alert-info" style="margin:10px;">该用户暂未配置通道</div>');
                        }
                    },
                    error: function() {
                        var detailDiv = $('tr[data-index="' + index + '"]').next().find('.detail-loading');
                        detailDiv.html('<div class="alert alert-danger" style="margin:10px;">加载失败</div>');
                    }
                });
                
                return html;
            }
        },
        
        events: {
            handler: {
                'click .setting-channel': function (e, value, row, index) {
                    e.stopPropagation();
                    var that = this;
                    var table = $(that).closest('table');
                    var options = table.bootstrapTable('getOptions');
                    var user_id = row[options.pk];
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
                        var url = Fast.api.fixurl('user/apichannel/single/id/' + user_id + '/code/' + encodeURIComponent(code));
                        Fast.api.open(url, '费率设置 - ' + row['merchant_id']);
                    });
                }
            }
        }
    };
    
    return Controller;
});

