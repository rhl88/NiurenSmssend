<?php

namespace App\Apps\NiurenSmssend\Services;

use App\Apps\NiurenSmssend\Exceptions\SmsException;
use App\Apps\NiurenSmssend\Models\SmsTemplate;
use App\Apps\NiurenSmssend\Models\TemplateField;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * 短信发送 · 短信模板服务
 *
 * 负责模板及其表单字段的增删改查：
 * - 模板字段与模板内容中的 ${变量} 强绑定，保存时校验变量必须存在
 * - 同一变量允许配置多个控件，按数组顺序（写入 sort）决定发送时的拼接顺序
 * - 模板与其字段的重建在同一事务内完成，避免出现半成品数据
 */
class TemplateService
{
    /**
     * 分页查询模板列表
     *
     * @param string|null $keyword  关键字（模板名称 / 模板 CODE）
     * @param string|null $provider 服务商筛选
     * @param int         $pageSize 每页条数
     */
    public function paginate(?string $keyword, ?string $provider, int $pageSize = 15): LengthAwarePaginator
    {
        return SmsTemplate::query()
            ->keyword($keyword)
            ->ofProvider($provider)
            ->orderBy('id', 'desc')
            ->paginate($pageSize);
    }

    /**
     * 启用状态的模板精简列表（发送页下拉用）
     *
     * @param string|null $provider 服务商筛选，空则不限
     * @return array<int,array{id:int,name:string,code:string,alias:string,provider:string}>
     */
    public function options(?string $provider = null): array
    {
        return SmsTemplate::query()
            ->enabled()
            ->ofProvider($provider)
            ->orderBy('id', 'desc')
            ->get(['id', 'name', 'code', 'alias', 'provider'])
            ->map(fn (SmsTemplate $template) => [
                'id' => (int) $template->id,
                'name' => (string) $template->name,
                'code' => (string) $template->code,
                'alias' => (string) ($template->alias ?? ''),
                'provider' => (string) $template->provider,
            ])
            ->all();
    }

    /**
     * 获取模板（含字段），不存在则抛出业务异常
     *
     * @throws SmsException
     */
    public function find(int $id): SmsTemplate
    {
        $template = SmsTemplate::with('fields')->find($id);

        if (! $template) {
            throw new SmsException('短信模板不存在或已被删除');
        }

        return $template;
    }

    /**
     * 模板编辑回显数据（含字段表单结构）
     *
     * @return array<string,mixed>
     *
     * @throws SmsException
     */
    public function form(int $id): array
    {
        $template = $this->find($id);

        return [
            'id' => (int) $template->id,
            'name' => (string) $template->name,
            'code' => (string) $template->code,
            'alias' => (string) ($template->alias ?? ''),
            'provider' => (string) $template->provider,
            'content' => (string) $template->content,
            'sign_name' => (string) $template->sign_name,
            'remark' => (string) $template->remark,
            'status' => (int) $template->status,
            'variables' => $template->extractVariables(),
            'fields' => $template->fields
                ->map(fn (TemplateField $field) => $field->toFormArray())
                ->values()
                ->all(),
        ];
    }

    /**
     * 创建模板及其表单字段
     *
     * @param array<string,mixed>  $data   模板主体数据
     * @param array<int,array<string,mixed>> $fields 表单字段列表
     *
     * @throws SmsException
     */
    public function create(array $data, array $fields): SmsTemplate
    {
        return DB::transaction(function () use ($data, $fields) {
            $payload = $this->normalizeTemplateData($data);
            $this->assertCodeUnique($payload['provider'], $payload['code']);
            if ($payload['alias'] !== null) {
                $this->assertAliasUnique($payload['alias']);
            }

            $template = SmsTemplate::create($payload);
            $this->syncFields($template, $fields);

            return $template->refresh()->load('fields');
        });
    }

    /**
     * 更新模板及其表单字段（字段整体重建）
     *
     * @param array<string,mixed>  $data
     * @param array<int,array<string,mixed>> $fields
     *
     * @throws SmsException
     */
    public function update(int $id, array $data, array $fields): SmsTemplate
    {
        return DB::transaction(function () use ($id, $data, $fields) {
            $template = $this->find($id);
            $payload = $this->normalizeTemplateData($data);
            $this->assertCodeUnique($payload['provider'], $payload['code'], (int) $template->id);
            if ($payload['alias'] !== null) {
                $this->assertAliasUnique($payload['alias'], (int) $template->id);
            }

            $template->update($payload);
            $this->syncFields($template, $fields);

            return $template->refresh()->load('fields');
        });
    }

    /**
     * 删除模板及其表单字段
     *
     * @throws SmsException
     */
    public function delete(int $id): void
    {
        DB::transaction(function () use ($id) {
            $template = $this->find($id);
            $template->fields()->delete();
            $template->delete();
        });
    }

    // ──────────────────────────────────────────────────────────────
    // 内部工具
    // ──────────────────────────────────────────────────────────────

    /**
     * 校验并规范化模板主体数据
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     *
     * @throws SmsException
     */
    private function normalizeTemplateData(array $data): array
    {
        $provider = trim((string) ($data['provider'] ?? ''));
        if (! array_key_exists($provider, SmsTemplate::providerMap())) {
            throw new SmsException('请选择有效的短信服务商');
        }

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new SmsException('请填写模板名称');
        }

