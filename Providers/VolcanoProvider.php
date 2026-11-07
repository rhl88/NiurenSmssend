<?php

namespace App\Apps\NiurenSmssend\Providers;

use App\Apps\NiurenSmssend\Contracts\SmsProviderInterface;
use App\Apps\NiurenSmssend\Exceptions\SmsException;
use App\Apps\NiurenSmssend\Providers\Support\HttpErrorFormatter;
use App\Apps\NiurenSmssend\Providers\Support\SslVerifyOptions;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * 短信发送 · 火山引擎短信适配器
 *
 * 对接火山引擎短信 SendSms 接口（POST JSON，接口版本 2020-01-01）：
 * - 域名默认 sms.volcengineapi.com，服务名 volcSMS，地域默认 cn-north-1
 * - 签名算法 HMAC-SHA256（火山引擎 V4 签名），派生密钥逐级以 hex 字符串传递，
 *   与 AWS / 腾讯云 TC3 的二进制密钥派生不同
 * - 模板变量通过 TemplateParam 传递，值为 JSON 字符串（键名须与模板 ${xxx} 占位符
 *   一致），固定模板（无变量）省略该字段
 * - 响应 Result.MessageID 为数组，取首条作为服务商消息 ID；ResponseMetadata.RequestId
 *   作为请求 ID 落库；业务错误统一放在 ResponseMetadata.Error（HTTP 状态码恒为 200）
 *
 * 配置读取优先级：数据库 config_items（后台设置页）→ Config/smssend.php 兜底。
 */
class VolcanoProvider implements SmsProviderInterface
{
    /**
     * 火山引擎短信接口版本
     */
    private const API_VERSION = '2020-01-01';

    /**
     * 火山引擎短信服务名（参与签名派生）
     */
    private const SERVICE = 'volcSMS';

    /**
     * 火山引擎接口动作
     */
    private const ACTION = 'SendSms';

    /**
     * 签名算法标识
     */
    private const ALGORITHM = 'HMAC-SHA256';

    /**
     * 请求内容类型
     */
    private const CONTENT_TYPE = 'application/json';

