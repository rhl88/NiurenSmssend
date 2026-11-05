<?php

namespace App\Apps\NiurenSmssend\Providers;

use App\Apps\NiurenSmssend\Contracts\SmsProviderInterface;
use App\Apps\NiurenSmssend\Exceptions\SmsException;
use App\Apps\NiurenSmssend\Providers\Support\HttpErrorFormatter;
use App\Apps\NiurenSmssend\Providers\Support\SslVerifyOptions;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * 短信发送 · 阿里云短信适配器
 *
 * 对接阿里云短信服务 SendSms 接口（RPC 风格，GET 请求）：
 * - 接口版本 2017-05-25，域名默认 dysmsapi.aliyuncs.com
 * - 签名算法 HMAC-SHA1：按参数名 ASCII 升序构造规范化查询串后计算
 * - 变量以 JSON 字符串通过 TemplateParam 传递（URL/JSON 双重编码交由签名统一处理）
 *
 * 配置读取优先级：数据库 config_items（后台设置页）→ Config/smssend.php 兜底。
 */
class AliyunProvider implements SmsProviderInterface
{
    /**
     * 阿里云短信接口版本
     */
    private const API_VERSION = '2017-05-25';

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
        'isv.SMS_SIGNATURE_ILLEGAL' => '短信签名不合法，请检查签名是否已审核通过',
        'isv.SMS_SIGNATURE_SCENE_ILLEGAL' => '短信签名场景不合法，请检查签名适用场景',
        'isv.SMS_TEMPLATE_ILLEGAL' => '短信模板不存在或未审核通过，请核对模板 CODE',
        'isv.TEMPLATE_PARAMS_ILLEGAL' => '模板参数不合法，请核对变量内容',
        'isv.MOBILE_NUMBER_ILLEGAL' => '手机号码格式不正确',
        'isv.AMOUNT_NOT_ENOUGH' => '阿里云账户余额不足，请及时充值',
        'isv.BUSINESS_LIMIT_CONTROL' => '触发流控限制，请稍后再试',
        'isv.DAY_LIMIT_CONTROL' => '触发日发送量限制',
        'isv.MONTH_LIMIT_CONTROL' => '触发月发送量限制',
        'isv.SMS_CONTENT_ILLEGAL' => '短信内容包含违规信息，发送被拦截',
        'isv.PRODUCT_UNSUBSCRIBE' => '阿里云短信产品未开通或已退订',
        'isp.RAM_PERMISSION_DENY' => 'AccessKey 权限不足，请在 RAM 中授予短信发送权限',
        'isp.SYSTEM_ERROR' => '阿里云内部系统错误，请稍后重试',
    ];

    /**
     * AccessKeyId（明文）
     */
    private string $accessKeyId;

    /**
     * AccessKeySecret（明文）
     */
    private string $accessKeySecret;

    /**
     * 默认短信签名
     */
    private string $defaultSignName;

    /**
     * 地域 ID
     */
    private string $regionId;

    /**
     * 接口域名（不含协议）
     */
    private string $endpoint;

    public function __construct()
    {
        $this->accessKeyId = trim((string) niuren_smssend_config('aliyun_access_key_id', ''));
        $this->accessKeySecret = trim((string) niuren_smssend_config('aliyun_access_key_secret', ''));
        $this->defaultSignName = trim((string) niuren_smssend_config('aliyun_sign_name', ''));
        $this->regionId = trim((string) niuren_smssend_config('aliyun_region_id', 'cn-hangzhou'));
        $this->endpoint = $this->normalizeEndpoint((string) niuren_smssend_config('aliyun_endpoint', 'dysmsapi.aliyuncs.com'));
    }

    public function code(): string
    {
        return 'aliyun';
    }

    public function name(): string
    {
        return '阿里云';
    }

    public function isConfigured(): bool
    {
        return $this->accessKeyId !== '' && $this->accessKeySecret !== '';
    }

    public function missingConfigMessage(): string
    {
        return '阿里云短信未完成配置，请在「短信设置」中填写 AccessKeyId 与 AccessKeySecret';
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
            throw new SmsException('阿里云短信签名未配置，请在模板或「短信设置」中填写签名');
        }

        if (trim($templateCode) === '') {
            throw new SmsException('短信模板 CODE 为空，请先完善模板配置');
        }

        $businessParams = [
            'PhoneNumbers' => $mobile,
            'SignName' => $signName,
            'TemplateCode' => $templateCode,
            'TemplateParam' => json_encode($params, JSON_UNESCAPED_UNICODE),
        ];

        if ($this->regionId !== '') {
            $businessParams['RegionId'] = $this->regionId;
        }

        try {
            $payload = $this->request($businessParams);
        } catch (SmsException $e) {
            throw $e->withRequestInfo($this->buildRequestInfo($businessParams));
        }

        if (($payload['Code'] ?? '') !== 'OK') {
            throw $this->buildError($payload)->withRequestInfo($this->buildRequestInfo($businessParams));
        }

        return [
            'vendor_msg_id' => (string) ($payload['BizId'] ?? ''),
            'biz_id' => (string) ($payload['RequestId'] ?? ''),
            'response' => $payload,
            'request_info' => $this->buildRequestInfo($businessParams),
        ];
    }

    /**
     * 组装接口信息（发送记录排查用）
     *
     * 仅记录端点地址与业务参数；完整请求 URL 中的查询串含
     * AccessKeyId / Signature 等鉴权参数，不落库展示。
     *
     * @param array<string,string> $businessParams
     * @return array{url:string,method:string,params:array<string,string>}
     */
    private function buildRequestInfo(array $businessParams): array
    {
        return [
            'url' => 'https://' . $this->endpoint . '/',
            'method' => 'GET',
            'params' => $businessParams,
        ];
    }

    /**
     * 发起已签名请求并返回解码后的响应数组
     *
     * @param array<string,string> $businessParams 业务参数（不含公共参数与签名）
     * @return array<string,mixed>
     *
     * @throws SmsException
     */
    private function request(array $businessParams): array
    {
        $url = 'https://' . $this->endpoint . '/?' . $this->buildSignedQuery($businessParams);

        try {
            $response = Http::withOptions(SslVerifyOptions::build())
                ->timeout(self::REQUEST_TIMEOUT)
                ->connectTimeout(self::CONNECT_TIMEOUT)
                ->get($url);
        } catch (Throwable $e) {
            throw new SmsException('请求阿里云短信服务失败：' . HttpErrorFormatter::describe($e));
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new SmsException('阿里云短信服务返回异常（HTTP ' . $response->status() . '）');
        }

        return $payload;
    }

    /**
     * 构造含签名的规范化查询串
     *
     * @param array<string,string> $businessParams 业务参数
     */
    private function buildSignedQuery(array $businessParams): string
    {
        $params = array_merge([
            'Format' => 'JSON',
            'Version' => self::API_VERSION,
            'AccessKeyId' => $this->accessKeyId,
            'SignatureMethod' => 'HMAC-SHA1',
            'Timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'SignatureVersion' => '1.0',
            'SignatureNonce' => bin2hex(random_bytes(16)),
            'Action' => 'SendSms',
        ], $businessParams);

        $canonicalizedQuery = $this->canonicalizedQuery($params);

        $stringToSign = 'GET'
            . '&' . $this->percentEncode('/')
            . '&' . $this->percentEncode($canonicalizedQuery);

        $params['Signature'] = base64_encode(
            hash_hmac('sha1', $stringToSign, $this->accessKeySecret . '&', true)
        );

        return $this->canonicalizedQuery($params);
    }

    /**
     * 参数按 ASCII 升序拼接为规范化查询串
     *
     * @param array<string,string> $params
     */
    private function canonicalizedQuery(array $params): string
    {
        ksort($params, SORT_STRING);

        $pairs = [];
        foreach ($params as $key => $value) {
            $pairs[] = $this->percentEncode((string) $key) . '=' . $this->percentEncode((string) $value);
        }

        return implode('&', $pairs);
    }

    /**
     * 阿里云签名规范编码：urlencode 后再修正 + * ~ 三个字符
     */
    private function percentEncode(string $value): string
    {
        return str_replace(['+', '*', '%7E'], ['%20', '%2A', '~'], urlencode($value));
    }

    /**
     * 从失败响应构造业务异常
     *
     * @param array<string,mixed> $payload
     */
    private function buildError(array $payload): SmsException
    {
        $code = (string) ($payload['Code'] ?? '');
        $message = self::ERROR_MESSAGES[$code]
            ?? (string) ($payload['Message'] ?? '')
            ?: ('阿里云短信发送失败（错误码 ' . $code . '）');

        return new SmsException($message);
    }

    /**
     * 规范化域名：剥离协议与路径，仅保留 host
     */
    private function normalizeEndpoint(string $endpoint): string
    {
        $endpoint = trim($endpoint);
        $endpoint = preg_replace('#^https?://#i', '', $endpoint) ?? $endpoint;
        $endpoint = rtrim(explode('/', $endpoint)[0], '/');

        return $endpoint !== '' ? $endpoint : 'dysmsapi.aliyuncs.com';
    }
}
