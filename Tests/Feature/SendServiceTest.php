<?php

namespace App\Apps\NiurenSmssend\Tests\Feature;

use App\Apps\NiurenSmssend\Exceptions\SmsException;
use App\Apps\NiurenSmssend\Models\SmsRecord;
use App\Apps\NiurenSmssend\Services\SendService;
use App\Apps\NiurenSmssend\Tests\Support\NiurenSmssendSetup;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * 短信发送 · 发送服务测试
 *
 * 覆盖发送链路：
 * - 按「同一变量多控件」顺序拼接变量值并渲染正文
 * - 多选控件以顿号连接
 * - 必填缺失 / 手机号非法 / 模板停用的边界校验（不落库或落 failed）
 * - 服务商返回错误映射中文提示并记录 failed
 * - 火山引擎预留能力拒绝发送
 */
class SendServiceTest extends TestCase
{
    use NiurenSmssendSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNiurenSmssend();
    }

    private function service(): SendService
    {
        return app(SendService::class);
    }

    /**
     * 模拟阿里云发送成功
     */
    private function fakeAliyunSuccess(): void
    {
        Http::fake([
            'dysmsapi.aliyuncs.com/*' => Http::response([
                'Code' => 'OK',
                'Message' => 'OK',
                'BizId' => 'biz_123456',
                'RequestId' => 'req_abcdef',
            ]),
        ]);
    }

    public function test_发送成功后按字段顺序拼接变量并落库(): void
    {
        $this->fakeAliyunSuccess();

        $template = $this->createTemplate([
            'content' => '尊敬的${name}，您好！您本次体检${type}，结果${info}',
        ], [
            ['var_name' => 'name', 'label' => '姓名', 'field_type' => 'text'],
            ['var_name' => 'name', 'label' => '性别', 'field_type' => 'radio', 'options' => [
                ['label' => '先生', 'value' => '先生'],
                ['label' => '女士', 'value' => '女士'],
            ]],
            ['var_name' => 'type', 'label' => '类型', 'field_type' => 'select', 'options' => [
                ['label' => '入职体检', 'value' => '入职体检'],
            ]],
            ['var_name' => 'info', 'label' => '结果', 'field_type' => 'textarea'],
        ]);

        $fields = $template->fields;
        $values = [
            $fields[0]->id => '张三',
            $fields[1]->id => '先生',
            $fields[2]->id => '入职体检',
            $fields[3]->id => '一切正常',
        ];

        $record = $this->service()->send($template->id, '13800138000', $values, [
            'id' => 1,
            'name' => '管理员',
        ]);

        $this->assertSame('success', $record['send_status']);
        $this->assertSame('张三先生', $record['params']['name']);
        $this->assertSame('入职体检', $record['params']['type']);
        $this->assertSame(
            '尊敬的张三先生，您好！您本次体检入职体检，结果一切正常',
            $record['content']
        );
        $this->assertSame('biz_123456', $record['vendor_msg_id']);
        $this->assertSame('管理员', $record['operator_name']);

        $this->assertDatabaseHas('app_niuren_smssend_records', [
            'mobile' => '13800138000',
            'send_status' => 'success',
            'vendor_msg_id' => 'biz_123456',
        ]);
    }

    public function test_多选控件取值以顿号连接(): void
    {
        $this->fakeAliyunSuccess();

        $template = $this->createTemplate([
            'content' => '您选择的套餐为${pkg}',
        ], [
            ['var_name' => 'pkg', 'label' => '套餐', 'field_type' => 'checkbox', 'options' => [
                ['label' => 'A', 'value' => 'A'],
                ['label' => 'B', 'value' => 'B'],
                ['label' => 'C', 'value' => 'C'],
            ]],
        ]);

        $field = $template->fields->first();

        $record = $this->service()->send($template->id, '13800138000', [
            $field->id => ['A', 'C'],
        ]);

        $this->assertSame('A、C', $record['params']['pkg']);
        $this->assertSame('您选择的套餐为A、C', $record['content']);
    }

    public function test_必填字段缺失时抛出异常且不落库(): void
    {
        $template = $this->createTemplate(['content' => '尊敬的${name}'], [
            ['var_name' => 'name', 'label' => '姓名', 'field_type' => 'text', 'is_required' => 1],
        ]);

        try {
            $this->service()->send($template->id, '13800138000', []);
            $this->fail('应当抛出 SmsException');
        } catch (SmsException $e) {
            $this->assertSame('请填写「姓名」', $e->getMessage());
        }

        // 校验发生在建记录之前，失败不落库
        $this->assertDatabaseCount('app_niuren_smssend_records', 0);
    }

    public function test_手机号格式错误时抛出异常(): void
    {
        $template = $this->createTemplate();

        $this->expectException(SmsException::class);
        $this->expectExceptionMessage('手机号码格式不正确');

        $this->service()->send($template->id, 'abc123', []);
    }

    public function test_未填写手机号时抛出异常(): void
    {
        $template = $this->createTemplate();

        $this->expectException(SmsException::class);
        $this->expectExceptionMessage('请填写接收短信的手机号');

        $this->service()->send($template->id, '  ', []);
    }

    public function test_模板停用时拒绝发送(): void
    {
        $template = $this->createTemplate(['status' => 0]);

        $this->expectException(SmsException::class);
        $this->expectExceptionMessage('该短信模板已停用，无法发送');

        $this->service()->send($template->id, '13800138000', []);
    }

    public function test_服务商返回错误时记录失败并映射中文提示(): void
    {
        Http::fake([
            'dysmsapi.aliyuncs.com/*' => Http::response([
                'Code' => 'isv.SMS_TEMPLATE_ILLEGAL',
                'Message' => 'template illegal',
            ]),
        ]);

        $template = $this->createTemplate(['content' => '尊敬的${name}'], [
            ['var_name' => 'name', 'label' => '姓名', 'field_type' => 'text'],
        ]);
        $field = $template->fields->first();

        try {
            $this->service()->send($template->id, '13800138000', [$field->id => '张三']);
            $this->fail('应当抛出 SmsException');
        } catch (SmsException $e) {
            $this->assertSame('短信模板不存在或未审核通过，请核对模板 CODE', $e->getMessage());
        }

        $this->assertDatabaseHas('app_niuren_smssend_records', [
            'mobile' => '13800138000',
            'send_status' => 'failed',
            'error_message' => '短信模板不存在或未审核通过，请核对模板 CODE',
        ]);
    }

    public function test_火山引擎预留能力拒绝发送并记录失败(): void
    {
        $template = $this->createTemplate([
            'provider' => 'volcano',
            'content' => '尊敬的${name}',
        ], [
            ['var_name' => 'name', 'label' => '姓名', 'field_type' => 'text'],
        ]);
        $field = $template->fields->first();

        try {
            $this->service()->send($template->id, '13800138000', [$field->id => '张三']);
            $this->fail('应当抛出 SmsException');
        } catch (SmsException $e) {
            $this->assertStringContainsString('预留能力', $e->getMessage());
        }

        $this->assertDatabaseHas('app_niuren_smssend_records', [
            'provider' => 'volcano',
            'send_status' => 'failed',
        ]);
    }

    public function test_阿里云请求携带规范化后的模板参数(): void
    {
        $this->fakeAliyunSuccess();

        $template = $this->createTemplate(['content' => '尊敬的${name}'], [
            ['var_name' => 'name', 'label' => '姓名', 'field_type' => 'text'],
        ]);
        $field = $template->fields->first();

        $this->service()->send($template->id, '13800138000', [$field->id => '张三']);

        Http::assertSent(function ($request) use ($template) {
            $url = urldecode($request->url());

            return str_contains($url, 'Action=SendSms')
                && str_contains($url, 'PhoneNumbers=13800138000')
                && str_contains($url, (string) $template->code)
                && str_contains($url, '"name":"张三"');
        });
    }

    public function test_网络异常时记录失败且不泄露请求地址与敏感参数(): void
    {
        // 模拟真实连接超时：异常消息带完整请求 URL（含 AccessKeyId / Signature）
        Http::fake(function () {
            throw new ConnectionException(
                'cURL error 28: Resolving timed out after 5009 milliseconds (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) '
                . 'for https://dysmsapi.aliyuncs.com/?AccessKeyId=LTAI5t92yyvJrA9RFb76vTse&Action=SendSms&Signature=mkldIE4RtNf3T5NV1cj7weDE8UE%3D'
            );
        });

        $template = $this->createTemplate(['content' => '尊敬的${name}'], [
            ['var_name' => 'name', 'label' => '姓名', 'field_type' => 'text'],
        ]);
        $field = $template->fields->first();

        try {
            $this->service()->send($template->id, '13800138000', [$field->id => '张三']);
            $this->fail('应当抛出 SmsException');
        } catch (SmsException $e) {
            $this->assertStringContainsString('网络连接异常', $e->getMessage());
        }

        $record = SmsRecord::query()->where('mobile', '13800138000')->firstOrFail();
        $this->assertSame(SmsRecord::STATUS_FAILED, $record->send_status);
        $this->assertStringContainsString('网络连接异常', (string) $record->error_message);
        $this->assertStringNotContainsString('AccessKeyId', (string) $record->error_message);
        $this->assertStringNotContainsString('Signature', (string) $record->error_message);
        $this->assertLessThanOrEqual(500, mb_strlen((string) $record->error_message));
    }

    public function test_服务商返回超长错误消息时落库截断至字段上限(): void
    {
        // 700 字符，超出 error_message varchar(500)
        $longMessage = str_repeat('模板参数不合法', 100);

        Http::fake([
            'dysmsapi.aliyuncs.com/*' => Http::response([
                'Code' => 'isv.UNMAPPED_ERROR_CODE',
                'Message' => $longMessage,
            ]),
        ]);

        $template = $this->createTemplate(['content' => '尊敬的${name}'], [
            ['var_name' => 'name', 'label' => '姓名', 'field_type' => 'text'],
        ]);
        $field = $template->fields->first();

        try {
            $this->service()->send($template->id, '13800138000', [$field->id => '张三']);
            $this->fail('应当抛出 SmsException');
        } catch (SmsException $e) {
            // 未映射错误码时按服务商原文抛出
            $this->assertSame($longMessage, $e->getMessage());
        }

        $record = SmsRecord::query()->where('mobile', '13800138000')->firstOrFail();
        $this->assertSame(SmsRecord::STATUS_FAILED, $record->send_status);
        $this->assertSame(500, mb_strlen((string) $record->error_message));
        $this->assertStringEndsWith('…', (string) $record->error_message);
    }

    public function test_发送成功时接口信息落库并可在详情返回(): void
    {
        $this->fakeAliyunSuccess();

        $template = $this->createTemplate(['content' => '尊敬的${name}'], [
            ['var_name' => 'name', 'label' => '姓名', 'field_type' => 'text'],
        ]);
        $field = $template->fields->first();

        $detail = $this->service()->send($template->id, '13800138000', [$field->id => '张三']);

        $this->assertSame('https://dysmsapi.aliyuncs.com/', $detail['request_info']['url'] ?? '');
        $this->assertSame('GET', $detail['request_info']['method'] ?? '');
        $this->assertSame('13800138000', $detail['request_info']['params']['PhoneNumbers'] ?? '');

        $record = SmsRecord::query()->where('mobile', '13800138000')->firstOrFail();
        $info = $record->request_info;
        $this->assertIsArray($info);
        $this->assertSame($template->code, $info['params']['TemplateCode'] ?? '');
    }

    public function test_发送失败时接口信息同样落库便于排查(): void
    {
        // 业务错误：服务商返回未映射错误码
        Http::fake([
            'dysmsapi.aliyuncs.com/*' => Http::response([
                'Code' => 'isv.UNMAPPED_ERROR_CODE',
                'Message' => 'unknown error',
            ]),
        ]);

        $template = $this->createTemplate(['content' => '尊敬的${name}'], [
            ['var_name' => 'name', 'label' => '姓名', 'field_type' => 'text'],
        ]);
        $field = $template->fields->first();

        try {
            $this->service()->send($template->id, '13800138000', [$field->id => '张三']);
            $this->fail('应当抛出 SmsException');
        } catch (SmsException $e) {
            $this->assertSame('unknown error', $e->getMessage());
        }

        $record = SmsRecord::query()->where('mobile', '13800138000')->firstOrFail();
        $this->assertSame(SmsRecord::STATUS_FAILED, $record->send_status);

        $info = $record->request_info;
        $this->assertIsArray($info);
        $this->assertSame('https://dysmsapi.aliyuncs.com/', $info['url'] ?? '');
        $this->assertSame('13800138000', $info['params']['PhoneNumbers'] ?? '');
        $this->assertSame($template->code, $info['params']['TemplateCode'] ?? '');
    }

    public function test_网络异常失败记录也携带接口信息(): void
    {
        Http::fake(function () {
            throw new ConnectionException('cURL error 77: Problem with the SSL CA cert');
        });

        $template = $this->createTemplate(['content' => '尊敬的${name}'], [
            ['var_name' => 'name', 'label' => '姓名', 'field_type' => 'text'],
        ]);
        $field = $template->fields->first();

        try {
            $this->service()->send($template->id, '13800138000', [$field->id => '张三']);
            $this->fail('应当抛出 SmsException');
        } catch (SmsException $e) {
            $this->assertStringContainsString('网络连接异常', $e->getMessage());
        }

        $record = SmsRecord::query()->where('mobile', '13800138000')->firstOrFail();
        $info = $record->request_info;
        $this->assertIsArray($info);
        $this->assertSame('https://dysmsapi.aliyuncs.com/', $info['url'] ?? '');
        $this->assertSame($template->code, $info['params']['TemplateCode'] ?? '');
        $this->assertStringNotContainsString('AccessKeyId', json_encode($info, JSON_UNESCAPED_UNICODE) ?: '');
        $this->assertStringNotContainsString('Signature', json_encode($info, JSON_UNESCAPED_UNICODE) ?: '');
    }

    public function test_按模板标识发送成功(): void
    {
        $this->fakeAliyunSuccess();

        $template = $this->createTemplate(['alias' => 'health.notice', 'content' => '尊敬的${name}'], [
            ['var_name' => 'name', 'label' => '姓名', 'field_type' => 'text'],
        ]);
        $field = $template->fields->first();

        $record = $this->service()->sendByAlias('health.notice', '13800138000', [$field->id => '张三'], [], 'niuren.form');

        $this->assertSame('success', $record['send_status']);
        $this->assertSame('niuren.form', $record['caller_app']);
    }

    public function test_按不存在的模板标识发送时报错(): void
    {
        $this->expectException(SmsException::class);
        $this->expectExceptionMessage('短信模板不存在或已被删除');

        $this->service()->sendByAlias('not.exists', '13800138000', [], [], 'niuren.form');
    }

    public function test_标识调用停用模板时由主流程拒绝(): void
    {
        $this->createTemplate(['alias' => 'disabled.tpl', 'status' => 0]);

        $this->expectException(SmsException::class);
        $this->expectExceptionMessage('该短信模板已停用，无法发送');

        $this->service()->sendByAlias('disabled.tpl', '13800138000', [], [], 'niuren.form');
    }

    public function test_调用方标识随记录落库(): void
    {
        $this->fakeAliyunSuccess();

        $template = $this->createTemplate(['alias' => 'checkin.tpl', 'content' => '尊敬的${name}'], [
            ['var_name' => 'name', 'label' => '姓名', 'field_type' => 'text'],
        ]);
        $field = $template->fields->first();

        // 按标识调用并传调用方
        $this->service()->sendByAlias('checkin.tpl', '13800138000', [$field->id => '张三'], ['id' => 9, 'name' => '外部应用'], 'niuren.checkin');

        $record = SmsRecord::query()->where('mobile', '13800138000')->firstOrFail();
        $this->assertSame('niuren.checkin', $record->caller_app);
        $this->assertSame('niuren.checkin', $record->toSummaryArray()['caller_app']);
    }

    public function test_后台发送不传调用方时记录落空串(): void
    {
        $this->fakeAliyunSuccess();

        $template = $this->createTemplate(['content' => '尊敬的${name}'], [
            ['var_name' => 'name', 'label' => '姓名', 'field_type' => 'text'],
        ]);
        $field = $template->fields->first();

        $this->service()->send($template->id, '13800138000', [$field->id => '张三']);

        $record = SmsRecord::query()->where('mobile', '13800138000')->firstOrFail();
        $this->assertSame('', $record->caller_app);
    }

    public function test_超长调用方标识落库时截断至字段上限(): void
    {
        $this->fakeAliyunSuccess();

        $template = $this->createTemplate(['content' => '尊敬的${name}'], [
            ['var_name' => 'name', 'label' => '姓名', 'field_type' => 'text'],
        ]);
        $field = $template->fields->first();

        $this->service()->send($template->id, '13800138000', [$field->id => '张三'], [], str_repeat('a', 150));

        $record = SmsRecord::query()->where('mobile', '13800138000')->firstOrFail();
        $this->assertSame(100, mb_strlen($record->caller_app));
    }
}
