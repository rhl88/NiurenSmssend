<?php

namespace App\Apps\NiurenSmssend\Providers\Support;

/**
 * 短信发送 · SSL 证书验证选项构建工具
 *
 * 按后台「SSL 证书安全」配置生成 HTTP Client 的 verify 选项，供各服务商适配器复用：
 * - ssl_verify 关闭：返回 verify=false 跳过证书校验（仅应急排查用，存在安全风险）；
 * - ssl_ca_path 配置且文件存在：返回 verify=<绝对路径>，使用应用级 CA 证书，
 *   使发送不依赖服务器 php.ini 的 curl.cainfo / openssl.cafile 配置；
 * - 其他情况：返回空数组，沿用 PHP 默认行为（php.ini 配置或系统证书库）。
 */
class SslVerifyOptions
{
    /**
     * 构建 verify 选项
     *
     * @return array<string,mixed> 传给 Http::withOptions() 的选项（可为空数组）
     */
    public static function build(): array
    {
        $verify = trim((string) niuren_smssend_config('ssl_verify', '1'));

        if ($verify === '0') {
            return ['verify' => false];
        }

        $caPath = trim((string) niuren_smssend_config('ssl_ca_path', ''));
        if ($caPath !== '') {
            $absolute = storage_path($caPath);
            if (is_file($absolute)) {
                return ['verify' => $absolute];
            }
        }

        return [];
    }
}
