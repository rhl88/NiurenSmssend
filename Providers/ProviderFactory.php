<?php

namespace App\Apps\NiurenSmssend\Providers;

use App\Apps\NiurenSmssend\Contracts\SmsProviderInterface;
use App\Apps\NiurenSmssend\Exceptions\SmsException;
use App\Apps\NiurenSmssend\Models\SmsTemplate;

/**
 * 短信发送 · 服务商适配器工厂
 *
 * 根据模板所属服务商标识实例化对应适配器，是发送服务与具体服务商之间的唯一入口。
 * 新增服务商：实现 SmsProviderInterface 后在 PROVIDERS 中登记即可。
 */
class ProviderFactory
{
    /**
     * 服务商标识 → 适配器类映射
     */
    private const PROVIDERS = [
        SmsTemplate::PROVIDER_ALIYUN => AliyunProvider::class,
        SmsTemplate::PROVIDER_TENCENT => TencentProvider::class,
        SmsTemplate::PROVIDER_VOLCANO => VolcanoProvider::class,
    ];

    /**
     * 实例化指定服务商适配器
     *
     * @param string $provider 服务商标识（aliyun / tencent / volcano）
     *
     * @throws SmsException 服务商标识不受支持时
     */
    public static function make(string $provider): SmsProviderInterface
    {
        $provider = trim($provider);

        if (! isset(self::PROVIDERS[$provider])) {
            throw new SmsException('不支持的短信服务商：' . ($provider !== '' ? $provider : '（空）'));
        }

        $class = self::PROVIDERS[$provider];

        return new $class();
    }

    /**
     * 是否支持指定服务商
     */
    public static function isSupported(string $provider): bool
    {
        return isset(self::PROVIDERS[trim($provider)]);
    }

    /**
     * 全部受支持的服务商标识
     *
     * @return array<int,string>
     */
    public static function supportedProviders(): array
    {
        return array_keys(self::PROVIDERS);
    }
}
