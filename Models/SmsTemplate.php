<?php

namespace App\Apps\NiurenSmssend\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 短信发送 · 短信模板模型
 *
 * 存储对接服务商的短信模板：模板名称、模板 CODE、所属服务商、
 * 含 ${变量} 占位符的模板内容、可选签名覆盖。
 * 遵循《CmsPro-v5-应用开发文档》第八章模型规范：
 * - 继承 App\Models\BaseModel，日期序列化统一 Y-m-d H:i:s
 * - 显式声明 $table（应用前缀 app_niuren_smssend_）
 * - CREATED_AT / UPDATED_AT 重定义为 create_time / update_time
 */
class SmsTemplate extends BaseModel
{
    /**
     * 服务商：阿里云
     */
    public const PROVIDER_ALIYUN = 'aliyun';

    /**
     * 服务商：腾讯云
     */
    public const PROVIDER_TENCENT = 'tencent';

    /**
     * 服务商：火山引擎
     */
    public const PROVIDER_VOLCANO = 'volcano';

    /**
     * 数据表名（应用前缀 app_niuren_smssend_）
     */
    protected $table = 'app_niuren_smssend_templates';

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
        'name',
        'code',
        'alias',
        'provider',
        'content',
        'sign_name',
        'remark',
        'status',
    ];

    /**
     * 字段类型转换
     */
    protected $casts = [
        'status' => 'integer',
        'create_time' => 'datetime:Y-m-d H:i:s',
        'update_time' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * 关联：模板表单字段（一个模板多个变量控件）
     */
    public function fields(): HasMany
    {
        return $this->hasMany(TemplateField::class, 'template_id')
            ->orderBy('sort')
            ->orderBy('id');
    }

    /**
     * 作用域：仅启用模板
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('status', 1);
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
     * 作用域：按关键字模糊搜索（模板名称 / 模板 CODE / 模板标识）
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
            $sub->where('name', 'like', '%' . $keyword . '%')
                ->orWhere('code', 'like', '%' . $keyword . '%')
                ->orWhere('alias', 'like', '%' . $keyword . '%');
        });
    }

    /**
     * 提取模板内容中的变量名列表（按首次出现顺序，去重）
     *
     * 支持 ${变量名} 格式，变量名限定字母/数字/下划线，首字符非数字。
     *
     * @return array<int,string> 变量名列表（不含 ${}）
     */
    public function extractVariables(): array
    {
        $content = (string) $this->content;

        if (! preg_match_all('/\$\{([A-Za-z_][A-Za-z0-9_]*)\}/', $content, $matches)) {
            return [];
        }

        $variables = [];
        foreach ($matches[1] as $name) {
            if (! in_array($name, $variables, true)) {
                $variables[] = $name;
            }
        }

        return $variables;
    }

    /**
     * 可用服务商映射（code => 名称）
     *
     * @return array<string,string>
     */
    public static function providerMap(): array
    {
        return [
            self::PROVIDER_ALIYUN => '阿里云',
            self::PROVIDER_TENCENT => '腾讯云',
            self::PROVIDER_VOLCANO => '火山引擎',
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
            'name' => (string) $this->name,
            'code' => (string) $this->code,
            'alias' => (string) ($this->alias ?? ''),
            'provider' => (string) $this->provider,
            'provider_text' => self::providerMap()[$this->provider] ?? $this->provider,
            'content' => (string) $this->content,
            'variables' => $this->extractVariables(),
            'sign_name' => (string) $this->sign_name,
            'remark' => (string) $this->remark,
            'status' => (int) $this->status,
            'create_time' => $this->create_time?->format('Y-m-d H:i:s') ?? '',
            'update_time' => $this->update_time?->format('Y-m-d H:i:s') ?? '',
        ];
    }
}
