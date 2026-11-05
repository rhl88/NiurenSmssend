<?php

namespace App\Apps\NiurenSmssend\Providers\Support;

use Illuminate\Http\Client\ConnectionException;
use Throwable;

/**
 * 短信发送 · 请求异常描述工具
 *
 * 把底层 HTTP 请求异常归一化为面向用户的中文描述，供各服务商适配器复用：
 * - 网络类异常（连接超时、域名解析失败、连接被拒等）统一提示网络问题，
 *   并保留 cURL 错误码便于运维排查；
 * - 其余异常仅保留截断后的原始消息，避免超长文本写入发送记录的 error_message 字段。
 *
 * 关键约定：绝不输出完整请求 URL —— 阿里云签名参数（AccessKeyId / Signature / SignatureNonce）
 * 就拼在 URL 里，原样落库并在发送记录页展示会造成敏感信息泄露。
 */
class HttpErrorFormatter
{
    /**
     * 网络类异常的统一提示
     */
    private const NETWORK_MESSAGE = '网络连接异常，请检查服务器网络与短信服务商接口地址';

    /**
     * 非网络类异常描述的最大长度
     */
    private const MESSAGE_MAX_LENGTH = 200;

    /**
     * 生成不含请求地址与敏感参数的异常描述
     */
    public static function describe(Throwable $e): string
    {
        if ($e instanceof ConnectionException) {
            $curlErrorCode = self::extractCurlErrorCode($e->getMessage());

            return $curlErrorCode === null
                ? self::NETWORK_MESSAGE
                : self::NETWORK_MESSAGE . '（cURL error ' . $curlErrorCode . '）';
        }

        return '请求异常：' . self::truncate($e->getMessage());
    }

    /**
     * 从 cURL 异常消息中提取错误码（如 "cURL error 28: ..." 中的 28）
     */
    private static function extractCurlErrorCode(string $message): ?string
    {
        return preg_match('/cURL error (\d+)/', $message, $matches) === 1 ? $matches[1] : null;
    }

    /**
     * 截断过长的异常消息
     */
    private static function truncate(string $message): string
    {
        $message = trim($message);

        return mb_strlen($message) > self::MESSAGE_MAX_LENGTH
            ? mb_substr($message, 0, self::MESSAGE_MAX_LENGTH) . '…'
            : $message;
    }
}
