<?php

namespace App\Apps\NiurenSmssend\Contracts;

use App\Apps\NiurenSmssend\Exceptions\SmsException;

/**
 * 短信发送 · 服务商适配契约
 *
 * 所有短信服务商（阿里云 / 腾讯云 / 火山引擎）适配器必须实现本接口，
 * 由 Providers\ProviderFactory 统一实例化，发送服务仅依赖本抽象，
 * 新增服务商只需新增一个实现类并在工厂中注册即可。
 */
interface SmsProviderInterface
{
    /**
     * 服务商标识（与模板 provider 字段一致）
     *
     * @return string aliyun / tencent / volcano
     */
    public function code(): string;

    /**
     * 服务商中文名称（用于展示与报错文案）
     */
    public function name(): string;

    /**
     * 是否已完成必要的对接配置（密钥、应用 ID 等）
     */
    public function isConfigured(): bool;

    /**
     * 未完成配置时给出的引导文案
     */
    public function missingConfigMessage(): string;

    /**
     * 发送单条短信
     *
     * @param string               $mobile       手机号码（不含国家码，适配器自行归一化）
     * @param string               $signName     短信签名（模板级覆盖，空则由适配器取默认签名）
     * @param string               $templateCode 服务商侧模板标识（阿里云模板 CODE / 腾讯云模板 ID）
     * @param array<string,string> $params       模板变量键值对，键为变量名（不含 ${}）
     * @return array{vendor_msg_id:string,biz_id:string,response:array<string,mixed>}
     *         发送成功返回服务商回执信息
     *
     * @throws SmsException 配置缺失、参数非法或服务商返回失败时抛出
     */
    public function send(string $mobile, string $signName, string $templateCode, array $params): array;
}