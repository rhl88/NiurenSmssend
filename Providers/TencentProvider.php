<?php

namespace App\Apps\NiurenSmssend\Providers;

use App\Apps\NiurenSmssend\Contracts\SmsProviderInterface;
use App\Apps\NiurenSmssend\Exceptions\SmsException;
use App\Apps\NiurenSmssend\Providers\Support\HttpErrorFormatter;
use App\Apps\NiurenSmssend\Providers\Support\SslVerifyOptions;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * 短信发送 · 腾讯云短信适配器
 *
 * 对接腾讯云短信 SendSms 接口（POST JSON）：
 * - 接口版本 2021-01-11，域名默认 sms.tencentcloudapi.com，服务名 sms
 * - 签名算法 TC3-HMAC-SHA256，需自行构造 CanonicalRequest / StringToSign / 派生密钥
 * - 模板变量通过 TemplateParamSet 有序数组传递（顺序须与腾讯云模板 ${1} ${2} 一致）
 * - 手机号统一归一化为 +国家码 形式（默认 +86）
 *
 * 配置读取优先级：数据库 config_items（后台设置页）→ Config/smssend.php 兜底。
 */
class TencentProvider implements SmsProviderInterface
{
    /**
     * 腾讯云短信接口版本
     */
    private const API_VERSION = '2021-01-11';

    /**
     * 腾讯云服务名（参与签名派生）
     */
    private const SERVICE = 'sms';

    /**
     * 腾讯云接口动作
     */
    private const ACTION = 'SendSms';

    /**
     * 签名算法标识
     */
    private const ALGORITHM = 'TC3-HMAC-SHA256';

    /**
     * 请求内容类型（参与签名，须与实际发送完全一致）
     */
    private const CONTENT_TYPE = 'application/json; charset=utf-8';

    /**
     * 连接超时（秒）
     */
    private const CONNECT_TIMEOUT = 5;

    /**
     * 请求超时（秒）
     */
    private const REQUEST_TIMEOUT = 30;

    /**
     * 常见错误码 → 面向用户的中文提示
     */
    private const ERROR_MESSAGES = [
        'AuthFailure.SignatureFailure' => '签名验证失败，请检查 SecretId / SecretKey 是否正确',
        'AuthFailure.SecretIdNotFound' => 'SecretId 不存在或已禁用',
        'AuthFailure.SignatureExpire' => '签名已过期，请检查服务器时间是否准确',
        'FailedOperation.SignatureIncorrectOrUnapproved' => '短信签名不正确或未审核通过',
        'FailedOperation.TemplateIncorrectOrUnapproved' => '短信模板不正确或未审核通过，请核对模板 ID',
        'FailedOperation.InsufficientBalanceInSmsPackage' => '短信套餐余量不足，请及时购买',
        'FailedOperation.PhoneNumberInBlacklist' => '手机号在腾讯云黑名单中，发送被拦截',
        'FailedOperation.ContainSensitiveWord' => '短信内容包含敏感词，发送被拦截',
        'InvalidParameterValue.IncorrectPhoneNumber' => '手机号码格式不正确',
        'LimitExceeded.PhoneNumberDailyLimit' => '该手机号触发日发送量限制',
        'LimitExceeded.PhoneNumberThirtySecondLimit' => '发送过于频繁，请稍后再试',
        'UnauthorizedOperation.SmsSdkAppIdVerifyFail' => 'SmsSdkAppId 校验失败，请核对应用 ID',
    ];

    /**
     * SecretId（明文）
     */
    private string $secretId;

    /**
     * SecretKey（明文）
     */
    private string $secretKey;

