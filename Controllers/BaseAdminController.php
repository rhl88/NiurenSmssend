<?php

namespace App\Apps\NiurenSmssend\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * 短信发送 · 后台控制器基类
 *
 * 统一后台控制器的权限校验（后端兜底，防止越权写操作）。
 * 系统 AdminUser::hasPermission() 内部已对 super_admin 角色直接放行，
 * 前端 data-permission 隐藏按钮仅是体验优化，后端校验才是安全保障。
 * 各 Admin/* 控制器继承本基类，避免重复实现权限方法。
 */
abstract class BaseAdminController extends Controller
{
    /**
     * 校验当前管理员是否具备指定权限
     *
     * @param string $code 权限码（对应 manifest.json 的 permissions[].code）
     * @return bool true=有权限
     */
    protected function hasPermission(string $code): bool
    {
        /** @var \App\Models\AdminUser|null $user */
        $user = auth('admin')->user();

        if ($user === null) {
            return false;
        }

        return $user->hasPermission($code);
    }

    /**
     * 无权操作的标准错误响应（HTTP 200，业务码 40301）
     *
     * @param string $message 提示文案
     * @return JsonResponse
     */
    protected function permissionDenied(string $message = '无权执行该操作'): JsonResponse
    {
        return response()->json(ApiResponse::error(40301, $message));
    }
}