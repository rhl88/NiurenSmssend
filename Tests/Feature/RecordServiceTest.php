<?php

namespace App\Apps\NiurenSmssend\Tests\Feature;

use App\Apps\NiurenSmssend\Exceptions\SmsException;
use App\Apps\NiurenSmssend\Models\SmsRecord;
use App\Apps\NiurenSmssend\Services\RecordService;
use App\Apps\NiurenSmssend\Tests\Support\NiurenSmssendSetup;
use Tests\TestCase;

/**
 * 短信发送 · 发送记录服务测试
 *
 * 覆盖发送记录的多条件查询与详情读取。
 */
class RecordServiceTest extends TestCase
{
    use NiurenSmssendSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNiurenSmssend();
    }

    private function service(): RecordService
    {
        return app(RecordService::class);
    }

    public function test_按服务商与状态筛选记录(): void
    {
        $this->createRecord(['provider' => 'aliyun', 'send_status' => 'success']);
        $this->createRecord(['provider' => 'tencent', 'send_status' => 'failed', 'mobile' => '13900139000']);

        $aliyun = $this->service()->paginate('aliyun', null, null, null, null);
        $this->assertSame(1, $aliyun->total());

        $success = $this->service()->paginate(null, 'success', null, null, null);
        $this->assertSame(1, $success->total());

        $failedTencent = $this->service()->paginate('tencent', 'failed', null, null, null);
        $this->assertSame(1, $failedTencent->total());

        $this->assertSame(2, $this->service()->paginate(null, null, null, null, null)->total());
    }

    public function test_按关键字搜索手机号或模板名称(): void
    {
        $this->createRecord(['mobile' => '13800138000', 'template_name' => '体检通知']);
        $this->createRecord(['mobile' => '13911112222', 'template_name' => '缴费提醒']);

        $byMobile = $this->service()->paginate(null, null, '13800', null, null);
        $this->assertSame(1, $byMobile->total());
        $this->assertSame('13800138000', $byMobile->items()[0]->mobile);

        $byName = $this->service()->paginate(null, null, '缴费', null, null);
        $this->assertSame(1, $byName->total());
        $this->assertSame('缴费提醒', $byName->items()[0]->template_name);
    }

    public function test_按关键字可搜索到调用方标识(): void
    {
        $this->createRecord(['mobile' => '13800138000', 'caller_app' => 'niuren.form']);
        $this->createRecord(['mobile' => '13900139000', 'caller_app' => '']);

        $result = $this->service()->paginate(null, null, 'niuren.form', null, null);

        $this->assertSame(1, $result->total());
        $this->assertSame('niuren.form', $result->items()[0]->caller_app);
        $this->assertSame('niuren.form', $result->items()[0]->toSummaryArray()['caller_app']);
    }

    public function test_关键字搜索调用方标识不命中后台记录(): void
    {
        $this->createRecord(['mobile' => '13800138000', 'caller_app' => '']);

        $result = $this->service()->paginate(null, null, 'niuren', null, null);

        $this->assertSame(0, $result->total());
    }

    public function test_按调用来源筛选记录(): void
    {
        $this->createRecord(['mobile' => '13800138000', 'caller_app' => 'niuren.form']);
        $this->createRecord(['mobile' => '13900139000', 'caller_app' => '']);
        $this->createRecord(['mobile' => '13700137000', 'caller_app' => 'niuren.checkin']);

        $byApp = $this->service()->paginate(null, null, null, null, null, 'niuren.form');
        $this->assertSame(1, $byApp->total());
        $this->assertSame('niuren.form', $byApp->items()[0]->caller_app);

        $byBackend = $this->service()->paginate(null, null, null, null, null, SmsRecord::CALLER_BACKEND_FILTER);
        $this->assertSame(1, $byBackend->total());
        $this->assertSame('', $byBackend->items()[0]->caller_app);

        // 空值不附加筛选条件
        $this->assertSame(3, $this->service()->paginate(null, null, null, null, null, '')->total());
    }

    public function test_调用来源筛选项去重升序提取(): void
    {
        $this->createRecord(['mobile' => '13800138000', 'caller_app' => 'niuren.form']);
        $this->createRecord(['mobile' => '13900139000', 'caller_app' => 'niuren.form']);
        $this->createRecord(['mobile' => '13700137000', 'caller_app' => 'niuren.checkin']);
        $this->createRecord(['mobile' => '13600136000', 'caller_app' => '']);

        $this->assertSame(['niuren.checkin', 'niuren.form'], $this->service()->callerAppOptions());
    }

    public function test_按日期范围筛选记录(): void
    {
        $this->createRecord(['create_time' => '2026-10-01 10:00:00', 'mobile' => '13800000001']);
        $this->createRecord(['create_time' => '2026-10-20 10:00:00', 'mobile' => '13800000002']);

        $result = $this->service()->paginate(null, null, null, '2026-10-15', '2026-10-22');

        $this->assertSame(1, $result->total());
        $this->assertSame('13800000002', $result->items()[0]->mobile);
        $this->assertSame('2026-10-20 10:00:00', $result->items()[0]->create_time->format('Y-m-d H:i:s'));
    }

    public function test_记录按ID倒序返回(): void
    {
        $first = $this->createRecord(['mobile' => '13800000001']);
        $second = $this->createRecord(['mobile' => '13800000002']);

        $result = $this->service()->paginate(null, null, null, null, null);

        $this->assertSame($second->id, $result->items()[0]->id);
        $this->assertSame($first->id, $result->items()[1]->id);
    }

    public function test_记录详情返回参数与原始响应(): void
    {
        $record = $this->createRecord([
            'params' => ['name' => '张三', 'type' => '入职体检'],
            'response' => ['Code' => 'OK', 'BizId' => 'biz_1'],
        ]);

        $detail = $this->service()->show($record->id)->toDetailArray();

        $this->assertSame('张三', $detail['params']['name']);
        $this->assertSame('入职体检', $detail['params']['type']);
        $this->assertSame('OK', $detail['response']['Code']);
    }

    public function test_记录不存在时抛出异常(): void
    {
        $this->expectException(SmsException::class);
        $this->expectExceptionMessage('发送记录不存在或已被删除');

        $this->service()->show(999999);
    }
}
