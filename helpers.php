<?php

/**
 * 短信发送 · 应用级辅助函数
 *
 * 由 ServiceProvider::register() 通过 require_once 加载。
 * 所有函数均用 function_exists 包裹，保证重复加载幂等，
 * 且函数名带 niuren_smssend_ 前缀，避免与系统或其他应用冲突。
 */

use App\Models\ConfigItem;
use Illuminate\Support\Facades\Cache;

if (! function_exists('niuren_smssend_config')) {
    /**
     * 读取本应用配置值（优先数据库 config_items，回退 Config/smssend.php）
     *
     * 所有配置（含 access_key_secret / secret_key 等）均明文存储、明文读取。
     *
     * @param string $key     配置键名（不含前缀），如 aliyun_access_key_id
     * @param mixed  $default 兜底默认值
     * @return mixed 配置值
     */
    function niuren_smssend_config(string $key, mixed $default = null): mixed
    {
        $cacheKey = 'niuren_smssend:config:' . $key;

        return Cache::remember($cacheKey, 3600, function () use ($key, $default) {
            try {
                $value = ConfigItem::where('code', 'app_niuren_smssend_' . $key)->value('value');
                if ($value !== null && $value !== '') {
                    return $value;
                }
            } catch (\Throwable $e) {
                // 数据库不可用（如安装前、迁移中）时静默降级到配置文件
            }

            return config('apps.niuren.smssend.' . $key, $default);
        });
    }
}

if (! function_exists('niuren_smssend_current_provider')) {
    /**
     * 读取当前启用的短信服务商标识
     *
     * @return string aliyun / tencent / volcano（异常兜底 aliyun）
     */
    function niuren_smssend_current_provider(): string
    {
        $provider = (string) niuren_smssend_config('current_provider', 'aliyun');

        return in_array($provider, ['aliyun', 'tencent', 'volcano'], true) ? $provider : 'aliyun';
    }
}

if (! function_exists('niuren_smssend_forget_config_cache')) {
    /**
     * 清除本应用配置缓存
     *
     * 后台设置页保存配置后调用，确保 niuren_smssend_config() 立即读到新值。
     *
     * @param array<int,string> $keys 需要清理的配置键名列表（不含前缀）
     */
    function niuren_smssend_forget_config_cache(array $keys): void
    {
        foreach ($keys as $key) {
            Cache::forget('niuren_smssend:config:' . $key);
        }
    }
}
