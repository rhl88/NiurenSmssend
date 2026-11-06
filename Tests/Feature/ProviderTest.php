<?php

namespace App\Apps\NiurenSmssend\Tests\Feature;

use App\Apps\NiurenSmssend\Exceptions\SmsException;
use App\Apps\NiurenSmssend\Providers\AliyunProvider;
use App\Apps\NiurenSmssend\Providers\ProviderFactory;
use App\Apps\NiurenSmssend\Providers\TencentProvider;
use App\Apps\NiurenSmssend\Providers\VolcanoProvider;
use App\Apps\NiurenSmssend\Tests\Support\NiurenSmssendSetup;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * 短信发送 · 服务商适配器测试
 *
 * 覆盖三家服务商的工厂映射、配置校验、成功回执与错误码映射。
 */
class ProviderTest extends TestCase
{
    use NiurenSmssendSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNiurenSmssend();
    }

    public function test_工厂返回对应服务商适配器(): void
    {
        $this->assertInstanceOf(AliyunProvider::class, ProviderFactory::make('aliyun'));
        $this->assertInstanceOf(TencentProvider::class, ProviderFactory::make('tencent'));
        $this->assertInstanceOf(VolcanoProvider::class, ProviderFactory::make('volcano'));

        $this->assertTrue(ProviderFactory::isSupported('aliyun'));
        $this->assertFalse(ProviderFactory::isSupported('unknown'));
    }

    public function test_工厂收到不支持的服务商时抛出异常(): void
    {
        $this->expectException(SmsException::class);
        $this->expectExceptionMessage('不支持的短信服务商：unknown');

        ProviderFactory::make('unknown');
    }

    public function test_阿里云未完成配置时给出引导提示(): void
    {
        config([
            'apps.niuren.smssend.aliyun_access_key_id' => '',
            'apps.niuren.smssend.aliyun_access_key_secret' => '',
        ]);
        Cache::flush();

        $provider = new AliyunProvider();
        $this->assertFalse($provider->isConfigured());

        $this->expectException(SmsException::class);
        $this->expectExceptionMessage('请在「短信设置」中填写 AccessKeyId 与 AccessKeySecret');

        $provider->send('13800138000', '测试签名', 'SMS_512651836', []);
    }

    public function test_阿里云发送成功返回BizId(): void
    {
        Http::fake([
            'dysmsapi.aliyuncs.com/*' => Http::response([
                'Code' => 'OK',
                'BizId' => 'biz_aliyun_1',
                'RequestId' => 'req_aliyun_1',
            ]),
        ]);

        $result = (new AliyunProvider())->send('13800138000', '测试签名', 'SMS_512651836', ['name' => '张三']);

        $this->assertSame('biz_aliyun_1', $result['vendor_msg_id']);
        $this->assertSame('req_aliyun_1', $result['biz_id']);
        $this->assertSame('OK', $result['response']['Code']);
    }

    public function test_阿里云错误码映射为中文提示(): void
    {
        Http::fake([
            'dysmsapi.aliyuncs.com/*' => Http::response([
                'Code' => 'isv.AMOUNT_NOT_ENOUGH',
                'Message' => 'account balance is not enough',
            ]),
        ]);

        $this->expectException(SmsException::class);
        $this->expectExceptionMessage('阿里云账户余额不足，请及时充值');

        (new AliyunProvider())->send('13800138000', '测试签名', 'SMS_512651836', []);
    }

    public function test_腾讯云未完成配置时给出引导提示(): void
    {
        config([
            'apps.niuren.smssend.tencent_secret_id' => '',
            'apps.niuren.smssend.tencent_secret_key' => '',
            'apps.niuren.smssend.tencent_sms_sdk_app_id' => '',
        ]);
        Cache::flush();

        $provider = new TencentProvider();
        $this->assertFalse($provider->isConfigured());

        $this->expectException(SmsException::class);
        $this->expectExceptionMessage('请在「短信设置」中填写 SecretId、SecretKey 与短信应用 SdkAppId');

        $provider->send('13800138000', '测试签名', '1234567', []);
    }

    public function test_腾讯云发送成功返回SerialNo(): void
    {
        Http::fake([
            'sms.tencentcloudapi.com*' => Http::response([
                'Response' => [
                    'SendStatusSet' => [['SerialNo' => 'sn_tencent_1', 'Code' => 'Ok']],
                    'RequestId' => 'req_tencent_1',
                ],
            ]),
        ]);

        $result = (new TencentProvider())->send('13800138000', '测试签名', '1234567', ['张三']);

        $this->assertSame('sn_tencent_1', $result['vendor_msg_id']);
        $this->assertSame('req_tencent_1', $result['biz_id']);
    }

    public function test_腾讯云错误码映射为中文提示(): void
    {
        Http::fake([
            'sms.tencentcloudapi.com*' => Http::response([
                'Response' => [
                    'Error' => [
                        'Code' => 'FailedOperation.PhoneNumberInBlacklist',
                        'Message' => 'phone number in blacklist',
                    ],
                ],
            ]),
        ]);

        $this->expectException(SmsException::class);
        $this->expectExceptionMessage('手机号在腾讯云黑名单中，发送被拦截');

        (new TencentProvider())->send('13800138000', '测试签名', '1234567', []);
    }

    public function test_火山引擎未完成配置时给出引导提示(): void
    {
        config([
            'apps.niuren.smssend.volcano_access_key' => '',
            'apps.niuren.smssend.volcano_secret_key' => '',
            'apps.niuren.smssend.volcano_sms_account' => '',
        ]);
        Cache::flush();

        $provider = new VolcanoProvider();
        $this->assertFalse($provider->isConfigured());

        $this->expectException(SmsException::class);
        $this->expectExceptionMessage('请在「短信设置」中填写 AccessKey、SecretKey 与短信账号');

        $provider->send('13800138000', '测试签名', '1234567', []);
    }

    public function test_火山引擎已配置但发送能力为预留(): void
    {
        $provider = new VolcanoProvider();
        $this->assertTrue($provider->isConfigured());

        $this->expectException(SmsException::class);
        $this->expectExceptionMessage('预留能力');

        $provider->send('13800138000', '测试签名', '1234567', []);
    }

    public function test_阿里云请求异常时提示不含请求地址与敏感参数(): void
    {
        // 真实 cURL 异常消息会带完整请求 URL，其中含有 AccessKeyId 与 Signature
        Http::fake(function () {
            throw new ConnectionException(
                'cURL error 28: Resolving timed out after 5009 milliseconds (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) '
                . 'for https://dysmsapi.aliyuncs.com/?AccessKeyId=TEST_ACCESS_KEY_ID&Action=SendSms&Signature=TEST_SIGNATURE'
            );
        });

        try {
            (new AliyunProvider())->send('13800138000', '测试签名', 'SMS_512651836', ['name' => '张三']);
            $this->fail('应当抛出 SmsException');
        } catch (SmsException $e) {
            $message = $e->getMessage();

            $this->assertStringContainsString('请求阿里云短信服务失败', $message);
            $this->assertStringContainsString('网络连接异常', $message);
            $this->assertStringContainsString('cURL error 28', $message);
            $this->assertStringNotContainsString('AccessKeyId', $message);
            $this->assertStringNotContainsString('Signature', $message);
            $this->assertStringNotContainsString('dysmsapi.aliyuncs.com', $message);
        }
    }

    public function test_腾讯云请求异常时提示不含请求地址(): void
    {
        Http::fake(function () {
            throw new ConnectionException('cURL error 6: Could not resolve host: sms.tencentcloudapi.com');
        });

        try {
            (new TencentProvider())->send('13800138000', '测试签名', '1234567', ['张三']);
            $this->fail('应当抛出 SmsException');
        } catch (SmsException $e) {
            $message = $e->getMessage();

            $this->assertStringContainsString('请求腾讯云短信服务失败', $message);
            $this->assertStringContainsString('网络连接异常', $message);
            $this->assertStringContainsString('cURL error 6', $message);
            $this->assertStringNotContainsString('sms.tencentcloudapi.com', $message);
        }
    }

    public function test_阿里云发送成功返回接口信息且不含鉴权参数(): void
    {
        Http::fake([
            'dysmsapi.aliyuncs.com/*' => Http::response([
                'Code' => 'OK',
                'BizId' => 'biz_aliyun_req',
                'RequestId' => 'req_aliyun_req',
            ]),
        ]);

        $result = (new AliyunProvider())->send('13800138000', '测试签名', 'SMS_512651836', ['name' => '张三']);

        $info = $result['request_info'] ?? [];
        $this->assertSame('https://dysmsapi.aliyuncs.com/', $info['url'] ?? '');
        $this->assertSame('GET', $info['method'] ?? '');
        $this->assertSame('13800138000', $info['params']['PhoneNumbers'] ?? '');
        $this->assertSame('SMS_512651836', $info['params']['TemplateCode'] ?? '');
        // 鉴权参数只存在于真实请求的查询串中，接口信息不落库展示
        $this->assertArrayNotHasKey('AccessKeyId', $info['params'] ?? []);
        $this->assertArrayNotHasKey('Signature', $info['params'] ?? []);
        $this->assertStringNotContainsString('AccessKeyId', json_encode($info, JSON_UNESCAPED_UNICODE) ?: '');
        $this->assertStringNotContainsString('Signature', json_encode($info, JSON_UNESCAPED_UNICODE) ?: '');
    }

    public function test_阿里云请求异常时携带接口信息且不含鉴权参数(): void
    {
        Http::fake(function () {
            throw new ConnectionException('cURL error 77: Problem with the SSL CA cert');
        });

        try {
            (new AliyunProvider())->send('13800138000', '测试签名', 'SMS_512651836', ['name' => '张三']);
            $this->fail('应当抛出 SmsException');
        } catch (SmsException $e) {
            $info = $e->getRequestInfo();

            $this->assertNotNull($info);
            $this->assertSame('https://dysmsapi.aliyuncs.com/', $info['url'] ?? '');
            $this->assertSame('SMS_512651836', $info['params']['TemplateCode'] ?? '');
            $this->assertStringNotContainsString('AccessKeyId', json_encode($info, JSON_UNESCAPED_UNICODE) ?: '');
            $this->assertStringNotContainsString('Signature', json_encode($info, JSON_UNESCAPED_UNICODE) ?: '');
        }
    }

    public function test_腾讯云发送成功返回接口信息(): void
    {
        Http::fake([
            'sms.tencentcloudapi.com*' => Http::response([
                'Response' => [
                    'SendStatusSet' => [['SerialNo' => 'sn_tencent_req', 'Code' => 'Ok']],
                    'RequestId' => 'req_tencent_req',
                ],
            ]),
        ]);

        $result = (new TencentProvider())->send('13800138000', '测试签名', '1234567', ['张三']);

        $info = $result['request_info'] ?? [];
        $this->assertSame('https://sms.tencentcloudapi.com/', $info['url'] ?? '');
        $this->assertSame('POST', $info['method'] ?? '');
        $this->assertSame('1234567', $info['params']['TemplateId'] ?? '');
        $this->assertSame(['张三'], $info['params']['TemplateParamSet'] ?? []);
        // 鉴权信息在请求头，接口信息不应包含
        $this->assertArrayNotHasKey('Authorization', $info['params'] ?? []);
    }
}