        $code = trim((string) ($data['code'] ?? ''));
        if ($code === '') {
            throw new SmsException('请填写模板 CODE');
        }

        $alias = trim((string) ($data['alias'] ?? ''));
        if ($alias !== '' && ! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{1,99}$/', $alias)) {
            throw new SmsException('模板标识仅支持字母、数字、点、中划线、下划线，须以字母或数字开头');
        }

        $content = (string) ($data['content'] ?? '');
        if (trim($content) === '') {
            throw new SmsException('请填写模板内容');
        }

        return [
            'name' => $name,
            'code' => $code,
            'alias' => $alias !== '' ? $alias : null,
            'provider' => $provider,
            'content' => $content,
            'sign_name' => trim((string) ($data['sign_name'] ?? '')),
            'remark' => trim((string) ($data['remark'] ?? '')),
            'status' => (int) ($data['status'] ?? 1) === 1 ? 1 : 0,
        ];
    }

    /**
     * 校验同一服务商下模板 CODE 唯一
     *
     * @throws SmsException
     */
    private function assertCodeUnique(string $provider, string $code, ?int $excludeId = null): void
    {
        $query = SmsTemplate::query()
            ->where('provider', $provider)
            ->where('code', $code);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw new SmsException('该服务商下已存在相同的模板 CODE');
        }
    }

    /**
     * 校验模板标识全局唯一（跨服务商）
     *
     * @throws SmsException
     */
    private function assertAliasUnique(string $alias, ?int $excludeId = null): void
    {
        $query = SmsTemplate::query()->where('alias', $alias);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw new SmsException('模板标识已被使用');
        }
    }

    /**
     * 按应用级模板标识查询模板（供其他应用按标识调用）
     *
     * 停用状态不做拦截，统一由 SendService::send() 校验。
     *
     * @throws SmsException
     */
    public function findByAlias(string $alias): SmsTemplate
    {
        $template = SmsTemplate::query()->where('alias', trim($alias))->first();

        if (! $template) {
            throw new SmsException('短信模板不存在或已被删除');
        }

        return $template;
    }

    /**
     * 重建模板的表单字段
     *
     * @param array<int,array<string,mixed>> $fields
     *
     * @throws SmsException
     */
    private function syncFields(SmsTemplate $template, array $fields): void
    {
        $variables = $template->extractVariables();
        $normalized = [];
        $sort = 0;

        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $varName = trim((string) ($field['var_name'] ?? ''));
            if ($varName === '') {
                continue;
            }

            $label = trim((string) ($field['label'] ?? ''));
            $displayName = $label !== '' ? $label : $varName;

            if (! in_array($varName, $variables, true)) {
                throw new SmsException('字段「' . $displayName . '」的变量 ${' . $varName . '} 不存在于模板内容中');
            }

            $type = trim((string) ($field['field_type'] ?? ''));
            if (! array_key_exists($type, TemplateField::typeMap())) {
                throw new SmsException('字段「' . $displayName . '」的控件类型无效');
            }

            if ($label === '') {
                throw new SmsException('变量 ${' . $varName . '} 的字段名称不能为空');
            }

            $normalized[] = [
                'var_name' => $varName,
                'label' => $label,
                'field_type' => $type,
                'options' => $this->normalizeOptions($type, $field['options'] ?? []),
                'default_value' => trim((string) ($field['default_value'] ?? '')),
                'placeholder' => trim((string) ($field['placeholder'] ?? '')),
                'is_required' => (int) ($field['is_required'] ?? 0) === 1 ? 1 : 0,
                'sort' => $sort++,
                'status' => 1,
            ];
        }

        $template->fields()->delete();

        foreach ($normalized as $row) {
            TemplateField::create(array_merge($row, ['template_id' => (int) $template->id]));
        }
    }

    /**
     * 规范化选项配置（仅选项类控件需要）
     *
     * @return array<int,array{label:string,value:string}>|null
     *
     * @throws SmsException
     */
    private function normalizeOptions(string $type, mixed $options): ?array
    {
        if (! in_array($type, [
            TemplateField::TYPE_RADIO,
            TemplateField::TYPE_SELECT,
            TemplateField::TYPE_CHECKBOX,
        ], true)) {
            return null;
        }

        if (is_string($options)) {
            $decoded = json_decode($options, true);
            $options = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($options)) {
            $options = [];
        }

        $result = [];
        foreach ($options as $option) {
            if (is_string($option)) {
                $option = ['label' => $option, 'value' => $option];
            }
            if (! is_array($option)) {
                continue;
            }

            $label = trim((string) ($option['label'] ?? ''));
            $value = trim((string) ($option['value'] ?? ''));
            if ($label === '' && $value === '') {
                continue;
            }

            $result[] = [
                'label' => $label !== '' ? $label : $value,
                'value' => $value !== '' ? $value : $label,
            ];
        }

        if (empty($result)) {
            throw new SmsException('选项类字段至少需要配置一个选项');
        }

        return $result;
    }
}