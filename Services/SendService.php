<?php

namespace App\Apps\NiurenSmssend\Services;

use App\Apps\NiurenSmssend\Exceptions\SmsException;
use App\Apps\NiurenSmssend\Models\SmsRecord;
use App\Apps\NiurenSmssend\Models\SmsTemplate;
use App\Apps\NiurenSmssend\Models\TemplateField;
use App\Apps\NiurenSmssend\Providers\ProviderFactory;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 短信发送 · 发送服务
 *
 * 封装一次短信发送的完整流程：
 * 读取模板 → 校验手机号与必填项 → 按变量拼接参数 → 渲染短信正文 →
 * 调服务商接口 → 无论成功失败均落库发送记录。
 *
 * 变量拼接规则（需求核心）：
 * 同一个 ${变量} 可绑定多个表单控件（如 ${name} = 姓名 + 性别），
 * 发送时按控件 sort 顺序把各控件取值直接首尾相接（无分隔符）作为该变量的最终值；
 * 多选控件内部多项之间以顿号「、」连接；一对一绑定即为拼接的特例。
 */
class SendService
{
    /**
     * 失败原因最大长度（与 app_niuren_smssend_records.error_message varchar(500) 对齐）
     */
    private const ERROR_MESSAGE_MAX_LENGTH = 500;

    /**
     * 非业务异常时面向用户的统一提示
     */
    private const UNEXPECTED_FAILURE_MESSAGE = '短信发送失败，请稍后重试';

    public function __construct(private TemplateService $templateService)
    {
    }

    /**
     * 发送短信
     *
     * @param int   $templateId 模板 ID
     * @param string $mobile    接收手机号
     * @param array<int|string,mixed> $fieldValues 表单值，键为字段 ID
     * @param array{id?:int,name?:string} $operator 操作人信息
     * @param string $callerApp 调用方应用标识（其他应用调用时传入；后台页面不传，落空串）
     * @return array<string,mixed> 发送后记录详情
     *
     * @throws SmsException 校验失败或服务商发送失败时（记录已写入 failed 状态）
     */
    public function send(int $templateId, string $mobile, array $fieldValues, array $operator = [], string $callerApp = ''): array
    {
        $template = $this->templateService->find($templateId);

        if ((int) $template->status !== 1) {
            throw new SmsException('该短信模板已停用，无法发送');
        }

        $mobile = $this->normalizeMobile($mobile);
        $params = $this->buildParams($template, $fieldValues);
        $content = $this->renderContent((string) $template->content, $params);

        $record = SmsRecord::create([
            'provider' => (string) $template->provider,
            'template_id' => (int) $template->id,
            'template_code' => (string) $template->code,
            'template_name' => (string) $template->name,
            'mobile' => $mobile,
            'sign_name' => (string) $template->sign_name,
            'params' => $params,
            'content' => $content,
            'send_status' => SmsRecord::STATUS_PENDING,
            'operator_id' => (int) ($operator['id'] ?? 0),
            'operator_name' => (string) ($operator['name'] ?? ''),
            'caller_app' => mb_substr(trim($callerApp), 0, 100),
            'status' => 1,
        ]);

        try {
            $provider = ProviderFactory::make((string) $template->provider);
            $result = $provider->send(
                $mobile,
                (string) $template->sign_name,
                (string) $template->code,
                $params
            );

            $record->update([
                'vendor_msg_id' => (string) $result['vendor_msg_id'],
                'biz_id' => (string) $result['biz_id'],
                'send_status' => SmsRecord::STATUS_SUCCESS,
                'response' => $result['response'],
                'request_info' => $result['request_info'] ?? null,
            ]);

            return $record->refresh()->toDetailArray();
        } catch (SmsException $e) {
            $this->markFailed($record, $e->getMessage(), $e->getRequestInfo());
            throw $e;
        } catch (Throwable $e) {
            // 意外异常不向用户透出底层细节（可能含 SQL / 请求地址等），全量记入日志便于排查
            Log::error('短信发送异常', [
                'record_id' => (int) $record->id,
                'provider' => (string) $record->provider,
                'exception' => $e,
            ]);
            $this->markFailed($record, self::UNEXPECTED_FAILURE_MESSAGE);
            throw new SmsException(self::UNEXPECTED_FAILURE_MESSAGE);
        }
    }

