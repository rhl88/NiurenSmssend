<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>短信设置 - 短信发送</title>
    <link rel="stylesheet" href="{{ asset('CmsProUi/component/pear/css/pear.css') }}">
    <link rel="stylesheet" href="{{ asset('CmsProUi/font-awesome/4.7.0/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/variables.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/reset.css') }}">
    <style>
        html, body { height: 100%; margin: 0; padding: 0; overflow: hidden; }
        *, *::before, *::after { box-sizing: border-box; }
        .layui-form { height: 100%; }
        .layui-form > .content-wrap { height: calc(100vh - 52px); overflow-y: auto; padding: 15px; margin: 0; background: #f7f8fa; }
        .bottom { height: 52px; background: #fff; border-top: 1px solid #e6e6e6; text-align: right; }
        .bottom .button-container { display: inline-block; line-height: 52px; padding-right: 15px; }
        .bottom .button-container button { margin-left: 10px; }
        .layui-form-label { white-space: nowrap; width: 150px; }
        .layui-input-block { margin-left: 180px; }
        .helper-tip { font-size: 12px; color: #999; margin-top: 4px; }
        .provider-badge { display: inline-block; font-size: 12px; padding: 1px 8px; border-radius: 10px; margin-left: 8px; }
        .provider-badge.on { background: #e8f5e9; color: #2e9d4e; }
        .provider-badge.off { background: #f2f2f2; color: #999; }
    </style>
    @include('admin.partials.permission-script')
</head>
<body>
<form class="layui-form" lay-filter="settingForm">
    <div class="content-wrap">
        {{-- 当前启用服务商 --}}
        <div class="layui-card">
            <div class="layui-card-header">当前启用服务商</div>
            <div class="layui-card-body">
                <div class="layui-form-item">
                    <label class="layui-form-label">服务商</label>
                    <div class="layui-input-block">
                        <select name="current_provider" lay-filter="currentProvider">
                            <option value="aliyun">阿里云</option>
                            <option value="tencent">腾讯云</option>
                            <option value="volcano">火山引擎</option>
                        </select>
                        <div class="helper-tip">新建短信模板与发送页默认使用该服务商；实际发送时以「模板自身所属服务商」的配置调用接口。</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- 阿里云 --}}
        <div class="layui-card" data-provider-card="aliyun">
            <div class="layui-card-header">阿里云短信配置 <span class="provider-badge" id="badgeAliyun"></span></div>
            <div class="layui-card-body">
                <div class="layui-form-item">
                    <label class="layui-form-label">AccessKey ID</label>
                    <div class="layui-input-block">
                        <input type="text" name="aliyun_access_key_id" class="layui-input" autocomplete="off" placeholder="阿里云账号 AccessKey ID（RAM 用户）">
                    </div>
                </div>
                <div class="layui-form-item">
                    <label class="layui-form-label">AccessKey Secret</label>
                    <div class="layui-input-block">
                        <input type="text" name="aliyun_access_key_secret" class="layui-input" autocomplete="off" placeholder="阿里云 AccessKey Secret（已配置则以明文回显）">
                        <div class="helper-tip">用于签名计算；明文存储，保存后可回显；留空保存保持原值。</div>
                    </div>
                </div>
                <div class="layui-form-item">
                    <label class="layui-form-label">短信签名</label>
                    <div class="layui-input-block">
                        <input type="text" name="aliyun_sign_name" class="layui-input" autocomplete="off" placeholder="控制台审核通过的签名名称，如：牛人体检">
                        <div class="helper-tip">模板未单独指定签名时使用此签名。</div>
                    </div>
                </div>
                <div class="layui-form-item">
                    <label class="layui-form-label">地域 RegionId</label>
                    <div class="layui-input-block">
                        <input type="text" name="aliyun_region_id" class="layui-input" autocomplete="off" placeholder="默认 cn-hangzhou">
                    </div>
                </div>
                <div class="layui-form-item">
                    <label class="layui-form-label">接口地址 Endpoint</label>
                    <div class="layui-input-block">
                        <input type="text" name="aliyun_endpoint" class="layui-input" autocomplete="off" placeholder="默认 dysmsapi.aliyuncs.com">
                    </div>
                </div>
            </div>
        </div>

        {{-- 腾讯云 --}}
        <div class="layui-card" data-provider-card="tencent">
            <div class="layui-card-header">腾讯云短信配置 <span class="provider-badge" id="badgeTencent"></span></div>
            <div class="layui-card-body">
                <div class="layui-form-item">
                    <label class="layui-form-label">SecretId</label>
                    <div class="layui-input-block">
                        <input type="text" name="tencent_secret_id" class="layui-input" autocomplete="off" placeholder="腾讯云访问密钥 SecretId">
                    </div>
                </div>
                <div class="layui-form-item">
                    <label class="layui-form-label">SecretKey</label>
                    <div class="layui-input-block">
                        <input type="text" name="tencent_secret_key" class="layui-input" autocomplete="off" placeholder="腾讯云访问密钥 SecretKey（已配置则以明文回显）">
                        <div class="helper-tip">用于 TC3-HMAC-SHA256 签名计算；明文存储，保存后可回显；留空保存保持原值。</div>
                    </div>
                </div>
                <div class="layui-form-item">
                    <label class="layui-form-label">短信应用 SdkAppId</label>
                    <div class="layui-input-block">
                        <input type="text" name="tencent_sms_sdk_app_id" class="layui-input" autocomplete="off" placeholder="短信控制台应用 SdkAppId，如：1400xxxxxx">
                    </div>
                </div>
                <div class="layui-form-item">
                    <label class="layui-form-label">短信签名</label>
                    <div class="layui-input-block">
                        <input type="text" name="tencent_sign_name" class="layui-input" autocomplete="off" placeholder="短信签名内容（不含【】符号）">
                    </div>
                </div>
                <div class="layui-form-item">
                    <label class="layui-form-label">地域 Region</label>
                    <div class="layui-input-block">
                        <input type="text" name="tencent_region" class="layui-input" autocomplete="off" placeholder="默认 ap-guangzhou">
                    </div>
                </div>
                <div class="layui-form-item">
                    <label class="layui-form-label">接口地址 Endpoint</label>
                    <div class="layui-input-block">
                        <input type="text" name="tencent_endpoint" class="layui-input" autocomplete="off" placeholder="默认 sms.tencentcloudapi.com">
                    </div>
                </div>
            </div>
        </div>

        {{-- 火山引擎 --}}
        <div class="layui-card" data-provider-card="volcano">
            <div class="layui-card-header">火山引擎短信配置 <span class="provider-badge" id="badgeVolcano"></span></div>
            <div class="layui-card-body">
                <div class="layui-form-item">
                    <label class="layui-form-label">AccessKey</label>
                    <div class="layui-input-block">
                        <input type="text" name="volcano_access_key" class="layui-input" autocomplete="off" placeholder="火山引擎 AccessKey ID">
                    </div>
                </div>
                <div class="layui-form-item">
                    <label class="layui-form-label">SecretKey</label>
                    <div class="layui-input-block">
                        <input type="text" name="volcano_secret_key" class="layui-input" autocomplete="off" placeholder="火山引擎 Secret Access Key（已配置则以明文回显）">
                        <div class="helper-tip">明文存储，保存后可回显；留空保存保持原值。</div>
                    </div>
                </div>
                <div class="layui-form-item">
                    <label class="layui-form-label">短信签名</label>
                    <div class="layui-input-block">
                        <input type="text" name="volcano_sign_name" class="layui-input" autocomplete="off" placeholder="火山引擎短信签名">
                    </div>
                </div>
                <div class="layui-form-item">
                    <label class="layui-form-label">短信服务账号</label>
                    <div class="layui-input-block">
                        <input type="text" name="volcano_sms_account" class="layui-input" autocomplete="off" placeholder="火山引擎短信消息组 ID（SmsAccount）">
                        <div class="helper-tip">火山引擎短信控制台的消息组 ID，即接口参数 SmsAccount，用于标识短信发送账号。</div>
                    </div>
                </div>
                <div class="layui-form-item">
                    <label class="layui-form-label">地域 Region</label>
                    <div class="layui-input-block">
                        <input type="text" name="volcano_region" class="layui-input" autocomplete="off" placeholder="默认 cn-north-1">
                    </div>
                </div>
                <div class="layui-form-item">
                    <label class="layui-form-label">接口地址 Endpoint</label>
                    <div class="layui-input-block">
                        <input type="text" name="volcano_endpoint" class="layui-input" autocomplete="off" placeholder="默认 sms.volcengineapi.com">
                        <div class="helper-tip">火山引擎短信 API 地址，一般无需修改。</div>
                    </div>
                </div>
            </div>
        </div>
        {{-- SSL 证书安全 --}}
        <div class="layui-card">
            <div class="layui-card-header">SSL 证书安全</div>
            <div class="layui-card-body">
                <div class="layui-form-item">
                    <label class="layui-form-label">SSL证书验证</label>
                    <div class="layui-input-block">
                        <input type="checkbox" name="ssl_verify" lay-skin="switch" lay-text="开启|关闭">
                        <div class="helper-tip">是否验证短信服务商接口的 SSL 证书。发送报 cURL error 60/77 时可先「下载CA证书」再保持开启；关闭验证仅作应急排查用，存在安全风险。</div>
                    </div>
                </div>
                <div class="layui-form-item">
                    <label class="layui-form-label">CA证书路径</label>
                    <div class="layui-input-block">
                        <input type="text" name="ssl_ca_path" class="layui-input" autocomplete="off" placeholder="app/niuren_smssend/certs/cacert.pem">
                        <div class="helper-tip">应用级 CA 证书路径（相对于 storage 目录）。配置且文件存在后，发送请求优先使用该证书，不依赖服务器 php.ini 配置。</div>
                    </div>
                </div>
                <div class="layui-form-item">
                    <label class="layui-form-label">证书检查</label>
                    <div class="layui-input-block">
                        <button type="button" class="layui-btn layui-btn-sm" id="btnCheckSsl"><i class="fa fa-search"></i> 检查SSL证书</button>
                        <button type="button" class="layui-btn layui-btn-sm layui-btn-normal" id="btnDownloadCa"><i class="fa fa-download"></i> 下载CA证书</button>
                        <div id="sslCheckResult" style="font-size:13px;margin-top:8px;color:#666;line-height:1.8;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="bottom">
        <div class="button-container">
            <button type="submit" class="layui-btn layui-btn-normal layui-btn-sm" lay-submit lay-filter="doSave" data-permission="niuren.smssend.setting">
                <i class="fa fa-save"></i> 保存设置
            </button>
        </div>
    </div>
</form>

<script src="{{ asset('CmsProUi/component/layui/layui.js') }}"></script>
<script src="{{ asset('CmsProUi/component/pear/pear.js') }}"></script>
<script>
layui.use(['jquery', 'form', 'layer'], function () {
    var $ = layui.jquery,
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

    function hasPerm(code) {
        return !window.hasPermission || window.hasPermission(code);
    }
    $('button[data-permission]').each(function () {
        if (!hasPerm($(this).data('permission'))) { $(this).hide(); }
    });

    // 各卡片"当前启用"徽标
    function refreshBadges(current) {
        var map = { aliyun: '#badgeAliyun', tencent: '#badgeTencent', volcano: '#badgeVolcano' };
        $.each(map, function (code, sel) {
            var $badge = $(sel);
            if (code === current) {
                $badge.attr('class', 'provider-badge on').text('当前启用');
            } else {
                $badge.attr('class', 'provider-badge off').text('未启用');
            }
        });
    }

    form.on('select(currentProvider)', function (data) {
        applyProvider(data.value);
    });

    // 仅显示当前选中服务商的配置卡片，未选中的隐藏
    function refreshProviderCards(current) {
        $('[data-provider-card]').each(function () {
            $(this).toggle($(this).attr('data-provider-card') === current);
        });
    }

    // 同步「当前启用」徽标与配置卡片显隐
    function applyProvider(current) {
        refreshBadges(current);
        refreshProviderCards(current);
    }

    // 回填当前配置（敏感项明文回显）
    $.get(API + '/settings', function (res) {
        if (res.code !== 0) { layer.msg(res.message || '配置读取失败', { icon: 2 }); return; }
        var s = res.data || {};
        $('select[name="current_provider"]').val(s.current_provider || 'aliyun');
        $.each([
            'aliyun_access_key_id', 'aliyun_access_key_secret', 'aliyun_sign_name', 'aliyun_region_id', 'aliyun_endpoint',
            'tencent_secret_id', 'tencent_secret_key', 'tencent_sms_sdk_app_id', 'tencent_sign_name', 'tencent_region', 'tencent_endpoint',
            'volcano_access_key', 'volcano_secret_key', 'volcano_sign_name', 'volcano_sms_account', 'volcano_region', 'volcano_endpoint'
        ], function (i, key) {
            $('[name="' + key + '"]').val(s[key] || '');
        });
        $('input[name="ssl_verify"]').prop('checked', (s.ssl_verify || '1') === '1');
        $('[name="ssl_ca_path"]').val(s.ssl_ca_path || '');
        form.render('select');
        form.render('checkbox');
        applyProvider(s.current_provider || 'aliyun');
    }).fail(function () { layer.msg('配置读取失败', { icon: 2 }); });

    // 保存
    form.on('submit(doSave)', function (data) {
        if (!hasPerm('niuren.smssend.setting')) { layer.msg('无操作权限', { icon: 2 }); return false; }

        var payload = {};
        $.each(data.field, function (k, v) {
            payload[k] = $.trim(v);
        });
        // layui 开关组件提交值为 "on"（未勾选不出现在 data.field），统一转为 0/1
        payload.ssl_verify = data.field.ssl_verify === 'on' ? '1' : '0';

        var idx = layer.load(1, { shade: 0.3 });
        $.ajax({
            url: API + '/settings',
            type: 'PUT',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify(payload),
            success: function (res) {
                layer.close(idx);
                layer.msg(res.message || (res.code === 0 ? '设置保存成功' : '保存失败'), { icon: res.code === 0 ? 1 : 2, time: res.code === 0 ? 1200 : 2000 });
            },
            error: function () { layer.close(idx); layer.msg('保存失败', { icon: 2 }); }
        });
        return false;
    });

    // ─────────────────────────────────────────────
    // SSL 证书检查与 CA 证书安装
    // ─────────────────────────────────────────────
    function escapeText(str) {
        return String(str == null ? '' : str).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // 检查 SSL 证书配置状态
    $('#btnCheckSsl').on('click', function () {
        var idx = layer.load(2);
        $.get(API + '/settings/check-ssl', function (res) {
            layer.close(idx);
            if (res.code !== 0) { layer.msg(res.message || '检查失败', { icon: 2 }); return; }
            var d = res.data || {};
            var color = d.status === 'ok' ? '#16b777' : (d.status === 'disabled' ? '#ffb800' : '#ff5722');
            var html = '<div style="color:' + color + ';font-weight:600;">' + escapeText(d.message || '') + '</div>';
            html += '<div style="font-size:12px;color:#999;margin-top:4px;">'
                + 'curl.cainfo: ' + escapeText(d.curl_cainfo || '未配置') + '<br>'
                + 'openssl.cafile: ' + escapeText(d.openssl_cafile || '未配置') + '<br>'
                + '应用级CA文件: ' + (d.ca_file_exists ? '存在' : '不存在') + '<br>'
                + '当前SSL验证: ' + (d.ssl_verify === '0' ? '关闭' : '开启') + '<br>'
                + '应用级CA路径: ' + escapeText(d.ssl_ca_path || '未配置')
                + '</div>';
            $('#sslCheckResult').html(html);
        }).fail(function () { layer.close(idx); layer.msg('请求失败', { icon: 2 }); });
    });

    // 下载 CA 证书（成功后自动填入路径并刷新检查结果）
    $('#btnDownloadCa').on('click', function () {
        var idx = layer.load(2);
        $.post(API + '/settings/download-ca', function (res) {
            layer.close(idx);
            if (res.code !== 0) { layer.msg(res.message || '下载失败', { icon: 2 }); return; }
            layer.msg(res.message || 'CA 证书下载成功', { icon: 1 });
            $('[name="ssl_ca_path"]').val((res.data || {}).path || '');
            $('#btnCheckSsl').trigger('click');
        }).fail(function () { layer.close(idx); layer.msg('请求失败', { icon: 2 }); });
    });
});
</script>
</body>
</html>
