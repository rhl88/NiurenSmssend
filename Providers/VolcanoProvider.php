<?php

namespace App\Apps\NiurenSmssend\Providers;

use App\Apps\NiurenSmssend\Contracts\SmsProviderInterface;
use App\Apps\NiurenSmssend\Exceptions\SmsException;

/**
 * 短信发送 · 火山引擎短信适配器（预留）
 *
 * 需求明确火山引擎为「预留」对接：本类完整实现配置校验与接口契约，
 * 但 send() 暂不发起真实请求，而是抛出明确的业务提示，
 * 引导用户改用已开放的阿里云 / 腾讯云通道。
 *
 * 后续接入时，仅需在 send() 中替换为火山引擎 V4 签名请求实现，
 * 上层发送服务与视图无需任何改动。
 */
class VolcanoProvider implements SmsProviderInterface
{
    /**
     * 未开放发送能力的统一提示
     */
    private const NOT_AVAILABLE_MESSAGE = '火山引擎短信接入为预留能力，当前版本暂未开放发送，请改用阿里云或腾讯云';

    /**
     * AccessKey（明文）
     */
    private string $accessKey;

    /**
     * SecretKey（明文）
     */
    private string $secretKey;

    /**
     * 短信账号（火山引擎短信服务的 Message Group 账号）
     */
    private string $smsAccount;

    /**
     * 默认短信签名
     */
    private string $defaultSignName;

    /**
     * 地域
     */
    private string $region;

    /**
     * 接口域名（不含协议）
     */
    private string $endpoint;

    public function __construct()
    {
        $this->accessKey = trim((string) niuren_smssend_config('volcano_access_key', ''));
        $this->secretKey = trim((string) niuren_smssend_config('volcano_secret_key', ''));
        $this->smsAccount = trim((string) niuren_smssend_config('volcano_sms_account', ''));
        $this->defaultSignName = trim((string) niuren_smssend_config('volcano_sign_name', ''));
        $this->region = trim((string) niuren_smssend_config('volcano_region', 'cn-north-1'));
        $this->endpoint = trim((string) niuren_smssend_config('volcano_endpoint', 'sms.volcengineapi.com'));
    }

    public function code(): string
    {
        return 'volcano';
    }

    public function name(): string
    {
        return '火山引擎';
    }

    public function isConfigured(): bool
    {
        return $this->accessKey !== ''
            && $this->secretKey !== ''
            && $this->smsAccount !== '';
    }

    public function missingConfigMessage(): string
    {
        return '火山引擎短信未完成配置，请在「短信设置」中填写 AccessKey、SecretKey 与短信账号';
    }

    /**
     * @throws SmsException 恒抛出：火山引擎发送能力为预留，暂未开放
     */
    public function send(string $mobile, string $signName, string $templateCode, array $params): array
    {
        if (! $this->isConfigured()) {
            throw new SmsException($this->missingConfigMessage());
        }

        throw new SmsException(self::NOT_AVAILABLE_MESSAGE);
    }
}