    /**
     * 短信应用 SDK AppID
     */
    private string $sdkAppId;

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
        $this->secretId = trim((string) niuren_smssend_config('tencent_secret_id', ''));
        $this->secretKey = trim((string) niuren_smssend_config('tencent_secret_key', ''));
        $this->sdkAppId = trim((string) niuren_smssend_config('tencent_sms_sdk_app_id', ''));
        $this->defaultSignName = trim((string) niuren_smssend_config('tencent_sign_name', ''));
        $this->region = trim((string) niuren_smssend_config('tencent_region', 'ap-guangzhou'));
        $this->endpoint = $this->normalizeEndpoint((string) niuren_smssend_config('tencent_endpoint', 'sms.tencentcloudapi.com'));
    }

    public function code(): string
    {
        return 'tencent';
    }

    public function name(): string
    {
        return '腾讯云';
    }

    public function isConfigured(): bool
    {
        return $this->secretId !== '' && $this->secretKey !== '' && $this->sdkAppId !== '';
    }

    public function missingConfigMessage(): string
    {
        return '腾讯云短信未完成配置，请在「短信设置」中填写 SecretId、SecretKey 与短信应用 SdkAppId';
    }

    /**
     * @throws SmsException
     */
    public function send(string $mobile, string $signName, string $templateCode, array $params): array
    {
        if (! $this->isConfigured()) {
            throw new SmsException($this->missingConfigMessage());
        }

        $signName = trim($signName) !== '' ? trim($signName) : $this->defaultSignName;
        if ($signName === '') {
            throw new SmsException('腾讯云短信签名未配置，请在模板或「短信设置」中填写签名');
        }

        if (trim($templateCode) === '') {
            throw new SmsException('短信模板 ID 为空，请先完善模板配置');
        }

        $payload = json_encode([
            'PhoneNumberSet' => [$this->normalizeMobile($mobile)],
            'SmsSdkAppId' => $this->sdkAppId,
            'SignName' => $signName,
            'TemplateId' => $templateCode,
            'TemplateParamSet' => array_values(array_map('strval', $params)),
        ], JSON_UNESCAPED_UNICODE);

        if ($payload === false) {
            throw new SmsException('腾讯云短信请求参数序列化失败');
        }

        $businessParams = json_decode($payload, true) ?: [];

        try {
            $result = $this->request($payload);
        } catch (SmsException $e) {
            throw $e->withRequestInfo($this->buildRequestInfo($businessParams));
        }

        $status = $result['Response']['SendStatusSet'][0] ?? null;
        if (! is_array($status) || ($status['Code'] ?? '') !== 'Ok') {
            throw $this->buildError($result, $status)->withRequestInfo($this->buildRequestInfo($businessParams));
        }

        return [
            'vendor_msg_id' => (string) ($status['SerialNo'] ?? ''),
            'biz_id' => (string) ($result['Response']['RequestId'] ?? ''),
            'response' => $result,
            'request_info' => $this->buildRequestInfo($businessParams),
        ];
    }

    /**
     * 组装接口信息（发送记录排查用）
     *
     * 仅记录端点地址与请求体参数；鉴权信息在请求头
     * （Authorization TC3 签名），不落库展示。
     *
     * @param array<string,mixed> $businessParams
     * @return array{url:string,method:string,params:array<string,mixed>}
     */
    private function buildRequestInfo(array $businessParams): array
    {
        return [
            'url' => 'https://' . $this->endpoint . '/',
            'method' => 'POST',
            'params' => $businessParams,
        ];
    }

    /**
     * 发起已签名请求并返回解码后的响应数组
     *
     * @throws SmsException
     */
    private function request(string $payload): array
    {
        $timestamp = time();
        $authorization = $this->buildAuthorization($payload, $timestamp);

        try {
            $response = Http::withHeaders([
                'Authorization' => $authorization,
                'Host' => $this->endpoint,
                'X-TC-Action' => self::ACTION,
                'X-TC-Timestamp' => (string) $timestamp,
                'X-TC-Version' => self::API_VERSION,
                'X-TC-Region' => $this->region,
            ])
                ->withOptions(SslVerifyOptions::build())
                ->timeout(self::REQUEST_TIMEOUT)
                ->connectTimeout(self::CONNECT_TIMEOUT)
                ->withBody($payload, self::CONTENT_TYPE)
                ->post('https://' . $this->endpoint);
        } catch (Throwable $e) {
            throw new SmsException('请求腾讯云短信服务失败：' . HttpErrorFormatter::describe($e));
        }

        $result = $response->json();

        if (! is_array($result)) {
            throw new SmsException('腾讯云短信服务返回异常（HTTP ' . $response->status() . '）');
        }

        if (isset($result['Response']['Error'])) {
            throw $this->buildError($result, null);
        }

        return $result;
    }

    /**
     * 构造 TC3-HMAC-SHA256 授权头
     */
    private function buildAuthorization(string $payload, int $timestamp): string
    {
        $date = gmdate('Y-m-d', $timestamp);
        $scope = $date . '/' . self::SERVICE . '/tc3_request';

        $canonicalHeaders = 'content-type:' . self::CONTENT_TYPE . "\n"
            . 'host:' . $this->endpoint . "\n"
            . 'x-tc-action:' . strtolower(self::ACTION) . "\n";
        $signedHeaders = 'content-type;host;x-tc-action';

        $canonicalRequest = implode("\n", [
            'POST',
            '/',
            '',
            $canonicalHeaders,
            $signedHeaders,
            hash('sha256', $payload),
        ]);

        $stringToSign = implode("\n", [
            self::ALGORITHM,
            (string) $timestamp,
            $scope,
            hash('sha256', $canonicalRequest),
        ]);

        $secretDate = hash_hmac('sha256', $date, 'TC3' . $this->secretKey, true);
        $secretService = hash_hmac('sha256', self::SERVICE, $secretDate, true);
        $secretSigning = hash_hmac('sha256', 'tc3_request', $secretService, true);
        $signature = hash_hmac('sha256', $stringToSign, $secretSigning);

        return self::ALGORITHM
            . ' Credential=' . $this->secretId . '/' . $scope
            . ', SignedHeaders=' . $signedHeaders
            . ', Signature=' . $signature;
    }

    /**
     * 从失败响应构造业务异常
     *
     * @param array<string,mixed>      $result 响应整体
     * @param array<string,mixed>|null $status 单条发送状态（可能为 null）
     */
    private function buildError(array $result, ?array $status): SmsException
    {
        $error = $result['Response']['Error'] ?? [];
        $code = (string) ($error['Code'] ?? ($status['Code'] ?? ''));
        $rawMessage = (string) ($error['Message'] ?? ($status['Message'] ?? ''));

        $message = self::ERROR_MESSAGES[$code] ?? ($rawMessage !== ''
            ? $rawMessage
            : ('腾讯云短信发送失败（错误码 ' . $code . '）'));

        return new SmsException($message);
    }

    /**
     * 手机号归一化：补齐国家码前缀
     */
    private function normalizeMobile(string $mobile): string
    {
        $mobile = trim($mobile);

        if (str_starts_with($mobile, '+')) {
            return $mobile;
        }

        if (preg_match('/^1\d{10}$/', $mobile)) {
            return '+86' . $mobile;
        }

        return $mobile;
    }

    /**
     * 规范化域名：剥离协议与路径，仅保留 host
     */
    private function normalizeEndpoint(string $endpoint): string
    {
        $endpoint = trim($endpoint);
        $endpoint = preg_replace('#^https?://#i', '', $endpoint) ?? $endpoint;
        $endpoint = rtrim(explode('/', $endpoint)[0], '/');

        return $endpoint !== '' ? $endpoint : 'sms.tencentcloudapi.com';
    }
}
