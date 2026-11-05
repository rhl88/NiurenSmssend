<?php

namespace App\Apps\NiurenSmssend\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;

/**
 * 短信发送 · 发送记录模型
 *
 * 记录每次短信发送的服务商、模板、手机号、参数、渲染后内容、
 * 服务商返回结果与错误信息。
 * 遵循《CmsPro-v5-应用开发文档》第八章模型规范。
 */
class SmsRecord extends BaseModel
{
    /**
     * 发送状态：待发送
     */
    public const STATUS_PENDING = 'pending';

    /**
     * 发送状态：成功
     */
    public const STATUS_SUCCESS = 'success';

    /**
     * 发送状态：失败
     */
    public const STATUS_FAILED = 'failed';

    /**
     * 数据表名（应用前缀 app_niuren_smssend_）
     */
    protected $table = 'app_niuren_smssend_records';

    /**
     * 创建时间字段名（系统统一规范）
     */
    public const CREATED_AT = 'create_time';

    /**
     * 更新时间字段名（系统统一规范）
     */
    public const UPDATED_AT = 'update_time';

    /**
     * 可批量赋值字段
     */
    protected $fillable = [
        'provider',
        'template_id',
        'template_code',
        'template_name',
        'mobile',
        'sign_name',
        'params',
        'content',
        'vendor_msg_id',
        'biz_id',
        'send_status',
        'error_code',
        'error_message',
        'response',
        'request_info',
        'operator_id',
        'operator_name',
        'status',
    ];

    /**
     * 字段类型转换
     */
    protected $casts = [
        'params' => 'array',
        'response' => 'array',
        'request_info' => 'array',
        'template_id' => 'integer',
        'operator_id' => 'integer',
        'status' => 'integer',
        'create_time' => 'datetime:Y-m-d H:i:s',
        'update_time' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * 作用域：按发送状态筛选
     *
     * @param string|null $status pending/success/failed，空则不筛
     */
    public function scopeOfStatus(Builder $query, ?string $status): Builder
    {
        $status = trim((string) $status);
        if ($status === '') {
            return $query;
        }

        return $query->where('send_status', $status);
    }

    /**
     * 作用域：按服务商筛选
     *
     * @param string|null $provider aliyun/tencent/volcano，空则不筛
     */
    public function scopeOfProvider(Builder $query, ?string $provider): Builder
    {
        $provider = trim((string) $provider);
        if ($provider === '') {
            return $query;
        }

        return $query->where('provider', $provider);
    }

    /**
     * 作用域：按关键字模糊搜索（手机号 / 模板名称 / 模板 CODE）
     *
     * @param string|null $keyword 搜索关键词，为空不附加条件
     */
    public function scopeKeyword(Builder $query, ?string $keyword): Builder
    {
        $keyword = trim((string) $keyword);
        if ($keyword === '') {
            return $query;
        }

        return $query->where(function (Builder $sub) use ($keyword) {
            $sub->where('mobile', 'like', '%' . $keyword . '%')
                ->orWhere('template_name', 'like', '%' . $keyword . '%')
                ->orWhere('template_code', 'like', '%' . $keyword . '%');
        });
    }

    /**
     * 可用状态映射（status => 名称）
     *
     * @return array<string,string>
     */
    public static function statusMap(): array
    {
        return [
            self::STATUS_PENDING => '待发送',
            self::STATUS_SUCCESS => '发送成功',
            self::STATUS_FAILED => '发送失败',
        ];
    }

    /**
     * 汇总展示数组（列表用）
     *
     * @return array<string,mixed>
     */
    public function toSummaryArray(): array
    {
        return [
            'id' => (int) $this->id,
            'provider' => (string) $this->provider,
            'provider_text' => SmsTemplate::providerMap()[$this->provider] ?? $this->provider,
            'template_id' => (int) $this->template_id,
            'template_code' => (string) $this->template_code,
            'template_name' => (string) $this->template_name,
            'mobile' => (string) $this->mobile,
            'sign_name' => (string) $this->sign_name,
            'content' => (string) ($this->content ?? ''),
            'vendor_msg_id' => (string) ($this->vendor_msg_id ?? ''),
            'send_status' => (string) $this->send_status,
            'send_status_text' => self::statusMap()[$this->send_status] ?? $this->send_status,
            'error_message' => (string) $this->error_message,
            'operator_name' => (string) $this->operator_name,
            'create_time' => $this->create_time?->format('Y-m-d H:i:s') ?? '',
        ];
    }

    /**
     * 详情展示数组（详情弹窗用，含参数与原始响应）
     *
     * @return array<string,mixed>
     */
    public function toDetailArray(): array
    {
        return array_merge($this->toSummaryArray(), [
            'params' => is_array($this->params) ? $this->params : [],
            'biz_id' => (string) ($this->biz_id ?? ''),
            'error_code' => (string) $this->error_code,
            'response' => is_array($this->response) ? $this->response : [],
            'request_info' => is_array($this->request_info) ? $this->request_info : [],
            'operator_id' => (int) $this->operator_id,
            'update_time' => $this->update_time?->format('Y-m-d H:i:s') ?? '',
        ]);
    }
}