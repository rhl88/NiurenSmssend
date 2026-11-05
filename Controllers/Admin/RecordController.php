<?php

namespace App\Apps\NiurenSmssend\Controllers\Admin;

use App\Apps\NiurenSmssend\Controllers\BaseAdminController;
use App\Apps\NiurenSmssend\Exceptions\SmsException;
use App\Apps\NiurenSmssend\Models\SmsRecord;
use App\Apps\NiurenSmssend\Models\SmsTemplate;
use App\Apps\NiurenSmssend\Services\RecordService;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 短信发送 · 发送记录控制器
 *
 * 提供发送记录列表页、列表数据与记录详情（含发送参数、服务商响应与错误信息）。
 * 记录为只读数据（需求仅要求查看发送记录与发送结果），不提供编辑与删除。
 * 列表接口采用 Layui table 要求的 {code,msg,count,data} 结构。
 */
class RecordController extends BaseAdminController
{
    /**
     * 记录业务服务
     */
    protected RecordService $service;

    /**
     * 构造注入记录服务
     */
    public function __construct(RecordService $service)
    {
        $this->service = $service;
    }

    /**
     * 发送记录列表页
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('niuren.smssend::Admin.records', [
            'providers' => SmsTemplate::providerMap(),
            'statusMap' => SmsRecord::statusMap(),
        ]);
    }

    /**
     * 发送记录列表数据（Layui table 数据源，支持多条件筛选）
     *
     * @param Request $request 请求对象
     * @return JsonResponse
     */
    public function list(Request $request): JsonResponse
    {
        if (! $this->hasPermission('niuren.smssend.record')) {
            return $this->permissionDenied();
        }

        $pageSize = (int) $request->input('limit', 15);
        $pageSize = max(1, min(100, $pageSize));

        $paginator = $this->service->paginate(
            $request->input('provider'),
            $request->input('status'),
            $request->input('keyword'),
            $request->input('date_from'),
            $request->input('date_to'),
            $pageSize
        );

        $rows = collect($paginator->items())
            ->map(fn (SmsRecord $record) => $record->toSummaryArray())
            ->values()
            ->toArray();

        return response()->json([
            'code' => 0,
            'msg' => '',
            'count' => $paginator->total(),
            'data' => $rows,
        ]);
    }

    /**
     * 发送记录详情（含参数与原始响应）
     *
     * @param int $id 记录 id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        if (! $this->hasPermission('niuren.smssend.record')) {
            return $this->permissionDenied();
        }

        try {
            $record = $this->service->show($id);
        } catch (SmsException $e) {
            return response()->json(ApiResponse::error(40401, $e->getMessage()));
        }

        return response()->json(ApiResponse::success($record->toDetailArray()));
    }
}
