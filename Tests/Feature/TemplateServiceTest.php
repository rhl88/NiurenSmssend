<?php

namespace App\Apps\NiurenSmssend\Tests\Feature;

use App\Apps\NiurenSmssend\Exceptions\SmsException;
use App\Apps\NiurenSmssend\Models\SmsTemplate;
use App\Apps\NiurenSmssend\Services\TemplateService;
use App\Apps\NiurenSmssend\Tests\Support\NiurenSmssendSetup;
use Tests\TestCase;

/**
 * 短信发送 · 短信模板服务测试
 *
 * 覆盖模板与表单字段的核心链路：
 * - 创建模板并保存「同一变量多控件」的表单字段（需求核心场景）
 * - 模板 CODE 在同服务商下唯一
 * - 字段变量必须存在于模板内容、选项类字段必须配置选项
 * - 变量提取顺序、更新重建字段、删除级联字段
 */
class TemplateServiceTest extends TestCase
{
    use NiurenSmssendSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNiurenSmssend();
    }

    private function service(): TemplateService
    {
        return app(TemplateService::class);
    }

    public function test_创建模板并保存同一变量多控件的表单字段(): void
    {
        $template = $this->service()->create([
            'name' => '体检通知',
            'code' => 'SMS_512651836',
            'provider' => SmsTemplate::PROVIDER_ALIYUN,
            'content' => '尊敬的${name}，您好！您本次体检${type}，结果${info}。医嘱${note}',
            'sign_name' => '体检中心',
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
            ['var_name' => 'note', 'label' => '备注', 'field_type' => 'textarea', 'is_required' => 0],
        ]);

        // ${name} 绑定两个控件（姓名 + 性别），保留排序供发送时拼接
        $this->assertSame(5, $template->fields->count());
        $this->assertSame([0, 1, 2, 3, 4], $template->fields->pluck('sort')->all());
        $this->assertSame(['name', 'name', 'type', 'info', 'note'], $template->fields->pluck('var_name')->all());

        // 单选控件的选项被规范化保存
        $this->assertSame(['先生', '女士'], array_column($template->fields[1]->options, 'value'));
        $this->assertSame(0, (int) $template->fields[4]->is_required);
    }

    public function test_同一服务商下模板CODE不可重复(): void
    {
        $this->service()->create([
            'name' => '体检通知',
            'code' => 'SMS_DUP_001',
            'provider' => SmsTemplate::PROVIDER_ALIYUN,
            'content' => '尊敬的${name}',
        ], []);

        $this->expectException(SmsException::class);
        $this->expectExceptionMessage('该服务商下已存在相同的模板 CODE');

        $this->service()->create([
            'name' => '体检通知2',
            'code' => 'SMS_DUP_001',
            'provider' => SmsTemplate::PROVIDER_ALIYUN,
            'content' => '尊敬的${name}',
        ], []);
    }

    public function test_不同服务商下可使用相同模板CODE(): void
    {
        $this->service()->create([
            'name' => '阿里云模板',
            'code' => 'SMS_SAME_001',
            'provider' => SmsTemplate::PROVIDER_ALIYUN,
            'content' => '尊敬的${name}',
        ], []);

        $template = $this->service()->create([
            'name' => '腾讯云模板',
            'code' => 'SMS_SAME_001',
            'provider' => SmsTemplate::PROVIDER_TENCENT,
            'content' => '尊敬的${name}',
        ], []);

        $this->assertSame(SmsTemplate::PROVIDER_TENCENT, $template->provider);
    }

    public function test_字段变量不在模板内容中时报错并回滚(): void
    {
        try {
            $this->service()->create([
                'name' => '异常模板',
                'code' => 'SMS_BAD_VAR',
                'provider' => SmsTemplate::PROVIDER_ALIYUN,
                'content' => '尊敬的${name}',
            ], [
                ['var_name' => 'unknown', 'label' => '未知', 'field_type' => 'text'],
            ]);
            $this->fail('应当抛出 SmsException');
        } catch (SmsException $e) {
            $this->assertStringContainsString('不存在于模板内容中', $e->getMessage());
        }

        // 事务回滚：模板不落库，字段也不落库
        $this->assertDatabaseMissing('app_niuren_smssend_templates', ['code' => 'SMS_BAD_VAR']);
        $this->assertDatabaseCount('app_niuren_smssend_template_fields', 0);
    }

    public function test_选项类字段缺少选项时报错(): void
    {
        $this->expectException(SmsException::class);
        $this->expectExceptionMessage('选项类字段至少需要配置一个选项');

        $this->service()->create([
            'name' => '选项模板',
            'code' => 'SMS_NO_OPTION',
            'provider' => SmsTemplate::PROVIDER_ALIYUN,
            'content' => '本次体检类型为${type}',
        ], [
            ['var_name' => 'type', 'label' => '类型', 'field_type' => 'select', 'options' => []],
        ]);
    }

    public function test_模板变量按出现顺序去重提取(): void
    {
        $template = new SmsTemplate([
            'content' => '${b}和${a}以及${b}，还有${c2}，忽略${9bad}',
        ]);

        $this->assertSame(['b', 'a', 'c2'], $template->extractVariables());
    }

    public function test_更新模板时重建表单字段(): void
    {
        $template = $this->createTemplate(['content' => '尊敬的${name}'], [
            ['var_name' => 'name', 'label' => '姓名', 'field_type' => 'text'],
        ]);

        $updated = $this->service()->update($template->id, [
            'name' => '体检通知V2',
            'code' => $template->code,
            'provider' => SmsTemplate::PROVIDER_ALIYUN,
            'content' => '尊敬的${name}，结果${info}',
        ], [
            ['var_name' => 'name', 'label' => '姓名', 'field_type' => 'text'],
            ['var_name' => 'info', 'label' => '结果', 'field_type' => 'textarea'],
        ]);

        $this->assertSame('体检通知V2', $updated->name);
        $this->assertSame(['name', 'info'], $updated->fields->pluck('var_name')->all());
        $this->assertDatabaseCount('app_niuren_smssend_template_fields', 2);
    }

    public function test_删除模板同时删除其表单字段(): void
    {
        $template = $this->createTemplate([], [
            ['var_name' => 'name', 'label' => '姓名', 'field_type' => 'text'],
        ]);

        $this->service()->delete($template->id);

        $this->assertDatabaseMissing('app_niuren_smssend_templates', ['id' => $template->id]);
        $this->assertDatabaseMissing('app_niuren_smssend_template_fields', ['template_id' => $template->id]);
    }

    public function test_模板不存在时抛出异常(): void
    {
        $this->expectException(SmsException::class);
        $this->expectExceptionMessage('短信模板不存在或已被删除');

        $this->service()->find(999999);
    }

    public function test_模板表单回显包含变量与字段结构(): void
    {
        $template = $this->createTemplate(['content' => '尊敬的${name}，结果${info}'], [
            ['var_name' => 'name', 'label' => '姓名', 'field_type' => 'text'],
            ['var_name' => 'info', 'label' => '结果', 'field_type' => 'textarea'],
        ]);

        $form = $this->service()->form($template->id);

        $this->assertSame(['name', 'info'], $form['variables']);
        $this->assertCount(2, $form['fields']);
        $this->assertSame('姓名', $form['fields'][0]['label']);
    }
}