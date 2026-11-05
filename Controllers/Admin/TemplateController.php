<?php

namespace App\Apps\NiurenSmssend\Controllers\Admin;

use App\Apps\NiurenSmssend\Controllers\BaseAdminController;
use App\Apps\NiurenSmssend\Exceptions\SmsException;
use App\Apps\NiurenSmssend\Models\SmsTemplate;
use App\Apps\NiurenSmssend\Services\TemplateService;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 短信发送 · 短信模板管理控制器
 *
 * 提供模板列表页与模板、模板表单字段的增删改查接口。
 * 模板内容中的 ${变量} 与表单字段强绑定：变量从模板内容解析，
 * 同一变量可搭配多个控件，按数组顺序决定发送时的拼接顺序。
 *
 * 列表接口采用 Layui table 要求的 {code,msg,count,data} 结构，
 * 其余接口采用系统统一的 ApiResponse 结构。
 */
class TemplateController extends BaseAdminController
{
    /**
     * 模板业务服务
     */
    protected TemplateService $service;

    /**
     * 构造注入模板服务
     */
    public function __construct(TemplateService $service)
    {
        $this->service = $service;
    }

    /**
     * 短信模板管理页
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('niuren.smssend::Admin.templates', [
            'providers' => SmsTemplate::providerMap(),
            'fieldTypes' => \App\Apps\NiurenSmssend\Models\TemplateField::typeMap(),
            'defaultProvider' => niuren_smssend_current_provider(),
        ]);
    }

    /**
     * 模板列表数据（Layui table 数据源，支持关键字与服务商筛选）
     *
     * @param Request $request 请求对象
     * @return JsonResponse
     */
    public function list(Request $request): JsonResponse
    {
        if (! $this->hasPermission('niuren.smssend.template')) {
            return $this->permissionDenied();
        }

        $pageSize = (int) $request->input('limit', 15);
        $pageSize = max(1, min(100, $pageSize));

        $paginator = $this->service->paginate(
            $request->input('keyword'),
            $request->input('provider'),
            $pageSize
        );

        $rows = collect($paginator->items())
            ->map(fn (SmsTemplate $template) => $template->toSummaryArray())
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
     * 启用模板下拉（发送页选择模板用）
     *
     * @param Request $request 请求对象
     * @return JsonResponse
     */
    public function options(Request $request): JsonResponse
    {
        if (! $this->hasPermission('niuren.smssend.send') && ! $this->hasPermission('niuren.smssend.template')) {
            return $this->permissionDenied();
        }

        $templates = $this->service->options($request->input('provider'));

        return response()->json(ApiResponse::success(['templates' => $templates]));
    }

    /**
     * 模板编辑回显（含解析出的变量与已配置字段）
     *
     * @param int $id 模板 id
     * @return JsonResponse
     */
    public function form(int $id): JsonResponse
    {
        if (! $this->hasPermission('niuren.smssend.template')) {
            return $this->permissionDenied();
        }

        try {
            $data = $this->service->form($id);
        } catch (SmsException $e) {
            return response()->json(ApiResponse::error(40401, $e->getMessage()));
        }

        return response()->json(ApiResponse::success($data));
    }

    /**
     * 模板详情（用于查看模板内容与字段搭配）
     *
     * @param int $id 模板 id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        if (! $this->hasPermission('niuren.smssend.template')) {
            return $this->permissionDenied();
        }

        try {
            $template = $this->service->find($id);
        } catch (SmsException $e) {
            return response()->json(ApiResponse::error(40401, $e->getMessage()));
        }

        $data = $template->toSummaryArray();
        $data['fields'] = $template->fields
            ->map(fn ($field) => $field->toFormArray())
            ->values()
            ->all();

        return response()->json(ApiResponse::success($data));
    }

    /**
     * 创建模板及其表单字段
     *
     * @param Request $request 请求对象
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        if (! $this->hasPermission('niuren.smssend.template')) {
            return $this->permissionDenied();
        }

        $validator = Validator::make($request->all(), $this->rules(), $this->messages(), $this->attributes());
        if ($validator->fails()) {
            return response()->json(ApiResponse::error(40001, (string) $validator->errors()->first()));
        }

        try {
            $this->service->create($request->all(), $this->normalizeFields($request));
        } catch (SmsException $e) {
            return response()->json(ApiResponse::error(40001, $e->getMessage()));
        }

        return response()->json(ApiResponse::success(null, '模板创建成功'));
    }

    /**
     * 更新模板及其表单字段（字段整体重建）
     *
     * @param Request $request 请求对象
     * @param int     $id      模板 id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        if (! $this->hasPermission('niuren.smssend.template')) {
            return $this->permissionDenied();
        }

        $validator = Validator::make($request->all(), $this->rules(), $this->messages(), $this->attributes());
        if ($validator->fails()) {
            return response()->json(ApiResponse::error(40001, (string) $validator->errors()->first()));
        }

        try {
            $this->service->update($id, $request->all(), $this->normalizeFields($request));
        } catch (SmsException $e) {
            return response()->json(ApiResponse::error(40001, $e->getMessage()));
        }

        return response()->json(ApiResponse::success(null, '模板保存成功'));
    }

    /**
     * 删除模板及其表单字段
     *
     * @param int $id 模板 id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        if (! $this->hasPermission('niuren.smssend.template')) {
            return $this->permissionDenied();
        }

        try {
            $this->service->delete($id);
        } catch (SmsException $e) {
            return response()->json(ApiResponse::error(40001, $e->getMessage()));
        }

        return response()->json(ApiResponse::success(null, '删除成功'));
    }

    // ──────────────────────────────────────────────────────────────
    // 内部工具
    // ──────────────────────────────────────────────────────────────

    /**
     * 模板保存校验规则
     *
     * @return array<string,string>
     */
    private function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:100',
            'alias' => 'nullable|string|max:100',
            'provider' => 'required|string|in:aliyun,tencent,volcano',
            'content' => 'required|string',
            'sign_name' => 'nullable|string|max:100',
            'remark' => 'nullable|string|max:255',
            'status' => 'nullable|integer|in:0,1',
            'fields' => 'nullable|array|max:50',
            'fields.*.var_name' => 'required|string|max:50',
            'fields.*.label' => 'required|string|max:100',
            'fields.*.field_type' => 'required|string|in:text,textarea,radio,select,number,date,checkbox,mobile',
            'fields.*.is_required' => 'nullable|integer|in:0,1',
            'fields.*.default_value' => 'nullable|string|max:255',
            'fields.*.placeholder' => 'nullable|string|max:255',
            'fields.*.options' => 'nullable|array',
            'fields.*.options.*.label' => 'nullable|string|max:100',
            'fields.*.options.*.value' => 'nullable|string|max:100',
        ];
    }

    /**
     * 校验错误中文消息
     *
     * @return array<string,string>
     */
    private function messages(): array
    {
        return [
            'name.required' => '请填写模板名称',
            'code.required' => '请填写模板 CODE',
            'provider.required' => '请选择短信服务商',
            'provider.in' => '短信服务商不合法',
            'content.required' => '请填写模板内容',
            'fields.*.var_name.required' => '表单字段的变量名不能为空',
            'fields.*.label.required' => '表单字段的名称不能为空',
            'fields.*.field_type.required' => '请选择表单字段的控件类型',
            'fields.*.field_type.in' => '表单字段的控件类型不合法',
        ];
    }

    /**
     * 校验字段中文名称
     *
     * @return array<string,string>
     */
    private function attributes(): array
    {
        return [
            'name' => '模板名称',
            'code' => '模板 CODE',
            'alias' => '模板标识',
            'provider' => '短信服务商',
            'content' => '模板内容',
            'sign_name' => '短信签名',
            'remark' => '备注',
        ];
    }

    /**
     * 规范化前端提交的表单字段数组
     *
     * @param Request $request 请求对象
     * @return array<int,array<string,mixed>>
     */
    private function normalizeFields(Request $request): array
    {
        $fields = $request->input('fields', []);

        return is_array($fields) ? array_values($fields) : [];
    }
}
