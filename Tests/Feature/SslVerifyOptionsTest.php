<?php

namespace App\Apps\NiurenSmssend\Tests\Feature;

use App\Apps\NiurenSmssend\Providers\Support\SslVerifyOptions;
use App\Apps\NiurenSmssend\Tests\Support\NiurenSmssendSetup;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * 短信发送 · SSL 证书验证选项测试
 *
 * 覆盖 SslVerifyOptions::build() 三种分支：
 * 未配置（沿用 PHP 默认）、关闭验证（verify=false）、应用级 CA 路径（绝对路径 / 路径失效回退）。
 */
class SslVerifyOptionsTest extends TestCase
{
    use NiurenSmssendSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNiurenSmssend();
    }

    public function test_未配置SSL选项时沿用PHP默认行为(): void
    {
        config([
            'apps.niuren.smssend.ssl_verify' => '1',
            'apps.niuren.smssend.ssl_ca_path' => '',
        ]);
        Cache::flush();

        $this->assertSame([], SslVerifyOptions::build());
    }

    public function test_关闭SSL验证时返回verify_false(): void
    {
        config(['apps.niuren.smssend.ssl_verify' => '0']);
        Cache::flush();

        $this->assertSame(['verify' => false], SslVerifyOptions::build());
    }

    public function test_应用级CA路径存在时返回绝对路径(): void
    {
        // 在测试 storage 中落一个真实文件，模拟「下载CA证书」后的状态
        $relativePath = 'app/niuren_smssend/certs/test_ca.pem';
        $absolutePath = storage_path($relativePath);
        $dir = dirname($absolutePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($absolutePath, '-----BEGIN CERTIFICATE-----TEST-----END CERTIFICATE-----');

        try {
            config(['apps.niuren.smssend.ssl_ca_path' => $relativePath]);
            Cache::flush();

            $this->assertSame(['verify' => $absolutePath], SslVerifyOptions::build());
        } finally {
            @unlink($absolutePath);
        }
    }

    public function test_应用级CA路径不存在时回退PHP默认行为(): void
    {
        config(['apps.niuren.smssend.ssl_ca_path' => 'app/niuren_smssend/certs/not_exists.pem']);
        Cache::flush();

        $this->assertSame([], SslVerifyOptions::build());
    }
}
