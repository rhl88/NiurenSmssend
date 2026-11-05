<?php

namespace App\Apps\NiurenSmssend\Tests\Support;

use App\Apps\NiurenSmssend\Models\SmsRecord;
use App\Apps\NiurenSmssend\Models\SmsTemplate;
use App\Apps\NiurenSmssend\Models\TemplateField;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * 短信发送 · 测试公共基建
 *
 * - 加载应用辅助函数并在内存库（sqlite :memory:）建本应用三张表
 * - 注入测试默认配置（阿里云 / 腾讯云 / 火山引擎），使各服务商适配器构造与发送链路可测
 * - 提供造数辅助：构造模板（含表单字段）与发送记录，供各用例复用
 */
trait NiurenSmssendSetup
{
    /**
     * 测试用阿里云 AccessKeyId
     */
    protected const FAKE_ALIYUN_KEY_ID = 'test_aliyun_key_id';

    /**
     * 测试用阿里云 AccessKeySecret
     */
    protected const FAKE_ALIYUN_KEY_SECRET = 'test_aliyun_key_secret';

    /**
     * 测试用腾讯云 SecretId
     */
    protected const FAKE_TENCENT_SECRET_ID = 'test_tencent_secret_id';

    /**
     * 测试用腾讯云 SecretKey
     */
    protected const FAKE_TENCENT_SECRET_KEY = 'test_tencent_secret_key';

    /**
     * 在 setUp() 中调用：加载辅助函数 + 建表 + 注入测试配置
     */
    protected function setUpNiurenSmssend(): void
    {
        // 显式加载应用辅助函数（niuren_smssend_config / niuren_smssend_current_provider / ...）。
        // 应用 ServiceProvider 仅在应用处于启用状态时注册，测试环境无 apps 表不会触发，
        // 故在测试基建中兜底加载，保证服务层与适配器调用辅助函数可用。
        $helpers = base_path('app/Apps/NiurenSmssend/helpers.php');
        if (file_exists($helpers)) {
            require_once $helpers;
        }

        // 辅助函数以 array 缓存保存配置 3600s，先清空避免跨用例读到过期配置
        Cache::flush();

        // 仅迁移本应用三张表（避免拉入框架 / 其他应用迁移），sqlite :memory: 每个用例全新
        $this->artisan('migrate', ['--path' => 'app/Apps/NiurenSmssend/Migrations']);

        config([
            'apps.niuren.smssend.current_provider' => 'aliyun',

            'apps.niuren.smssend.aliyun_access_key_id' => self::FAKE_ALIYUN_KEY_ID,
            'apps.niuren.smssend.aliyun_access_key_secret' => self::FAKE_ALIYUN_KEY_SECRET,
            'apps.niuren.smssend.aliyun_sign_name' => '测试签名',
            'apps.niuren.smssend.aliyun_region_id' => 'cn-hangzhou',
            'apps.niuren.smssend.aliyun_endpoint' => 'dysmsapi.aliyuncs.com',

            'apps.niuren.smssend.tencent_secret_id' => self::FAKE_TENCENT_SECRET_ID,
            'apps.niuren.smssend.tencent_secret_key' => self::FAKE_TENCENT_SECRET_KEY,
            'apps.niuren.smssend.tencent_sms_sdk_app_id' => '1400000000',
            'apps.niuren.smssend.tencent_sign_name' => '测试签名',
            'apps.niuren.smssend.tencent_region' => 'ap-guangzhou',
            'apps.niuren.smssend.tencent_endpoint' => 'sms.tencentcloudapi.com',

            'apps.niuren.smssend.volcano_access_key' => 'test_volcano_access_key',
            'apps.niuren.smssend.volcano_secret_key' => 'test_volcano_secret_key',
            'apps.niuren.smssend.volcano_sms_account' => 'test_volcano_account',
            'apps.niuren.smssend.volcano_sign_name' => '测试签名',
            'apps.niuren.smssend.volcano_region' => 'cn-north-1',
            'apps.niuren.smssend.volcano_endpoint' => 'sms.volcengineapi.com',
        ]);
    }

    /**
     * 构造一个短信模板（可直接附带表单字段）
     *
     * @param array<string,mixed>            $overrides 模板主体字段覆盖
     * @param array<int,array<string,mixed>> $fields    表单字段列表（未指定的 sort 按数组顺序自增）
     */
    protected function createTemplate(array $overrides = [], array $fields = []): SmsTemplate
    {
        $template = SmsTemplate::create(array_merge([
            'name' => '体检通知',
            'code' => 'SMS_' . random_int(100000000, 999999999),
            'provider' => SmsTemplate::PROVIDER_ALIYUN,
            'content' => '尊敬的${name}，您好！您本次体检${type}，结果${info}。医嘱${note}',
            'sign_name' => '测试签名',
            'remark' => '',
            'status' => 1,
        ], $overrides));

        $sort = 0;
        foreach ($fields as $field) {
            TemplateField::create(array_merge([
                'template_id' => (int) $template->id,
                'var_name' => 'name',
                'label' => '姓名',
                'field_type' => TemplateField::TYPE_TEXT,
                'options' => null,
                'default_value' => '',
                'placeholder' => '',
                'is_required' => 1,
                'sort' => $sort,
                'status' => 1,
            ], $field));
            $sort++;
        }

        return $template->refresh()->load('fields');
    }

    /**
     * 构造一条发送记录（可直接指定 create_time 以测试日期范围筛选）
     *
     * @param array<string,mixed> $overrides 字段覆盖；params/response 传数组即可
     */
    protected function createRecord(array $overrides = []): SmsRecord
    {
        $createTime = (string) ($overrides['create_time'] ?? Carbon::now()->format('Y-m-d H:i:s'));
        unset($overrides['create_time']);

        $data = array_merge([
            'provider' => SmsTemplate::PROVIDER_ALIYUN,
            'template_id' => 0,
            'template_code' => 'SMS_512651836',
            'template_name' => '体检通知',
            'mobile' => '13800138000',
            'sign_name' => '测试签名',
            'params' => json_encode(['name' => '张三'], JSON_UNESCAPED_UNICODE),
            'content' => '尊敬的张三',
            'vendor_msg_id' => 'biz_default',
            'biz_id' => 'req_default',
            'send_status' => SmsRecord::STATUS_SUCCESS,
            'error_code' => '',
            'error_message' => '',
            'response' => json_encode([], JSON_UNESCAPED_UNICODE),
            'operator_id' => 0,
            'operator_name' => '',
            'status' => 1,
            'create_time' => $createTime,
            'update_time' => $createTime,
        ], $overrides);

        // params / response 为 text 列，允许用例直接传数组，此处统一编码为 JSON
        foreach (['params', 'response'] as $jsonField) {
            if (isset($data[$jsonField]) && is_array($data[$jsonField])) {
                $data[$jsonField] = json_encode($data[$jsonField], JSON_UNESCAPED_UNICODE);
            }
        }

        $id = DB::table('app_niuren_smssend_records')->insertGetId($data);

        return SmsRecord::findOrFail($id);
    }
}
