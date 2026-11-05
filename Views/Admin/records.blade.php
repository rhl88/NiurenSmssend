<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>发送记录 - 短信发送</title>
    <link rel="stylesheet" href="{{ asset('CmsProUi/component/pear/css/pear.css') }}">
    <link rel="stylesheet" href="{{ asset('CmsProUi/font-awesome/4.7.0/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/variables.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/reset.css') }}">
    <style>
        .layui-form-label { width: 60px; }
        .layui-table .content-cell { color: #666; }
        .record-detail .rd-section { margin-bottom: 14px; }
        .record-detail .rd-title { font-size: 14px; font-weight: 600; color: #333; padding-bottom: 8px; border-bottom: 1px solid #eef0f2; margin-bottom: 10px; }
        .record-detail .rd-row { line-height: 28px; }
        .record-detail .rd-row .ml { display: inline-block; width: 100px; color: #999; }
        .record-detail .rd-box { background: #f7f9fb; border: 1px solid #e8eef5; border-radius: 4px; padding: 10px; white-space: pre-wrap; word-break: break-all; line-height: 1.8; color: #333; }
        .record-detail .rd-pre { background: #f7f9fb; border: 1px solid #e8eef5; border-radius: 4px; padding: 10px; white-space: pre-wrap; word-break: break-all; color: #666; font-size: 12px; line-height: 1.7; max-height: 240px; overflow: auto; }
        /* 框架 reset.css 将 .layui-layer-content 设为 overflow:hidden，弹窗内容超出不会滚动，
           故在弹窗内部自建滚动容器，保证长内容可滚动查看 */
        .record-detail { height: 100%; box-sizing: border-box; overflow-y: auto; }
    </style>
    @include('admin.partials.permission-script')
</head>
<body>
<div class="pear-container">
    {{-- 搜索卡 --}}
    <div class="layui-card">
        <div class="layui-card-body">
            <form class="layui-form" lay-filter="searchForm">
                <div class="layui-form-item">
                    <div class="layui-inline">
                        <label class="layui-form-label">服务商</label>
                        <div class="layui-input-inline" style="width:140px;">
                            <select name="provider">
                                <option value="">全部</option>
                                @foreach($providers as $code => $name)
                                <option value="{{ $code }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="layui-inline">
                        <label class="layui-form-label">状态</label>
                        <div class="layui-input-inline" style="width:130px;">
                            <select name="status">
                                <option value="">全部</option>
                                @foreach($statusMap as $code => $name)
                                <option value="{{ $code }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="layui-inline">
                        <label class="layui-form-label">关键字</label>
                        <div class="layui-input-inline">
                            <input type="text" name="keyword" placeholder="手机号 / 模板名称 / 模板 CODE" class="layui-input" autocomplete="off">
                        </div>
                    </div>
                    <div class="layui-inline">
                        <label class="layui-form-label">日期范围</label>
                        <div class="layui-input-inline">
                            <input type="text" name="date_range" id="dateRange" class="layui-input" placeholder="选择日期范围" autocomplete="off">
                            <input type="hidden" name="date_from" id="dateFrom">
                            <input type="hidden" name="date_to" id="dateTo">
                        </div>
                    </div>
                    <div class="layui-inline">
                        <button class="layui-btn layui-btn-normal" lay-submit lay-filter="doSearch"><i class="layui-icon layui-icon-search"></i> 搜索</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- 表格卡 --}}
    <div class="layui-card">
        <div class="layui-card-body">
            <table id="recordsTable" lay-filter="recordsTable"></table>
        </div>
    </div>

    @verbatim
    <script type="text/html" id="providerTpl">
        <span class="layui-badge layui-bg-blue">{{ d.provider_text }}</span>
    </script>
    <script type="text/html" id="statusTpl">
        {{# if(d.send_status === 'success'){ }}
        <span class="layui-badge layui-bg-green">发送成功</span>
        {{# } else if(d.send_status === 'failed'){ }}
        <span class="layui-badge layui-bg-red" title="{{ d.error_message || '' }}">发送失败</span>
        {{# } else { }}
        <span class="layui-badge layui-bg-orange">待发送</span>
        {{# } }}
    </script>
    <script type="text/html" id="contentTpl">
        <span class="content-cell" title="{{ d.content }}">{{ d.content }}</span>
    </script>
    <script type="text/html" id="rowBar">
        <a class="layui-btn layui-btn-xs" lay-event="detail" data-permission="niuren.smssend.record"><i class="fa fa-eye"></i> 详情</a>
    </script>
    @endverbatim
</div>

<script src="{{ asset('CmsProUi/component/layui/layui.js') }}"></script>
<script src="{{ asset('CmsProUi/component/pear/pear.js') }}"></script>
<script>
layui.use(['jquery', 'table', 'form', 'layer', 'laydate'], function () {
    var $ = layui.jquery,
        table = layui.table,
        form = layui.form,
        layer = layui.layer,
        laydate = layui.laydate;

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
        { field: 'mobile', title: '手机号', width: 140 },
        { field: 'template_name', title: '模板名称', minWidth: 150 },
        { field: 'template_code', title: '模板 CODE', width: 160 },
        { field: 'provider_text', title: '服务商', width: 100, align: 'center', templet: '#providerTpl' },
        { field: 'content', title: '短信内容', minWidth: 240, templet: '#contentTpl' },
        { field: 'send_status', title: '发送状态', width: 100, align: 'center', templet: '#statusTpl' },
        { field: 'vendor_msg_id', title: '服务商回执', width: 190, templet: function (d) { return d.vendor_msg_id || '<span style="color:#ccc;">-</span>'; } },
        { field: 'create_time', title: '发送时间', width: 170 },
        { title: '操作', width: 100, toolbar: '#rowBar', fixed: 'right' }
    ]];

    table.render({
        elem: '#recordsTable',
        id: 'recordsTable',
        url: API + '/records',
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
        table.reload('recordsTable', {
            where: {
                provider: data.field.provider,
                status: data.field.status,
                keyword: data.field.keyword,
                date_from: $('#dateFrom').val(),
                date_to: $('#dateTo').val()
            },
            page: { curr: 1 }
        });
        return false;
    });

    laydate.render({
        elem: '#dateRange',
        range: true,
        done: function (value) {
            if (value) {
                var parts = value.split(' - ');
                $('#dateFrom').val(parts[0]);
                $('#dateTo').val(parts[1]);
            } else {
                $('#dateFrom').val('');
                $('#dateTo').val('');
            }
        }
    });

    // ============ 详情 ============
    table.on('tool(recordsTable)', function (obj) {
        if (obj.event !== 'detail') { return; }
        if (!hasPerm('niuren.smssend.record')) { layer.msg('无操作权限', { icon: 2, time: 1000 }); return; }
        openDetail(obj.data.id);
    });

    function openDetail(id) {
        var idx = layer.load(1, { shade: 0.3 });
        $.get(API + '/records/' + id, function (res) {
            layer.close(idx);
            if (res.code !== 0) { layer.msg(res.message || '记录读取失败', { icon: 2 }); return; }
            layer.open({
                type: 1,
                title: '发送记录详情',
                area: ['720px', '85%'],
                maxmin: true,
                content: renderDetail(res.data || {}),
                shadeClose: true
            });
        }).fail(function () { layer.close(idx); layer.msg('记录读取失败', { icon: 2 }); });
    }

    function renderDetail(d) {
        var statusHtml = d.send_status === 'success'
            ? '<span class="layui-badge layui-bg-green">发送成功</span>'
            : (d.send_status === 'failed'
                ? '<span class="layui-badge layui-bg-red">发送失败</span>'
                : '<span class="layui-badge layui-bg-orange">待发送</span>');

        var html = '<div class="record-detail" style="padding:16px 20px;">';

        // 基本信息
        html += '<div class="rd-section"><div class="rd-title">基本信息</div>';
        html += row('接收手机号', escapeHtml(d.mobile));
        html += row('模板名称', escapeHtml(d.template_name));
        html += row('模板 CODE', escapeHtml(d.template_code));
        html += row('服务商', escapeHtml(d.provider_text || d.provider));
        html += row('短信签名', d.sign_name ? escapeHtml(d.sign_name) : '<span style="color:#ccc;">（默认签名）</span>');
        html += row('发送状态', statusHtml);
        html += row('服务商回执', d.vendor_msg_id ? escapeHtml(d.vendor_msg_id) : '<span style="color:#ccc;">-</span>');
        html += row('业务 ID', d.biz_id ? escapeHtml(d.biz_id) : '<span style="color:#ccc;">-</span>');
        html += row('操作人', d.operator_name ? escapeHtml(d.operator_name) : '<span style="color:#ccc;">-</span>');
        html += row('发送时间', escapeHtml(d.create_time || ''));
        html += row('更新时间', escapeHtml(d.update_time || ''));
        html += '</div>';

        // 短信内容
        html += '<div class="rd-section"><div class="rd-title">短信内容</div>';
        html += '<div class="rd-box">' + escapeHtml(d.content || '(无内容)') + '</div></div>';

        // 发送参数
        html += '<div class="rd-section"><div class="rd-title">发送参数</div>';
        var params = d.params || {};
        var keys = Object.keys(params);
        if (keys.length) {
            html += '<div class="rd-box">';
            keys.forEach(function (k) {
                html += '<div><span style="color:#999;display:inline-block;min-width:80px;">${' + escapeHtml(k) + '}</span>' + escapeHtml(params[k]) + '</div>';
            });
            html += '</div>';
        } else {
            html += '<div style="color:#ccc;">无参数</div>';
        }
        html += '</div>';

        // 失败原因
        if (d.send_status === 'failed' && d.error_message) {
            html += '<div class="rd-section"><div class="rd-title">失败原因</div>';
            html += '<div class="rd-box" style="color:#f56c6c;">' + escapeHtml(d.error_message) + '</div></div>';
        }

        // 服务商响应
        html += '<div class="rd-section"><div class="rd-title">服务商响应</div>';
        var response = d.response;
        var responseText = '';
        if (response && typeof response === 'object' && Object.keys(response).length) {
            try { responseText = JSON.stringify(response, null, 2); } catch (e) { responseText = String(response); }
        } else if (response) {
            responseText = String(response);
        }
        if (responseText) {
            html += '<div class="rd-pre">' + escapeHtml(responseText) + '</div>';
        } else {
            html += '<div style="color:#ccc;">无响应数据</div>';
        }
        html += '</div>';

        // 接口信息（发送时请求的接口地址与数据，用于排查）
        html += '<div class="rd-section"><div class="rd-title">接口信息</div>';
        var req = d.request_info || {};
        if (req.url) {
            html += row('接口地址', escapeHtml(req.url));
            html += row('请求方式', escapeHtml(req.method || 'GET'));
            var reqParams = req.params || {};
            var reqParamKeys = Object.keys(reqParams);
            if (reqParamKeys.length) {
                html += '<div style="margin:6px 0 4px;color:#999;">请求数据</div>';
                html += '<div class="rd-box">';
                reqParamKeys.forEach(function (k) {
                    var v = reqParams[k];
                    if (v !== null && typeof v === 'object') {
                        try { v = JSON.stringify(v, null, 2); } catch (e) { v = String(v); }
                    }
                    html += '<div><span style="color:#999;display:inline-block;min-width:110px;vertical-align:top;">' + escapeHtml(k) + '</span>' + escapeHtml(String(v)) + '</div>';
                });
                html += '</div>';
            } else {
                html += '<div style="color:#ccc;">无请求数据</div>';
            }
        } else {
            html += '<div style="color:#ccc;">无接口信息（历史记录或请求未发起）</div>';
        }
        html += '</div>';

        html += '</div>';
        return html;

        function row(label, value) {
            return '<div class="rd-row"><span class="ml">' + label + '</span>' + value + '</div>';
        }
    }
});
</script>
</body>
</html>