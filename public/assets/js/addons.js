define([], function () {
    require(['form', 'upload'], function (Form, Upload) {
    var _bindevent = Form.events.bindevent;
    Form.events.bindevent = function (form) {
        _bindevent.apply(this, [form]);

        if ($("#croppertpl").length == 0) {
            var allowAttr = [
                'aspectRatio', 'autoCropArea', 'cropBoxMovable', 'cropBoxResizable', 'minCropBoxWidth', 'minCropBoxHeight', 'minContainerWidth', 'minContainerHeight',
                'minCanvasHeight', 'minCanvasWidth', 'croppedWidth', 'croppedHeight', 'croppedMinWidth', 'croppedMinHeight', 'croppedMaxWidth', 'croppedMaxHeight', 'fillColor',
                'containerMinHeight', 'containerMaxHeight', 'customWidthHeight', 'customAspectRatio'
            ];
            String.prototype.toLineCase = function () {
                return this.replace(/[A-Z]/g, function (match) {
                    return "-" + match.toLowerCase();
                });
            };

            var btnAttr = [];
            $.each(allowAttr, function (i, j) {
                btnAttr.push('data-' + j.toLineCase() + '="<%=data.' + j + '%>"');
            });

            var btn = '<button class="btn btn-success btn-cropper btn-xs" data-input-id="<%=data.inputId%>" ' + btnAttr.join(" ") + ' style="position:absolute;top:10px;right:15px;">裁剪</button>';

            var insertBtn = function () {
                return arguments[0].replace(arguments[2], btn + arguments[2]);
            };
            $("<script type='text/html' id='croppertpl'>" + Upload.config.previewtpl.replace(/<li(.*?)>(.*?)<\/li>/, insertBtn) + "</script>").appendTo("body");
        }

        $(".plupload[data-preview-id],.faupload[data-preview-id]").each(function () {
            var preview_id = $(this).data("preview-id");
            var previewObj = $("#" + preview_id);
            var tpl = previewObj.length > 0 ? previewObj.data("template") : '';
            if (!tpl) {
                if (!$(this).hasClass("cropper")) {
                    $(this).addClass("cropper");
                }
                previewObj.data("template", "croppertpl");
            }
        });

        //图片裁剪
        $(document).off('click', '.btn-cropper').on('click', '.btn-cropper', function () {
            var image = $(this).closest("li").find('.thumbnail').data('url');
            var input = $("#" + $(this).data("input-id"));
            var url = image;
            var data = $(this).data();
            var params = [];
            $.each(allowAttr, function (i, j) {
                if (typeof data[j] !== 'undefined' && data[j] !== '') {
                    params.push(j + '=' + data[j]);
                }
            });
            try {
                var parentWin = (parent ? parent : window);
                parentWin.Fast.api.open('/addons/cropper/index/cropper?url=' + image + (params.length > 0 ? '&' + params.join('&') : ''), '裁剪', {
                    callback: function (data) {
                        if (typeof data !== 'undefined') {
                            var arr = data.dataURI.split(','), mime = arr[0].match(/:(.*?);/)[1],
                                bstr = atob(arr[1]), n = bstr.length, u8arr = new Uint8Array(n);
                            while (n--) {
                                u8arr[n] = bstr.charCodeAt(n);
                            }
                            var urlArr = url.split('.');
                            var suffix = 'png';
                            url = urlArr.join('');
                            var filename = url.substr(url.lastIndexOf('/') + 1);
                            var exp = new RegExp("\\." + suffix + "$", "i");
                            filename = exp.test(filename) ? filename : filename + "." + suffix;
                            var file = new File([u8arr], filename, {type: mime});
                            Upload.api.send(file, function (data) {
                                input.val(input.val().replace(image, data.url)).trigger("change");
                            }, function (data) {
                            });
                        }
                    },
                    area: [Math.min(parentWin.$(parentWin.window).width(), Config.cropper.dialogWidth) + "px", Math.min(parentWin.$(parentWin.window).height(), Config.cropper.dialogHeight) + "px"],
                });
            } catch (e) {
                console.error(e);
            }
            return false;
        });
    }
});

require.config({
    paths: {
        'editable': '../libs/bootstrap-table/dist/extensions/editable/bootstrap-table-editable.min',
        'x-editable': '../addons/editable/js/bootstrap-editable.min',
    },
    shim: {
        'editable': {
            deps: ['x-editable', 'bootstrap-table']
        },
        "x-editable": {
            deps: ["css!../addons/editable/css/bootstrap-editable.css"],
        }
    }
});
if ($("table.table").length > 0) {
    require(['editable', 'table'], function (Editable, Table) {
        $.fn.bootstrapTable.defaults.onEditableSave = function (field, row, oldValue, $el) {
            var data = {};
            data["row[" + field + "]"] = row[field];
            Fast.api.ajax({
                url: this.extend.edit_url + "/ids/" + row[this.pk],
                data: data
            });
        };
    });
}

require.config({
    paths: {
        'summernote': '../addons/summernote/lang/summernote-zh-CN.min',
        'purify': '../addons/summernote/js/purify.min'
    },
    shim: {
        'summernote': ['../addons/summernote/js/summernote.min', 'css!../addons/summernote/css/summernote.min.css'],
    }
});
require(['form', 'upload'], function (Form, Upload) {
    var _bindevent = Form.events.bindevent;
    Form.events.bindevent = function (form) {
        _bindevent.apply(this, [form]);
        try {
            //绑定summernote事件
            if ($(Config.summernote.classname || '.editor', form).length > 0) {
                var selectUrl = typeof Config !== 'undefined' && Config.modulename === 'index' ? 'user/attachment' : 'general/attachment/select';
                require(['summernote', 'purify'], function (undefined, DOMPurify) {
                    var imageButton = function (context) {
                        var ui = $.summernote.ui;
                        var button = ui.button({
                            contents: '<i class="fa fa-file-image-o"/>',
                            tooltip: __('Choose'),
                            click: function () {
                                parent.Fast.api.open(selectUrl + "?element_id=&multiple=true&mimetype=image/", __('Choose'), {
                                    callback: function (data) {
                                        var urlArr = data.url.split(/\,/);
                                        $.each(urlArr, function () {
                                            var url = Fast.api.cdnurl(this, true);
                                            context.invoke('editor.insertImage', url);
                                        });
                                    }
                                });
                                return false;
                            }
                        });
                        return button.render();
                    };
                    var attachmentButton = function (context) {
                        var ui = $.summernote.ui;
                        var button = ui.button({
                            contents: '<i class="fa fa-file"/>',
                            tooltip: __('Choose'),
                            click: function () {
                                parent.Fast.api.open(selectUrl + "?element_id=&multiple=true&mimetype=*", __('Choose'), {
                                    callback: function (data) {
                                        var urlArr = data.url.split(/\,/);
                                        $.each(urlArr, function () {
                                            var url = Fast.api.cdnurl(this, true);
                                            var node = $("<a href='" + url + "'>" + url + "</a>");
                                            context.invoke('insertNode', node[0]);
                                        });
                                    }
                                });
                                return false;
                            }
                        });
                        return button.render();
                    };
                    if (Config.summernote.isdompurify) {
                        // 添加 hook 过滤 iframe 来源
                        DOMPurify.addHook('uponSanitizeElement', function (node, data, config) {
                            if (data.tagName === 'iframe') {
                                var allowedIframePrefixes = Config.nkeditor.allowiframeprefixs || [];
                                var src = node.getAttribute('src');

                                // 判断是否匹配允许的前缀
                                var isAllowed = false;
                                for (var i = 0; i < allowedIframePrefixes.length; i++) {
                                    if (src && src.indexOf(allowedIframePrefixes[i]) === 0) {
                                        isAllowed = true;
                                        break;
                                    }
                                }

                                if (!isAllowed) {
                                    // 不符合要求则移除该节点
                                    return node.parentNode.removeChild(node);
                                }

                                // 添加安全属性
                                node.setAttribute('allowfullscreen', '');
                                node.setAttribute('allow', 'fullscreen');
                            }
                        });

                        var purifyOptions = {
                            ADD_TAGS: ['iframe'],
                            FORCE_REJECT_IFRAME: false
                        };
                        $.extend($.summernote.plugins, {
                            'dompurify': function (context) {
                                // 重写代码过滤方法
                                const originalPurify = context.options.modules.codeview.prototype.purify;
                                context.options.modules.codeview.prototype.purify = function (html) {
                                    html = DOMPurify.sanitize(html, purifyOptions);
                                    return originalPurify.call(this, html);
                                };
                            }
                        });
                    }

                    $(Config.summernote.classname || '.editor', form).each(function () {
                        $(this).summernote($.extend(true, {}, {
                            height: isNaN(Config.summernote.height) ? null : parseInt(Config.summernote.height),
                            minHeight: parseInt(Config.summernote.minHeight || 250),
                            toolbar: Config.summernote.toolbar,
                            followingToolbar: parseInt(Config.summernote.followingToolbar),
                            placeholder: Config.summernote.placeholder || '',
                            airMode: parseInt(Config.summernote.airMode) || false,
                            lang: 'zh-CN',
                            fontNames: Config.summernote.fontNames || [],
                            fontNamesIgnoreCheck: ["Open Sans", "Microsoft YaHei", '微软雅黑', '宋体', '黑体', '仿宋', '楷体', '幼圆'],
                            buttons: {
                                image: imageButton,
                                attachment: attachmentButton,
                            },
                            plugins: {
                                'dompurify': true
                            },
                            dialogsInBody: true,
                            callbacks: {
                                onChange: function (contents) {
                                    if (Config.summernote.isdompurify) {
                                        contents = DOMPurify.sanitize(contents, purifyOptions);
                                    }
                                    $(this).val(contents);
                                    $(this).trigger('change');
                                },
                                onInit: function () {
                                },
                                onImageUpload: function (files) {
                                    var that = this;
                                    //依次上传图片
                                    for (var i = 0; i < files.length; i++) {
                                        Upload.api.send(files[i], function (data) {
                                            var url = Fast.api.cdnurl(data.url, true);
                                            $(that).summernote("insertImage", url, 'filename');
                                        });
                                    }
                                },
                                onPaste: function (e) {
                                    if (Config.summernote.pasteAsPlainText || false) {
                                        var bufferText = ((e.originalEvent || e).clipboardData || window.clipboardData).getData('Text');
                                        e.preventDefault();
                                        setTimeout(function () {
                                            document.execCommand('insertText', false, bufferText);
                                        }, 10);
                                    }
                                }
                            }
                        }, $(this).data("summernote-options") || {}));
                    });
                });
            }
        } catch (e) {

        }

    };
});

// 手机端左右滑动切换菜单栏
if ('ontouchstart' in document.documentElement) {
    var startX, startY, moveEndX, moveEndY, relativeX, relativeY, element;
    element = $('body', top.document);
    $("body").on("touchstart", function (e) {
        startX = e.originalEvent.changedTouches[0].pageX;
        startY = e.originalEvent.changedTouches[0].pageY;
    });
    $("body").on("touchend", function (e) {
        moveEndX = e.originalEvent.changedTouches[0].pageX;
        moveEndY = e.originalEvent.changedTouches[0].pageY;
        relativeX = moveEndX - startX;
        relativeY = moveEndY - startY;

        // 判断标准
        //右滑
        if (relativeX > 45) {
            if ((Math.abs(relativeX) - Math.abs(relativeY)) > 50) {
                element.addClass("sidebar-open");
            }
        }
        //左滑
        else if (relativeX < -45) {
            if ((Math.abs(relativeX) - Math.abs(relativeY)) > 50) {
                element.removeClass("sidebar-open");
            }
        }
    });
}
});