    /**
     * 参与签名的请求头（小写、分号分隔，官方示例 content-type 不参与签名）
     */
    private const SIGNED_HEADERS = 'host;x-date';

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
        'RE:0000' => '火山引擎鉴权失败，请检查 AccessKey / SecretKey 是否正确',
        'RE:0001' => '火山引擎短信服务未开通，请先在控制台开通短信服务',
        'RE:0002' => '火山引擎短信服务已被关停，请联系火山引擎处理',
        'RE:0003' => '火山引擎短信账号（SmsAccount）不存在，请核对消息组 ID',
        'RE:0004' => '短信签名不正确或未审核通过，请核对签名内容',
        'RE:0005' => '短信模板不正确或未审核通过，请核对模板 ID',
        'RE:0006' => '手机号码格式不正确',
        'RE:0007' => '请求 IP 不在火山引擎白名单内，请在控制台配置 IP 白名单',
        'RE:0009' => '请求参数错误，请核对模板参数格式',
        'RE:0010' => '火山引擎账号欠费，请及时充值',
        'RE:0011' => '该地区暂不支持火山引擎短信服务',
        'RE:0012' => '发送类型不支持，请核对短信类型配置',
        'RE:0013' => '发送量超过限制，请稍后再试',
        'RE:0500' => '火山引擎服务内部错误，请稍后重试',
    ];

    /**
     * AccessKey（明文）
     */
    private string $accessKey;

    /**
     * SecretKey（明文）
     */
    private string $secretKey;

    /**
     * 短信账号（火山引擎短信服务的消息组 ID）
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
        $this->endpoint = $this->normalizeEndpoint((string) niuren_smssend_config('volcano_endpoint', 'sms.volcengineapi.com'));
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
     * @throws SmsException
     */
    public function send(string $mobile, string $signName, string $templateCode, array $params): array
    {
        if (! $this->isConfigured()) {
            throw new SmsException($this->missingConfigMessage());
        }

        $signName = trim($signName) !== '' ? trim($signName) : $this->defaultSignName;
        if ($signName === '') {
            throw new SmsException('火山引擎短信签名缺失，请在「短信设置」或模板中配置签名');
        }

        $templateCode = trim($templateCode);
        if ($templateCode === '') {
            throw new SmsException('短信模板 ID 为空，请先完善模板配置');
        }

        $businessParams = [
            'SmsAccount' => $this->smsAccount,
            'Sign' => $signName,
            'TemplateID' => $templateCode,
            'PhoneNumbers' => trim($mobile),
        ];
        if ($params !== []) {
            $businessParams['TemplateParam'] = json_encode($params, JSON_UNESCAPED_UNICODE);
        }

        $payload = json_encode($businessParams, JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            throw new SmsException('火山引擎短信请求参数序列化失败');
        }

        try {
            $result = $this->request($payload);
        } catch (SmsException $e) {
            throw $e->withRequestInfo($this->buildRequestInfo($businessParams));
        }

        $vendorMessageId = (string) ($result['Result']['MessageID'][0] ?? '');
        if ($vendorMessageId === '') {
            throw $this->buildError($result)->withRequestInfo($this->buildRequestInfo($businessParams));
        }

        return [
            'vendor_msg_id' => $vendorMessageId,
            'biz_id' => (string) ($result['ResponseMetadata']['RequestId'] ?? ''),
            'response' => $result,
            'request_info' => $this->buildRequestInfo($businessParams),
        ];
    }

    /**
     * 组装接口信息（发送记录排查用）
     *
     * 仅记录端点地址与请求体参数；鉴权信息在请求头
     * （Authorization V4 签名），不落库展示。
     *
     * @param array<string,mixed> $businessParams
     * @return array{url:string,method:string,params:array<string,mixed>}
     */
    private function buildRequestInfo(array $businessParams): array
    {
        return [
            'url' => $this->buildUrl(),
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
                'Content-Type' => self::CONTENT_TYPE,
                'X-Date' => gmdate('Ymd\THis\Z', $timestamp),
                'Authorization' => $authorization,
            ])
                ->withOptions(SslVerifyOptions::build())
                ->timeout(self::REQUEST_TIMEOUT)
                ->connectTimeout(self::CONNECT_TIMEOUT)
                ->withBody($payload, self::CONTENT_TYPE)
                ->post($this->buildUrl());
        } catch (Throwable $e) {
            throw new SmsException('请求火山引擎短信服务失败：' . HttpErrorFormatter::describe($e));
        }

        $result = $response->json();

        if (! is_array($result)) {
            throw new SmsException('火山引擎短信服务返回异常（HTTP ' . $response->status() . '）');
        }

        if (isset($result['ResponseMetadata']['Error'])) {
            throw $this->buildError($result);
        }

        return $result;
    }

    /**
     * 构造火山引擎 V4 签名授权头（HMAC-SHA256）
     *
     * 派生密钥逐级以 hex 字符串传递（火山引擎特有，区别于 AWS 的二进制密钥）：
     * kDate → kRegion → kService → kSigning。
     */
    private function buildAuthorization(string $payload, int $timestamp): string
    {
        $xDate = gmdate('Ymd\THis\Z', $timestamp);
        $shortDate = gmdate('Ymd', $timestamp);

        $canonicalHeaders = 'host:' . $this->endpoint . "\n"
            . 'x-date:' . $xDate . "\n";

        $canonicalRequest = implode("\n", [
            'POST',
            '/',
            'Action=' . self::ACTION . '&Version=' . self::API_VERSION,
            $canonicalHeaders,
            self::SIGNED_HEADERS,
            hash('sha256', $payload),
        ]);

        $credentialScope = $shortDate . '/' . $this->region . '/' . self::SERVICE . '/request';

        $stringToSign = implode("\n", [
            self::ALGORITHM,
            $xDate,
            $credentialScope,
            hash('sha256', $canonicalRequest),
        ]);

        $kDate = hash_hmac('sha256', $shortDate, $this->secretKey);
        $kRegion = hash_hmac('sha256', $this->region, $kDate);
        $kService = hash_hmac('sha256', self::SERVICE, $kRegion);
        $kSigning = hash_hmac('sha256', 'request', $kService);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        return self::ALGORITHM
            . ' Credential=' . $this->accessKey . '/' . $credentialScope
            . ', SignedHeaders=' . self::SIGNED_HEADERS
            . ', Signature=' . $signature;
    }

    /**
     * 构造接口请求地址（Action 与 Version 走查询串）
     */
    private function buildUrl(): string
    {
        return 'https://' . $this->endpoint . '/?Action=' . self::ACTION . '&Version=' . self::API_VERSION;
    }

    /**
     * 从失败响应构造业务异常
     *
     * @param array<string,mixed> $result 响应整体
     */
    private function buildError(array $result): SmsException
    {
        $error = $result['ResponseMetadata']['Error'] ?? null;

        if (is_array($error)) {
            $code = (string) ($error['Code'] ?? '');
            $rawMessage = (string) ($error['Message'] ?? '');

            if (isset(self::ERROR_MESSAGES[$code])) {
                return new SmsException(self::ERROR_MESSAGES[$code]);
            }

            return new SmsException('火山引擎短信发送失败：' . trim($code . ' ' . $rawMessage));
        }

        return new SmsException('火山引擎短信发送失败：接口未返回消息 ID');
    }

    /**
     * 规范化域名：剥离协议与斜杠，仅保留 host；为空回退默认域名
     */
    private function normalizeEndpoint(string $endpoint): string
    {
        $endpoint = trim($endpoint);
        $endpoint = preg_replace('#^https?://#i', '', $endpoint) ?? $endpoint;
        $endpoint = rtrim($endpoint, '/');

        return $endpoint !== '' ? $endpoint : 'sms.volcengineapi.com';
    }
}