    /**
     * 按应用级模板标识发送短信（供其他应用按标识调用，不依赖模板 ID）
     *
     * 停用校验、手机号与必填校验、落库与异常处理均复用 send() 主流程。
     *
     * @param string $alias 应用级模板标识
     * @param string $mobile 接收手机号
     * @param array<int|string,mixed> $fieldValues 表单值，键为字段 ID
     * @param array{id?:int,name?:string} $operator 操作人信息
     * @param string $callerApp 调用方应用标识
     * @return array<string,mixed> 发送后记录详情
     *
     * @throws SmsException 模板不存在、校验失败或服务商发送失败时
     */
    public function sendByAlias(string $alias, string $mobile, array $fieldValues, array $operator = [], string $callerApp = ''): array
    {
        $template = $this->templateService->findByAlias($alias);

        return $this->send((int) $template->id, $mobile, $fieldValues, $operator, $callerApp);
    }

    // ──────────────────────────────────────────────────────────────
    // 内部工具
    // ──────────────────────────────────────────────────────────────

    /**
     * 标记记录为发送失败
     *
     * 错误信息按字段长度截断，且落库异常自身兜底：
     * 失败记录写入不得影响原始业务异常的抛出，否则用户只能看到数据库报错，掩盖真实失败原因。
     *
     * @param array<string,mixed>|null $requestInfo 发起请求时的接口信息（失败排查用）
     */
    private function markFailed(SmsRecord $record, string $message, ?array $requestInfo = null): void
    {
        try {
            $record->update([
                'send_status' => SmsRecord::STATUS_FAILED,
                'error_message' => $this->truncateMessage($message),
                'request_info' => $requestInfo,
            ]);
        } catch (Throwable $e) {
            Log::error('短信发送失败记录写入异常', [
                'record_id' => (int) $record->id,
                'provider' => (string) $record->provider,
                'exception' => $e,
            ]);
        }
    }

    /**
     * 截断超长错误信息，保证不超过字段长度上限
     */
    private function truncateMessage(string $message): string
    {
        if (mb_strlen($message) <= self::ERROR_MESSAGE_MAX_LENGTH) {
            return $message;
        }

        return mb_substr($message, 0, self::ERROR_MESSAGE_MAX_LENGTH - 1) . '…';
    }

    /**
     * 校验并规范化手机号
     *
     * @throws SmsException
     */
    private function normalizeMobile(string $mobile): string
    {
        $mobile = trim($mobile);

        if ($mobile === '') {
            throw new SmsException('请填写接收短信的手机号');
        }

        if (! preg_match('/^\+?\d{6,20}$/', $mobile)) {
            throw new SmsException('手机号码格式不正确');
        }

        return $mobile;
    }

    /**
     * 按模板字段配置把表单值组装为模板变量键值对
     *
     * @param array<int|string,mixed> $fieldValues
     * @return array<string,string> 变量名 => 值（顺序与模板变量出现顺序一致）
     *
     * @throws SmsException 必填项缺失时
     */
    private function buildParams(SmsTemplate $template, array $fieldValues): array
    {
        $collected = [];

        foreach ($template->fields as $field) {
            $varName = (string) $field->var_name;
            $raw = $fieldValues[$field->id] ?? ($fieldValues[(string) $field->id] ?? null);

            if ($raw === null) {
                $raw = $field->default_value;
            }

            $value = $this->formatFieldValue($field, $raw);

            if ((int) $field->is_required === 1 && $value === '') {
                $label = (string) $field->label !== '' ? (string) $field->label : $varName;
                throw new SmsException('请填写「' . $label . '」');
            }

            $collected[$varName][] = $value;
        }

        $params = [];
        foreach ($template->extractVariables() as $varName) {
            $params[$varName] = isset($collected[$varName])
                ? implode('', $collected[$varName])
                : '';
        }

        return $params;
    }

    /**
     * 格式化单个控件的取值为字符串
     */
    private function formatFieldValue(TemplateField $field, mixed $raw): string
    {
        if ((string) $field->field_type === TemplateField::TYPE_CHECKBOX) {
            if (! is_array($raw)) {
                return trim((string) $raw);
            }

            $items = [];
            foreach ($raw as $item) {
                $item = trim((string) $item);
                if ($item !== '') {
                    $items[] = $item;
                }
            }

            return implode('、', $items);
        }

        if (is_array($raw)) {
            return trim(implode('、', array_map('strval', $raw)));
        }

        return trim((string) $raw);
    }

    /**
     * 用变量值渲染短信正文（用于记录展示）
     *
     * @param array<string,string> $params
     */
    private function renderContent(string $content, array $params): string
    {
        foreach ($params as $name => $value) {
            $content = str_replace('${' . $name . '}', $value, $content);
        }

        return $content;
    }
}