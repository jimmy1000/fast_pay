define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'cardowner/card_bank_type/index' + location.search,
                    add_url: 'cardowner/card_bank_type/add',
                    edit_url: 'cardowner/card_bank_type/edit',
                    del_url: 'cardowner/card_bank_type/del',
                    multi_url: 'cardowner/card_bank_type/multi',
                    import_url: 'cardowner/card_bank_type/import',
                    table: 'card_bank_type',
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
                        {field: 'bank_code', title: __('Bank_code'), operate: 'LIKE'},
                        {field: 'bank_name', title: __('Bank_name'), operate: 'LIKE'},
                        {field: 'bank_name_en', title: __('Bank_name_en'), operate: 'LIKE'},
                        {field: 'bank_short_name', title: __('Bank_short_name'), operate: 'LIKE'},
                        {field: 'bank_logo', title: __('Bank_logo'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'bank_website', title: __('Bank_website'), operate: 'LIKE'},
                        {field: 'bank_phone', title: __('Bank_phone'), operate: 'LIKE'},
                        {field: 'bank_status', title: __('Bank_status')},
                        {field: 'bank_sort', title: __('Bank_sort')},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
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
