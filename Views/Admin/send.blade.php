<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>发送短信 - 短信发送</title>
    <link rel="stylesheet" href="{{ asset('CmsProUi/component/pear/css/pear.css') }}">
    <link rel="stylesheet" href="{{ asset('CmsProUi/font-awesome/4.7.0/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/variables.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/reset.css') }}">
    <style>
        .layui-form-label { width: 110px; white-space: nowrap; }
        .layui-input-block { margin-left: 140px; }
        .helper-tip { font-size: 12px; color: #999; margin-top: 4px; }
        .template-meta { line-height: 26px; color: #333; }
        .template-meta .meta-row { margin-bottom: 2px; }
        .template-meta .meta-row .ml { color: #999; margin-right: 6px; }
        .template-meta .meta-content { background: #f7f9fb; border: 1px solid #e8eef5; border-radius: 4px; padding: 8px 10px; margin-top: 6px; white-space: pre-wrap; word-break: break-all; }
        .field-section-title { font-size: 14px; font-weight: 600; color: #333; padding: 4px 0 12px; border-bottom: 1px solid #eef0f2; margin-bottom: 14px; }
        .dyn-item .var-hint { color: #1e6fff; font-size: 12px; margin-right: 4px; }
        .req-mark { color: #f56c6c; margin-left: 2px; }
        .opt-group { padding-top: 6px; }
        .opt-item { margin-right: 18px; font-weight: 400; cursor: pointer; }
        .preview-box { background: #f7f9fb; border: 1px solid #e8eef5; border-radius: 4px; padding: 12px; line-height: 1.9; color: #333; white-space: pre-wrap; word-break: break-all; }
        .send-footer { text-align: right; margin-top: 12px; }
        .send-result .sr-row { line-height: 26px; }
        .layui-textarea{min-height: 50px;}
        .send-result .sr-row .ml { display: inline-block; width: 90px; color: #999; }
    </style>
    @include('admin.partials.permission-script')
</head>
<body>
<div class="pear-container">
    <div class="layui-card">
        <div class="layui-card-header">发送短信</div>
        <div class="layui-card-body">
            {{-- 第一步：选择短信模板 --}}
            <div id="stepSelect">
                <div class="layui-form-item">
                    <label class="layui-form-label">短信模板</label>
                    <div class="layui-input-block">
                        <select id="templateSelect" class="layui-input" style="max-width:420px;">
                            <option value="">请选择短信模板</option>
                        </select>
                        <div class="helper-tip">仅显示启用状态的模板；发送时按模板所属服务商调用对应接口。</div>
                    </div>
                </div>

                {{-- 模板信息：选择模板后即时加载展示 --}}
                <div class="layui-form-item" id="templateMetaWrap" style="display:none;">
                    <label class="layui-form-label">模板信息</label>
                    <div class="layui-input-block">
                        <div id="templateMeta" class="template-meta"></div>
                    </div>
                </div>

                <div class="send-footer">
                    <button type="button" class="layui-btn layui-btn-normal" id="btnNext">下一步</button>
                </div>
            </div>

            {{-- 第二步：填写并发送 --}}
            <div id="stepForm" style="display:none;">

                {{-- 表单第一项：接收手机号 --}}
                <div class="layui-form-item">
                    <label class="layui-form-label">接收手机号</label>
                    <div class="layui-input-block">
                        <input type="text" id="mobile" class="layui-input" placeholder="请输入接收短信的手机号，如 13800138000" autocomplete="off" style="max-width:420px;">
                        <div class="helper-tip">支持国际号码，可带 + 前缀；单次发送一个手机号。</div>
                    </div>
                </div>

                {{-- 模板变量动态表单 --}}
                <div id="dynamicForm"></div>

                {{-- 短信预览 --}}
                <div id="previewWrap" style="display:none;">
                    <div class="layui-form-item">
                        <label class="layui-form-label">短信预览</label>
                        <div class="layui-input-block">
                            <div id="previewBox" class="preview-box"></div>
                            <div class="helper-tip">预览为本地按变量顺序拼接的结果，实际以服务商模板渲染为准。</div>
                        </div>
                    </div>
                </div>

                <div class="send-footer">
                    <button type="button" class="layui-btn layui-btn-primary" id="btnPrev">上一步</button>
                    <button type="button" class="layui-btn layui-btn-normal" id="btnSend" data-permission="niuren.smssend.send">
                        <i class="fa fa-paper-plane"></i> 立即发送
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('CmsProUi/component/layui/layui.js') }}"></script>
<script src="{{ asset('CmsProUi/component/pear/pear.js') }}"></script>
<script>
layui.use(['jquery', 'layer'], function () {
    var $ = layui.jquery,
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
    var PROVIDER_NAMES = @json($providers);
    var currentTemplate = null; // 当前选中模板（含 content 与 fields）
    var loadedTemplateId = null; // 已加载模板数据的模板 ID（模板信息已展示）
    var renderedTemplateId = null; // 已渲染填写表单的模板 ID（避免返回上一步后重复渲染）

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

    // ============ 模板下拉 ============
    function loadTemplates() {
        $.get(API + '/templates/options', function (res) {
            if (res.code !== 0 || !res.data || !res.data.templates) {
                layer.msg(res.message || '模板加载失败', { icon: 2 });
                return;
            }
            var opts = '<option value="">请选择短信模板</option>';
            res.data.templates.forEach(function (t) {
                var providerName = PROVIDER_NAMES[t.provider] || t.provider;
                opts += '<option value="' + t.id + '">' + escapeHtml(t.name) + '（' + escapeHtml(t.code) + ' · ' + escapeHtml(providerName) + '）</option>';
            });
            $('#templateSelect').html(opts);
        }).fail(function () { layer.msg('模板加载失败', { icon: 2 }); });
    }

    // ============ 步骤切换 ============
    function showStep(step) {
        if (step === 2) { $('#stepSelect').hide(); $('#stepForm').show(); }
        else { $('#stepForm').hide(); $('#stepSelect').show(); }
    }

    // 选择模板后即时回到第一步并加载模板信息（此时不渲染填写表单）
    $('#templateSelect').on('change', function () {
        var id = parseInt($(this).val(), 10);
        resetAll();
        if (id) { loadTemplateData(id); }
    });

    // 点击“下一步”：进入第二步，显示填写发送内容
    $('#btnNext').on('click', function () {
        var id = parseInt($('#templateSelect').val(), 10);
        if (!id) { layer.msg('请选择短信模板', { icon: 2 }); return; }
        if (!currentTemplate || loadedTemplateId !== id) { loadTemplateData(id, true); return; }
        goStep2(id);
    });

    // 返回上一步（保留已填内容）
    $('#btnPrev').on('click', function () { showStep(1); });

    // 进入第二步：同一模板已渲染过则直接展示，避免重复渲染丢失已填内容
    function goStep2(id) {
        if (renderedTemplateId !== id) {
            renderDynamic(currentTemplate.fields || []);
            updatePreview();
            renderedTemplateId = id;
        }
        showStep(2);
    }

    function resetAll() {
        currentTemplate = null;
        loadedTemplateId = null;
        renderedTemplateId = null;
        $('#templateMetaWrap').hide();
        $('#previewWrap').hide();
        $('#dynamicForm').empty();
        $('#previewBox').empty();
        showStep(1);
    }

    // 加载模板数据并展示模板信息；enterStep2 为 true 时继续渲染填写表单并进入第二步
    function loadTemplateData(id, enterStep2) {
        var idx = layer.load(1, { shade: 0.3 });
        $.get(API + '/templates/' + id + '/form', function (res) {
            layer.close(idx);
            if (res.code !== 0) { layer.msg(res.message || '模板读取失败', { icon: 2 }); return; }
            currentTemplate = res.data || {};
            loadedTemplateId = id;
            renderMeta(currentTemplate);
            if (enterStep2) { goStep2(id); }
        }).fail(function () { layer.close(idx); layer.msg('模板读取失败', { icon: 2 }); });
    }

    function renderMeta(tpl) {
        var providerName = PROVIDER_NAMES[tpl.provider] || tpl.provider;
        var sign = tpl.sign_name ? escapeHtml(tpl.sign_name) : '（使用服务商默认签名）';
        var html = ''
            + '<div class="meta-row"><span class="ml">模板名称</span>' + escapeHtml(tpl.name) + '</div>'
            + '<div class="meta-row"><span class="ml">模板 CODE</span>' + escapeHtml(tpl.code) + '</div>'
            + '<div class="meta-row"><span class="ml">服务商</span><span class="layui-badge layui-bg-blue">' + escapeHtml(providerName) + '</span></div>'
            + '<div class="meta-row"><span class="ml">短信签名</span>' + sign + '</div>'
            + '<div class="meta-row"><span class="ml">模板内容</span></div>'
            + '<div class="meta-content">' + escapeHtml(tpl.content) + '</div>';
        $('#templateMeta').html(html);
        $('#templateMetaWrap').show();
    }

    // ============ 动态表单渲染 ============
    function renderDynamic(fields) {
        var $wrap = $('#dynamicForm');
        $wrap.empty();

        if (!fields.length) { return; }

        fields.forEach(function (f) { $wrap.append(renderField(f)); });
    }

    function renderField(f) {
        var required = f.is_required == 1 ? '<span class="req-mark">*</span>' : '';
        var ph = escapeHtml(f.placeholder || '');
        var def = escapeHtml(f.default_value || '');
        var html = '<div class="layui-form-item dyn-item" data-field-id="' + f.id + '" data-var="' + escapeHtml(f.var_name) + '" data-type="' + f.field_type + '">';
        html += '<label class="layui-form-label"><span class="var-hint">${' + escapeHtml(f.var_name) + '}</span>' + escapeHtml(f.label) + required + '</label>';
        html += '<div class="layui-input-block">';

        switch (f.field_type) {
            case 'textarea':
                html += '<textarea class="layui-textarea dyn-ctrl" rows="2" placeholder="' + ph + '">' + def + '</textarea>';
                break;
            case 'radio':
                html += '<div class="opt-group">';
                (f.options || []).forEach(function (o) {
                    html += '<label class="opt-item"><input type="radio" name="radio_' + f.id + '" class="dyn-ctrl dyn-radio" value="' + escapeHtml(o.value) + '"' + (o.value === f.default_value ? ' checked' : '') + '> ' + escapeHtml(o.label) + '</label>';
                });
                html += '</div>';
                break;
            case 'select':
                html += '<select class="layui-input dyn-ctrl dyn-select" style="max-width:320px;">';
                html += '<option value="">请选择</option>';
                (f.options || []).forEach(function (o) {
                    html += '<option value="' + escapeHtml(o.value) + '"' + (o.value === f.default_value ? ' selected' : '') + '>' + escapeHtml(o.label) + '</option>';
                });
                html += '</select>';
                break;
            case 'checkbox':
                html += '<div class="opt-group">';
                (f.options || []).forEach(function (o) {
                    html += '<label class="opt-item"><input type="checkbox" class="dyn-ctrl dyn-checkbox" value="' + escapeHtml(o.value) + '"> ' + escapeHtml(o.label) + '</label>';
                });
                html += '</div>';
                break;
            case 'number':
                html += '<input type="number" class="layui-input dyn-ctrl" placeholder="' + ph + '" value="' + def + '" style="max-width:320px;">';
                break;
            case 'date':
                html += '<input type="date" class="layui-input dyn-ctrl" value="' + def + '" style="max-width:220px;">';
                break;
            case 'mobile':
                html += '<input type="tel" class="layui-input dyn-ctrl" placeholder="' + (ph || '请输入手机号') + '" value="' + def + '" style="max-width:320px;">';
                break;
            default:
                html += '<input type="text" class="layui-input dyn-ctrl" placeholder="' + ph + '" value="' + def + '" style="max-width:420px;">';
        }

        html += '</div></div>';
        return html;
    }

    // 读取单个控件的字符串值（与后端 formatFieldValue 规则一致）
    function readFieldValue($item) {
        var type = $item.data('type');
        if (type === 'radio') {
            return $.trim($item.find('.dyn-radio:checked').val() || '');
        }
        if (type === 'checkbox') {
            var arr = [];
            $item.find('.dyn-checkbox:checked').each(function () { arr.push($(this).val()); });
            return arr.join('、');
        }
        return $.trim($item.find('.dyn-ctrl').val());
    }

    // 收集提交用的字段值（键为字段 ID；多选为数组）
    function collectFieldValues() {
        var values = {};
        $('#dynamicForm .dyn-item').each(function () {
            var $item = $(this);
            var id = $item.data('field-id');
            if ($item.data('type') === 'checkbox') {
                var arr = [];
                $item.find('.dyn-checkbox:checked').each(function () { arr.push($(this).val()); });
                values[id] = arr;
            } else {
                values[id] = readFieldValue($item);
            }
        });
        return values;
    }

    // 按变量顺序拼接预览值（多选内部用顿号，同一变量的多个控件首尾相接）
    function buildPreviewParams() {
        var order = [], collected = {};
        $('#dynamicForm .dyn-item').each(function () {
            var $item = $(this);
            var varName = $item.data('var');
            if (order.indexOf(varName) === -1) { order.push(varName); }
            if (!collected[varName]) { collected[varName] = []; }
            collected[varName].push(readFieldValue($item));
        });
        var params = {};
        order.forEach(function (v) { params[v] = collected[v].join(''); });
        return params;
    }

    function updatePreview() {
        if (!currentTemplate) { return; }
        var params = buildPreviewParams();
        var content = String(currentTemplate.content || '');
        Object.keys(params).forEach(function (name) {
            content = content.split('${' + name + '}').join(params[name]);
        });
        $('#previewBox').text(content);
        $('#previewWrap').show();
    }

    // 表单变化实时刷新预览
    $('#dynamicForm').on('input change', '.dyn-ctrl', updatePreview);

    // ============ 发送 ============
    $('#btnSend').on('click', function () {
        if (!hasPerm('niuren.smssend.send')) { layer.msg('无操作权限', { icon: 2, time: 1200 }); return; }

        var templateId = parseInt($('#templateSelect').val(), 10);
        if (!templateId) { layer.msg('请选择短信模板', { icon: 2 }); return; }

        var mobile = $.trim($('#mobile').val());
        if (!mobile) { layer.msg('请填写接收手机号', { icon: 2 }); return; }

        var payload = {
            template_id: templateId,
            mobile: mobile,
            field_values: collectFieldValues()
        };

        var idx = layer.load(1, { shade: 0.3 });
        $.ajax({
            url: API + '/send',
            type: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify(payload),
            success: function (res) {
                layer.close(idx);
                if (res.code === 0) {
                    showResult(res.data || {});
                    // 发送成功后重置填写内容（保留所选的手机号与模板，便于连续发送）
                    renderDynamic(currentTemplate.fields || []);
                    updatePreview();
                } else {
                    layer.msg(res.message || '发送失败', { icon: 2, time: 2500 });
                }
            },
            error: function () { layer.close(idx); layer.msg('请求失败，请重试', { icon: 2 }); }
        });
    });

    function showResult(d) {
        var html = '<div class="send-result" style=" padding: 13px; ">'
            + '<div class="sr-row"><span class="ml">接收手机号</span>' + escapeHtml(d.mobile) + '</div>'
            + '<div class="sr-row"><span class="ml">模板名称</span>' + escapeHtml(d.template_name) + '</div>'
            + '<div class="sr-row"><span class="ml">模板 CODE</span>' + escapeHtml(d.template_code) + '</div>'
            + '<div class="sr-row"><span class="ml">服务商</span>' + escapeHtml(d.provider_text || d.provider) + '</div>'
            + '<div class="sr-row"><span class="ml">发送状态</span><span class="layui-badge layui-bg-green">' + escapeHtml(d.send_status_text || '成功') + '</span></div>'
            + (d.vendor_msg_id ? '<div class="sr-row"><span class="ml">服务商回执</span>' + escapeHtml(d.vendor_msg_id) + '</div>' : '')
            + '<div class="sr-row"><span class="ml">短信内容</span></div>'
            + '<div class="preview-box" style="margin-top:6px;">' + escapeHtml(d.content || '') + '</div>'
            + '</div>';
        layer.open({
            type: 1,
            title: '发送成功',
            icon: 1,
            area: ['560px', 'auto'],
            content: html,
            btn: ['知道了'],
            shadeClose: true
        });
    }

    loadTemplates();
});
</script>
</body>
</html>
