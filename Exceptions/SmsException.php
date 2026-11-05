<?php

namespace App\Apps\NiurenSmssend\Exceptions;

use RuntimeException;

/**
 * 短信发送 · 业务异常
 *
 * 用于承载「面向用户可读」的业务错误（配置缺失、模板非法、
 * 服务商接口返回失败等）。控制器捕获后统一转成 ApiResponse::error()，
 * 因此异常 message 必须是可以直接展示给运营人员的中文文案。
 */
class SmsException extends RuntimeException
{
    /**
     * 发起请求时的接口信息（url/method/params），供失败记录落库排查
     */
    private ?array $requestInfo = null;

    /**
     * 附加接口信息（流式调用）
     */
    public function withRequestInfo(?array $requestInfo): self
    {
        $this->requestInfo = $requestInfo;

        return $this;
    }

    /**
     * 获取接口信息，未携带时返回 null
     */
    public function getRequestInfo(): ?array
    {
        return $this->requestInfo;
    }
}