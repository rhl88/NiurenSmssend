<?php

namespace App\Apps\NiurenSmssend\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 短信发送 · 模板表单字段模型
 *
 * 存储模板内容中每个 ${变量} 对应的表单控件配置。
 * 同一变量（var_name）允许配置多条记录（多个控件），
 * 发送时按 sort 顺序把各控件取值拼接为该变量的最终值。
 * 遵循《CmsPro-v5-应用开发文档》第八章模型规范。
 */
class TemplateField extends BaseModel
{
    /**
     * 控件类型：单行文本
     */
    public const TYPE_TEXT = 'text';

    /**
     * 控件类型：多行文本
     */
    public const TYPE_TEXTAREA = 'textarea';

    /**
     * 控件类型：单选按钮
     */
    public const TYPE_RADIO = 'radio';

    /**
     * 控件类型：单选下拉
     */
    public const TYPE_SELECT = 'select';

    /**
     * 控件类型：数字
     */
    public const TYPE_NUMBER = 'number';

    /**
     * 控件类型：日期
     */
    public const TYPE_DATE = 'date';

    /**
     * 控件类型：多选
     */
    public const TYPE_CHECKBOX = 'checkbox';

    /**
     * 控件类型：手机号
     */
    public const TYPE_MOBILE = 'mobile';

    /**
     * 数据表名（应用前缀 app_niuren_smssend_）
     */
    protected $table = 'app_niuren_smssend_template_fields';

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
        'template_id',
        'var_name',
        'label',
        'field_type',
        'options',
        'default_value',
        'placeholder',
        'is_required',
        'sort',
        'status',
    ];

    /**
     * 字段类型转换
     */
    protected $casts = [
        'options' => 'array',
        'is_required' => 'integer',
        'sort' => 'integer',
        'status' => 'integer',
        'create_time' => 'datetime:Y-m-d H:i:s',
        'update_time' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * 关联：所属模板
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(SmsTemplate::class, 'template_id');
    }

    /**
     * 作用域：仅启用字段
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('status', 1);
    }

    /**
     * 可用控件类型映射（type => 名称）
     *
     * @return array<string,string>
     */
    public static function typeMap(): array
    {
        return [
            self::TYPE_TEXT => '单行文本',
            self::TYPE_TEXTAREA => '多行文本',
            self::TYPE_RADIO => '单选',
            self::TYPE_SELECT => '单选下拉',
            self::TYPE_NUMBER => '数字',
            self::TYPE_DATE => '日期',
            self::TYPE_CHECKBOX => '多选',
            self::TYPE_MOBILE => '手机号',
        ];
    }

    /**
     * 是否为需要选项配置的控件类型（单选/下拉/多选）
     */
    public function hasOptions(): bool
    {
        return in_array($this->field_type, [
            self::TYPE_RADIO,
            self::TYPE_SELECT,
            self::TYPE_CHECKBOX,
        ], true);
    }

    /**
     * 获取规范化选项列表
     *
     * 每项结构：label-展示文案 value-提交值（缺省取 label）。
     *
     * @return array<int,array{label:string,value:string}>
     */
    public function optionList(): array
    {
        $options = $this->options;

        if (! is_array($options)) {
            return [];
        }

        $normalized = [];
        foreach ($options as $option) {
            if (! is_array($option)) {
                continue;
            }
            $label = trim((string) ($option['label'] ?? ''));
            $value = trim((string) ($option['value'] ?? ''));
            if ($label === '' && $value === '') {
                continue;
            }
            $normalized[] = [
                'label' => $label !== '' ? $label : $value,
                'value' => $value !== '' ? $value : $label,
            ];
        }

        return $normalized;
    }

    /**
     * 表单渲染数组（发送页动态表单用）
     *
     * @return array<string,mixed>
     */
    public function toFormArray(): array
    {
        return [
            'id' => (int) $this->id,
            'var_name' => (string) $this->var_name,
            'label' => (string) $this->label,
            'field_type' => (string) $this->field_type,
            'options' => $this->optionList(),
            'default_value' => (string) $this->default_value,
            'placeholder' => (string) $this->placeholder,
            'is_required' => (int) $this->is_required,
            'sort' => (int) $this->sort,
        ];
    }
}