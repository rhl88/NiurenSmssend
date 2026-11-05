<?php

namespace App\Apps\NiurenSmssend\Controllers\Admin;

use App\Apps\NiurenSmssend\Controllers\BaseAdminController;
use App\Apps\NiurenSmssend\Exceptions\SmsException;
use App\Apps\NiurenSmssend\Models\SmsTemplate;
use App\Apps\NiurenSmssend\Services\SendService;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 短信发送 · 发送短信控制器
 *
 * 提供发送页与发送接口。前端选择短信模板并填写模板表单，
 * Service 层完成手机号与必填校验、变量拼接、调用服务商接口并落库发送记录。
 * 失败返回业务错误码（记录已写入 failed 状态，可在发送记录中查看）。
 */
class SendController extends BaseAdminController
{
    /**
     * 发送业务服务
     */
    protected SendService $service;

    /**
     * 构造注入发送服务
     */
    public function __construct(SendService $service)
    {
        $this->service = $service;
    }

    /**
     * 发送短信页
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('niuren.smssend::Admin.send', [
            'providers' => SmsTemplate::providerMap(),
            'defaultProvider' => niuren_smssend_current_provider(),
        ]);
    }

    /**
     * 发送短信接口
     *
     * @param Request $request 请求对象
     * @return JsonResponse
     */
    public function send(Request $request): JsonResponse
    {
        if (! $this->hasPermission('niuren.smssend.send')) {
            return $this->permissionDenied();
        }

        $validator = Validator::make($request->all(), [
            'template_id' => 'required|integer|min:1',
            'mobile' => 'required|string|max:20',
            'field_values' => 'nullable|array',
        ], [
            'template_id.required' => '请选择短信模板',
            'template_id.min' => '请选择有效的短信模板',
            'mobile.required' => '请填写接收短信的手机号',
            'mobile.max' => '手机号码长度不合法',
        ], [
            'template_id' => '短信模板',
            'mobile' => '接收手机号',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error(40001, (string) $validator->errors()->first()));
        }

        /** @var \App\Models\AdminUser|null $admin */
        $admin = auth('admin')->user();

        try {
            $result = $this->service->send(
                (int) $request->input('template_id'),
                (string) $request->input('mobile'),
                (array) $request->input('field_values', []),
                [
                    'id' => (int) ($admin->id ?? 0),
                    'name' => (string) ($admin->username ?? $admin->name ?? ''),
                ]
            );
        } catch (SmsException $e) {
            $code = is_int($e->getCode()) && $e->getCode() > 0 ? $e->getCode() : 40001;

            return response()->json(ApiResponse::error($code, $e->getMessage()));
        }

        return response()->json(ApiResponse::success($result, '发送成功'));
    }
}