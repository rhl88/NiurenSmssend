<?php

/**
 * 短信发送 · 应用默认配置
 *
 * 通过 ServiceProvider::register() 中的 mergeConfigFrom() 合并到
 * config('apps.niuren.smssend.*')，作为数据库 config_items 尚未写入时的兜底值。
 *
 * 与 manifest.json 中 config_groups 的默认值保持一致：
 * manifest.json 是「用户可在后台修改的配置」的唯一声明来源，
 * 本文件则是「代码读取配置时的兜底默认值」，二者需同步维护。
 *
 * 说明：AccessKey Secret / SecretKey 等敏感字段均明文存储、明文读取
 * （保存在 config_items，与后台设置页回显一致），业务读取统一走
 * niuren_smssend_config()；此处仅作空字符串兜底。
 */
return [
    // 当前启用的短信服务商：aliyun / tencent / volcano
    'current_provider' => 'aliyun',

    // ── 阿里云短信配置 ──
    'aliyun_access_key_id' => '',
    'aliyun_access_key_secret' => '',
    'aliyun_sign_name' => '',
    'aliyun_region_id' => 'cn-hangzhou',
    'aliyun_endpoint' => 'dysmsapi.aliyuncs.com',

    // ── 腾讯云短信配置 ──
    'tencent_secret_id' => '',
    'tencent_secret_key' => '',
    'tencent_sms_sdk_app_id' => '',
    'tencent_sign_name' => '',
    'tencent_region' => 'ap-guangzhou',
    'tencent_endpoint' => 'sms.tencentcloudapi.com',

    // ── 火山引擎短信配置 ──
    'volcano_access_key' => '',
    'volcano_secret_key' => '',
    'volcano_sign_name' => '',
    'volcano_sms_account' => '',
    'volcano_region' => 'cn-north-1',
    'volcano_endpoint' => 'sms.volcengineapi.com',
];
