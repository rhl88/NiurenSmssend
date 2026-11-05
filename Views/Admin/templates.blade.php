<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>短信模板 - 短信发送</title>
    <link rel="stylesheet" href="{{ asset('CmsProUi/component/pear/css/pear.css') }}">
    <link rel="stylesheet" href="{{ asset('CmsProUi/font-awesome/4.7.0/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/variables.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/reset.css') }}">
    <style>
        .layui-form-label { width: 60px; }
        .var-badge { display: inline-block; font-size: 12px; padding: 0 6px; border-radius: 3px; background: #eef5ff; color: #1e6fff; margin: 0 4px 2px 0; }
        .tpl-toolbar { display: flex; justify-content: space-between; align-items: center; }
        /* 框架 reset.css 将 .layui-layer-content 设为 overflow:hidden，弹窗内容超出不会滚动，
           故在弹窗内部自建滚动容器，保证长表单可滚动查看 */
        #editModal { height: 100%; }
        .modal-form { height: 100%; box-sizing: border-box; overflow-y: auto; }
        .modal-form .layui-form-item { margin-bottom: 12px; }
        .modal-form .layui-form-label { width: 90px; }
        .modal-form .layui-input-block { margin-left: 120px; }
        .field-group { border: 1px solid #e8e8e8; border-radius: 4px; margin-bottom: 10px; background: #fafbfc; }
        .field-group-head { padding: 8px 12px; border-bottom: 1px solid #eef0f2; display: flex; align-items: center; }
        .field-group-head .var-tag { font-weight: 600; color: #1e6fff; margin-right: 12px; }
        .field-group-head a { font-size: 12px; margin-right: 12px; }
        .field-row { padding: 10px 12px; border-bottom: 1px dashed #eef0f2; }
        .field-row:last-child { border-bottom: none; }
        .field-row .fr-line { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
        .field-row .fr-line .layui-input { flex: 1; }
        .field-row .fr-line select.layui-input { flex: 0 0 130px; }
        .field-row .fr-req { white-space: nowrap; font-size: 13px; color: #666; }
        .field-row .fr-remove { color: #f56c6c; white-space: nowrap; font-size: 13px; }
        .field-row .fr-opts-wrap { display: flex; align-items: flex-start; gap: 8px; }
        .field-row .fr-opts-wrap .opts-label { font-size: 12px; color: #999; white-space: nowrap; padding-top: 8px; }
        .empty-tip { color: #999; font-size: 12px; padding: 8px 0; }
        /* 模板名称 hover 显示复制按钮 */
        .tpl-name-cell { position: relative; display: inline-block; max-width: 100%; }
        .tpl-name-cell .tpl-copy-btn { display: none; margin-left: 6px; cursor: pointer; color: #1e6fff; font-size: 13px; white-space: nowrap; }
        .tpl-name-cell:hover .tpl-copy-btn { display: inline-block; }
    </style>
    @include('admin.partials.permission-script')
</head>
<body>
<div class="pear-container">
    {{-- 搜索卡 --}}
    <div class="layui-card">
        <div class="layui-card-body">
            <form class="layui-form" lay-filter="searchForm">
                <div class="layui-form-item" style="margin-bottom:0;">
                    <div class="tpl-toolbar">
                        <div>
                            <div class="layui-inline">
                                <label class="layui-form-label">关键字</label>
                                <div class="layui-input-inline">
                                    <input type="text" name="keyword" placeholder="模板名称 / 模板 CODE / 模板标识" class="layui-input" autocomplete="off">
                                </div>
                            </div>
                            <div class="layui-inline">
                                <label class="layui-form-label">服务商</label>
                                <div class="layui-input-inline" style="width:130px;">
                                    <select name="provider">
                                        <option value="">全部</option>
                                        @foreach($providers as $code => $name)
                                        <option value="{{ $code }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="layui-inline">
                                <button class="layui-btn layui-btn-normal" lay-submit lay-filter="doSearch"><i class="layui-icon layui-icon-search"></i> 搜索</button>
                            </div>
                        </div>
                        <div>
                            <button type="button" class="layui-btn layui-btn-sm" id="btnCreate" data-permission="niuren.smssend.template"><i class="fa fa-plus"></i> 新建模板</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- 表格卡 --}}
    <div class="layui-card">
        <div class="layui-card-body">
            <table id="templatesTable" lay-filter="templatesTable"></table>
        </div>
    </div>

    @verbatim
    <script type="text/html" id="providerTpl">
        <span class="layui-badge layui-bg-blue">{{ d.provider_text }}</span>
    </script>
    <script type="text/html" id="varsTpl">
        {{# if(d.variables && d.variables.length){ }}
        {{# layui.each(d.variables, function(i, v){ }}
        <span class="var-badge">${ {{ v }} }</span>
        {{# }); }}
        {{# } else { }}
        <span style="color:#ccc;">无变量</span>
        {{# } }}
    </script>
    <script type="text/html" id="statusTpl">
        {{# if(d.status === 1){ }}
        <span class="layui-badge layui-bg-green">启用</span>
        {{# } else { }}
        <span class="layui-badge layui-bg-gray">停用</span>
        {{# } }}
    </script>
    <script type="text/html" id="rowBar">
        <a class="layui-btn layui-btn-xs" lay-event="edit" data-permission="niuren.smssend.template"><i class="fa fa-pencil"></i> 编辑</a>
        <a class="layui-btn layui-btn-xs layui-btn-danger" lay-event="delete" data-permission="niuren.smssend.template"><i class="fa fa-trash"></i> 删除</a>
    </script>
    @endverbatim
</div>

{{-- 新建 / 编辑弹窗（layer type:1 内容） --}}
<div id="editModal" style="display:none;">
    <div class="modal-form" style="padding:15px 20px 0;">
        <div class="layui-form-item">
            <label class="layui-form-label">模板名称</label>
            <div class="layui-input-block">
                <input type="text" id="tplName" class="layui-input" placeholder="如：体检通知短信" autocomplete="off">
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">模板 CODE</label>
            <div class="layui-input-block">
                <input type="text" id="tplCode" class="layui-input" placeholder="服务商控制台的模板 CODE，如：SMS_512651836" autocomplete="off">
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">模板标识</label>
            <div class="layui-input-block">
                <input type="text" id="tplAlias" class="layui-input" placeholder="选填，如 user.register.verify；供其他应用按标识调用，全局唯一" autocomplete="off">
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">服务商</label>
            <div class="layui-input-block">
                <select id="tplProvider" class="layui-input">
                    @foreach($providers as $code => $name)
                    <option value="{{ $code }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">短信签名</label>
            <div class="layui-input-block">
                <input type="text" id="tplSignName" class="layui-input" placeholder="选填；留空则使用设置中该服务商的默认签名" autocomplete="off">
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">模板内容</label>
            <div class="layui-input-block">
                <textarea id="tplContent" class="layui-textarea" style="min-height:90px;" placeholder="如：尊敬的${name}，您好！您本次体检${type}，结果${info}。"></textarea>
                <div class="helper-tip">使用 ${变量名} 作为占位符（与阿里云一致）。填写后点击「解析变量」生成字段搭配。</div>
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">状态</label>
            <div class="layui-input-block">
                <select id="tplStatus" class="layui-input" style="width:130px;">
                    <option value="1">启用</option>
                    <option value="0">停用</option>
                </select>
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">备注</label>
            <div class="layui-input-block">
                <input type="text" id="tplRemark" class="layui-input" placeholder="选填，便于识别模板用途" autocomplete="off">
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">变量字段</label>
            <div class="layui-input-block">
                <div style="margin-bottom:8px;">
                    <button type="button" class="layui-btn layui-btn-sm layui-btn-normal" id="btnParseVars"><i class="fa fa-magic"></i> 解析变量</button>
                    <button type="button" class="layui-btn layui-btn-sm layui-btn-primary" id="btnAddVar"><i class="fa fa-plus"></i> 手动添加变量</button>
                    <span class="helper-tip" style="display:block;">每个变量可搭配一个或多个控件；同一变量搭配多个控件时，发送时按顺序拼接为一个值。</span>
                </div>
                <div id="fieldGroups"></div>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('CmsProUi/component/layui/layui.js') }}"></script>
<script src="{{ asset('CmsProUi/component/pear/pear.js') }}"></script>
<script>
layui.use(['jquery', 'table', 'form', 'layer'], function () {
    var $ = layui.jquery,
        table = layui.table,
        form = layui.form,
        layer = layui.layer;

    $.ajaxSetup({
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        statusCode: {
            401: function () {
                layer.msg('登录已过期，请重新登录', { icon: 2, time: 1500 }, function () {
                    top.location.href = '/admin/login';
                });
            }
        }
    });

    var API = '/admin/niuren/smssend/api';
    var FIELD_TYPES = @json($fieldTypes);
    var OPTION_TYPES = ['radio', 'select', 'checkbox'];

    function hasPerm(code) { return !window.hasPermission || window.hasPermission(code); }
    function hidePermButtons() {
        $('button[data-permission], a[data-permission]').each(function () {
            if (!hasPerm($(this).data('permission'))) { $(this).hide(); }
        });
    }
    hidePermButtons();

    function escapeHtml(str) {
        return String(str == null ? '' : str).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // ============ 表格 ============
    var cols = [[
        { title: ' ', type: 'numbers', width: 42 },
        { field: 'name', title: '模板名称', minWidth: 160, templet: function (d) { return '<span class="tpl-name-cell" title="' + escapeHtml(d.content) + '">' + escapeHtml(d.name) + '<a class="tpl-copy-btn" data-id="' + d.id + '"><i class="fa fa-copy"></i> 复制</a></span>'; } },
        { field: 'code', title: '模板 CODE', width: 170 },
        { field: 'alias', title: '模板标识', width: 160, templet: function (d) { return d.alias ? escapeHtml(d.alias) : '<span style="color:#ccc;">-</span>'; } },
        { field: 'provider_text', title: '服务商', width: 100, align: 'center', templet: '#providerTpl' },
        { field: 'variables', title: '模板变量', minWidth: 200, templet: '#varsTpl' },
        { field: 'status', title: '状态', width: 80, align: 'center', templet: '#statusTpl' },
        { field: 'update_time', title: '更新时间', width: 170 },
        { title: '操作', width: 170, toolbar: '#rowBar', fixed: 'right' }
    ]];

    table.render({
        elem: '#templatesTable',
        id: 'templatesTable',
        url: API + '/templates',
        method: 'get',
        page: { layout: ['count', 'prev', 'page', 'next', 'limit'], groups: 5, limit: 15, limits: [15, 30, 50, 100] },
        skin: false,
        cols: cols,
        parseData: function (res) {
            return { code: res.code === 0 ? 0 : 1, msg: res.msg || '', count: res.count || 0, data: res.data || [] };
        },
        done: function () { hidePermButtons(); },
        error: function () { layer.msg('列表加载失败', { icon: 2 }); }
    });

    form.on('submit(doSearch)', function (data) {
        table.reload('templatesTable', {
            where: { keyword: data.field.keyword, provider: data.field.provider },
            page: { curr: 1 }
        });
        return false;
    });

    // ============ 变量字段编辑器 ============
    var varList = [];     // 变量名顺序
    var fieldRows = [];   // 控件模型 {var_name,label,field_type,is_required,default_value,placeholder,options}

    function optionsToText(options) {
        if (!options || !options.length) return '';
        return options.map(function (o) {
            return (o.label && o.value && o.label !== o.value) ? (o.label + '=' + o.value) : (o.value || o.label || '');
        }).join('\n');
    }

    function parseOptions(text) {
        var result = [];
        String(text || '').split('\n').forEach(function (line) {
            line = $.trim(line);
            if (line === '') return;
            var idx = line.indexOf('=');
            if (idx > -1) {
                var label = $.trim(line.substring(0, idx));
                var value = $.trim(line.substring(idx + 1));
                if (label !== '' || value !== '') {
                    result.push({ label: label || value, value: value || label });
                }
            } else {
                result.push({ label: line, value: line });
            }
        });
        return result;
    }

    function typeOptionsHtml(selected) {
        var html = '';
        $.each(FIELD_TYPES, function (code, name) {
            html += '<option value="' + code + '"' + (code === selected ? ' selected' : '') + '>' + name + '</option>';
        });
        return html;
    }

    function createFieldRow(row) {
        var $row = $('<div class="field-row"></div>');
        $row.data('model', row);
        var isOpt = OPTION_TYPES.indexOf(row.field_type) > -1;
        $row.html(
            '<div class="fr-line">' +
                '<input type="text" class="layui-input fr-label" placeholder="字段名称（如：姓名）" value="' + escapeHtml(row.label) + '">' +
                '<select class="layui-input fr-type">' + typeOptionsHtml(row.field_type) + '</select>' +
                '<label class="fr-req"><input type="checkbox" class="fr-req-chk"' + (row.is_required == 1 ? ' checked' : '') + '> 必填</label>' +
                '<a href="javascript:;" class="fr-remove"><i class="fa fa-trash"></i> 删除</a>' +
            '</div>' +
            '<div class="fr-line">' +
                '<input type="text" class="layui-input fr-default" placeholder="默认值（可选）" value="' + escapeHtml(row.default_value) + '">' +
                '<input type="text" class="layui-input fr-placeholder" placeholder="输入提示（可选）" value="' + escapeHtml(row.placeholder) + '">' +
            '</div>' +
            '<div class="fr-line fr-opts-wrap"' + (isOpt ? '' : ' style="display:none;"') + '>' +
                '<span class="opts-label">选项</span>' +
                '<textarea class="layui-textarea fr-options-text" style="min-height:56px;" placeholder="每行一个选项；支持 标签=值 格式，如：男=male">' + escapeHtml(optionsToText(row.options)) + '</textarea>' +
            '</div>'
        );
        return $row;
    }

    function renderGroups() {
        var $wrap = $('#fieldGroups');
        $wrap.empty();

        if (!varList.length) {
            $wrap.append('<div class="empty-tip">暂无变量：请在上方「模板内容」中使用 ${变量名} 占位符，然后点击「解析变量」，或点击「手动添加变量」。</div>');
            return;
        }

        varList.forEach(function (varName) {
            var $group = $('<div class="field-group"></div>');
            $group.data('var', varName);
            $group.append(
                '<div class="field-group-head">' +
                    '<span class="var-tag">${' + escapeHtml(varName) + '}</span>' +
                    '<a href="javascript:;" class="link-add-ctrl" data-var="' + escapeHtml(varName) + '"><i class="fa fa-plus"></i> 添加控件</a>' +
                    '<a href="javascript:;" class="link-remove-var" data-var="' + escapeHtml(varName) + '" style="color:#f56c6c;"><i class="fa fa-times"></i> 移除变量</a>' +
                '</div>'
            );
            var rows = fieldRows.filter(function (r) { return r.var_name === varName; });
            if (!rows.length) {
                $group.append('<div class="empty-tip" style="padding:8px 12px;">该变量暂无控件，发送时其值为空字符串。</div>');
            } else {
                rows.forEach(function (r) { $group.append(createFieldRow(r)); });
            }
            $wrap.append($group);
        });
    }

    function collectDomToModel() {
        $('#fieldGroups .field-row').each(function () {
            var m = $(this).data('model');
            if (!m) return;
            m.label = $.trim($(this).find('.fr-label').val());
            m.field_type = $(this).find('.fr-type').val();
            m.is_required = $(this).find('.fr-req-chk').is(':checked') ? 1 : 0;
            m.default_value = $.trim($(this).find('.fr-default').val());
            m.placeholder = $.trim($(this).find('.fr-placeholder').val());
            m.options = parseOptions($(this).find('.fr-options-text').val());
        });
    }

    // 解析模板内容中的变量（与后端 extractVariables 规则一致）
    function extractVars(content) {
        var vars = [], re = /\$\{([A-Za-z_][A-Za-z0-9_]*)\}/g, m;
        while ((m = re.exec(content)) !== null) {
            if (vars.indexOf(m[1]) === -1) { vars.push(m[1]); }
        }
        return vars;
    }

    $('#btnParseVars').on('click', function () {
        collectDomToModel();
        var vars = extractVars($('#tplContent').val());
        if (!vars.length) {
            layer.msg('模板内容中未解析到 ${变量} 占位符', { icon: 0 });
            return;
        }
        // 保留已有变量顺序，追加新变量
        vars.forEach(function (v) { if (varList.indexOf(v) === -1) { varList.push(v); } });
        // 移除已不存在的变量及其控件
        varList = varList.filter(function (v) {
            if (vars.indexOf(v) === -1) {
                fieldRows = fieldRows.filter(function (r) { return r.var_name !== v; });
                return false;
            }
            return true;
        });
        // 变量顺序对齐内容出现顺序
        varList.sort(function (a, b) { return vars.indexOf(a) - vars.indexOf(b); });
        renderGroups();
        layer.msg('已解析到 ' + vars.length + ' 个变量', { icon: 1, time: 1000 });
    });

    $('#btnAddVar').on('click', function () {
        layer.prompt({ title: '输入变量名（字母/数字/下划线，首字符非数字）', formType: 0 }, function (value, index) {
            value = $.trim(value);
            if (!/^[A-Za-z_][A-Za-z0-9_]*$/.test(value)) {
                layer.msg('变量名格式不正确', { icon: 2 });
                return;
            }
            if (varList.indexOf(value) > -1) {
                layer.msg('该变量已存在', { icon: 2 });
                return;
            }
            collectDomToModel();
            varList.push(value);
            layer.close(index);
            renderGroups();
        });
    });

    // 事件委托：添加控件 / 移除变量 / 删除控件 / 类型切换
    $('#fieldGroups').on('click', '.link-add-ctrl', function () {
        collectDomToModel();
        var varName = $(this).data('var');
        fieldRows.push({ var_name: varName, label: '', field_type: 'text', is_required: 0, default_value: '', placeholder: '', options: [] });
        renderGroups();
    });

    $('#fieldGroups').on('click', '.link-remove-var', function () {
        var varName = $(this).data('var');
        layer.confirm('移除变量 ${' + varName + '} 及其全部控件？', { icon: 3, title: '确认' }, function (idx) {
            collectDomToModel();
            varList = varList.filter(function (v) { return v !== varName; });
            fieldRows = fieldRows.filter(function (r) { return r.var_name !== varName; });
            layer.close(idx);
            renderGroups();
        });
    });

    $('#fieldGroups').on('click', '.fr-remove', function () {
        var m = $(this).closest('.field-row').data('model');
        collectDomToModel();
        fieldRows = fieldRows.filter(function (r) { return r !== m; });
        renderGroups();
    });

    $('#fieldGroups').on('change', '.fr-type', function () {
        var isOpt = OPTION_TYPES.indexOf($(this).val()) > -1;
        $(this).closest('.field-row').find('.fr-opts-wrap').toggle(isOpt);
    });

    // ============ 弹窗 ============
    var editingId = 0;

    function openEdit(id) {
        editingId = id || 0;
        $('#tplName').val('');
        $('#tplCode').val('');
        $('#tplAlias').val('');
        $('#tplProvider').val('{{ $defaultProvider }}');
        $('#tplSignName').val('');
        $('#tplContent').val('');
        $('#tplStatus').val('1');
        $('#tplRemark').val('');
        varList = [];
        fieldRows = [];
        renderGroups();

        var doOpen = function () {
            layer.open({
                type: 1,
                title: editingId ? '编辑短信模板' : '新建短信模板',
                area: ['900px', '88%'],
                maxmin: true,
                content: $('#editModal'),
                btn: ['保存', '取消'],
                yes: function (index) { submit(index); },
                end: function () { $('#editModal').hide(); }
            });
        };

        if (editingId) {
            $.get(API + '/templates/' + editingId + '/form', function (res) {
                if (res.code !== 0) { layer.msg(res.message || '模板读取失败', { icon: 2 }); return; }
                var d = res.data || {};
                $('#tplName').val(d.name || '');
                $('#tplCode').val(d.code || '');
                $('#tplAlias').val(d.alias || '');
                $('#tplProvider').val(d.provider || '{{ $defaultProvider }}');
                $('#tplSignName').val(d.sign_name || '');
                $('#tplContent').val(d.content || '');
                $('#tplStatus').val(String(d.status));
                $('#tplRemark').val(d.remark || '');
                varList = (d.variables || []).slice();
                fieldRows = (d.fields || []).map(function (f) {
                    return {
                        var_name: f.var_name, label: f.label, field_type: f.field_type,
                        is_required: f.is_required, default_value: f.default_value,
                        placeholder: f.placeholder, options: f.options || []
                    };
                });
                // 补齐字段中引用但未被内容解析出的变量
                fieldRows.forEach(function (r) { if (varList.indexOf(r.var_name) === -1) { varList.push(r.var_name); } });
                renderGroups();
                doOpen();
            }).fail(function () { layer.msg('模板读取失败', { icon: 2 }); });
        } else {
            doOpen();
        }
    }

    function submit(index) {
        collectDomToModel();
        var name = $.trim($('#tplName').val());
        var code = $.trim($('#tplCode').val());
        var alias = $.trim($('#tplAlias').val());
        var content = $('#tplContent').val();
        if (!name) { layer.msg('请填写模板名称', { icon: 2 }); return; }
        if (!code) { layer.msg('请填写模板 CODE', { icon: 2 }); return; }
        if (!$.trim(content)) { layer.msg('请填写模板内容', { icon: 2 }); return; }

        // 校验每个控件必须有字段名称
        var blankRow = fieldRows.filter(function (r) { return r.var_name && !r.label; })[0];
        if (blankRow) { layer.msg('变量 ${' + blankRow.var_name + '} 存在未填写名称的控件', { icon: 2 }); return; }

        var payload = {
            name: name,
            code: code,
            alias: alias,
            provider: $('#tplProvider').val(),
            sign_name: $.trim($('#tplSignName').val()),
            content: content,
            remark: $.trim($('#tplRemark').val()),
            status: parseInt($('#tplStatus').val(), 10),
            fields: fieldRows.map(function (r) {
                return {
                    var_name: r.var_name,
                    label: $.trim(r.label),
                    field_type: r.field_type,
                    is_required: r.is_required ? 1 : 0,
                    default_value: $.trim(r.default_value),
                    placeholder: $.trim(r.placeholder),
                    options: OPTION_TYPES.indexOf(r.field_type) > -1 ? r.options : []
                };
            })
        };

        var idx = layer.load(1, { shade: 0.3 });
        $.ajax({
            url: editingId ? (API + '/templates/' + editingId) : (API + '/templates'),
            type: editingId ? 'PUT' : 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify(payload),
            success: function (res) {
                layer.close(idx);
                if (res.code === 0) {
                    layer.close(index);
                    layer.msg(res.message || '保存成功', { icon: 1, time: 1200 });
                    table.reload('templatesTable');
                } else {
                    layer.msg(res.message || '保存失败', { icon: 2, time: 2000 });
                }
            },
            error: function () { layer.close(idx); layer.msg('保存失败', { icon: 2 }); }
        });
    }

    $('#btnCreate').on('click', function () {
        if (!hasPerm('niuren.smssend.template')) { layer.msg('无操作权限', { icon: 2 }); return; }
        openEdit(0);
    });

    // 复制模板：读取原模板数据，以新建模式打开弹窗，名称加"（副本）"后缀，CODE 清空
    $(document).on('click', '.tpl-copy-btn', function (e) {
        e.stopPropagation();
        if (!hasPerm('niuren.smssend.template')) { layer.msg('无操作权限', { icon: 2 }); return; }
        var id = parseInt($(this).data('id'), 10);
        if (!id) return;
        var idx = layer.load(1, { shade: 0.3 });
        $.get(API + '/templates/' + id + '/form', function (res) {
            layer.close(idx);
            if (res.code !== 0) { layer.msg(res.message || '模板读取失败', { icon: 2 }); return; }
            var d = res.data || {};
            // 以新建模式填充表单
            editingId = 0;
            $('#tplName').val((d.name || '') + '（副本）');
            $('#tplCode').val('');
            $('#tplAlias').val('');
            $('#tplProvider').val(d.provider || '{{ $defaultProvider }}');
            $('#tplSignName').val(d.sign_name || '');
            $('#tplContent').val(d.content || '');
            $('#tplStatus').val('1');
            $('#tplRemark').val(d.remark || '');
            varList = (d.variables || []).slice();
            fieldRows = (d.fields || []).map(function (f) {
                return {
                    var_name: f.var_name, label: f.label, field_type: f.field_type,
                    is_required: f.is_required, default_value: f.default_value,
                    placeholder: f.placeholder, options: f.options || []
                };
            });
            fieldRows.forEach(function (r) { if (varList.indexOf(r.var_name) === -1) { varList.push(r.var_name); } });
            renderGroups();
            layer.open({
                type: 1,
                title: '复制模板（新建）',
                area: ['900px', '88%'],
                maxmin: true,
                content: $('#editModal'),
                btn: ['保存', '取消'],
                yes: function (index) { submit(index); },
                end: function () { $('#editModal').hide(); }
            });
        }).fail(function () { layer.close(idx); layer.msg('模板读取失败', { icon: 2 }); });
    });

    table.on('tool(templatesTable)', function (obj) {
        var data = obj.data, layEvent = obj.event;
        if (!hasPerm('niuren.smssend.template')) { layer.msg('无操作权限', { icon: 2 }); return; }

        if (layEvent === 'edit') {
            openEdit(data.id);
        } else if (layEvent === 'delete') {
            layer.confirm('确定删除模板「' + escapeHtml(data.name) + '」？其表单字段将一并删除。', { icon: 3, title: '删除确认' }, function (idx) {
                layer.close(idx);
                $.ajax({
                    url: API + '/templates/' + data.id,
                    type: 'DELETE',
                    success: function (res) {
                        if (res.code === 0) {
                            layer.msg('删除成功', { icon: 1, time: 800 }, function () { table.reload('templatesTable'); });
                        } else {
                            layer.msg(res.message || '删除失败', { icon: 2 });
                        }
                    },
                    error: function () { layer.msg('删除失败', { icon: 2 }); }
                });
            });
        }
    });
});
</script>
</body>
</html>