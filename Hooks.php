<?php

namespace App\Apps\NiurenSmssend;

use App\Services\HookManager;

/**
 * 短信发送 · 钩子
 *
 * 本应用功能全部收敛到后台管理端（设置/模板/发送/记录），
 * 当前无对外广播的业务钩子，register() 保留为空实现以满足 manifest.json
 * "hooks": "Hooks" 声明与系统 AppInstallerService::registerAppHooks() 的实例化要求。
 *
 * 后续若需将「短信发送成功/失败」广播给其他应用或挂载点，可在此声明并实现：
 *   $hooks->registerAction('niuren.smssend.sent', [$this, 'onSent'], 10, 'niuren.smssend');
 */
class Hooks
{
    /**
     * 注册钩子（当前为空实现）
     *
     * @param HookManager $hooks 钩子管理器单例
     */
    public function register(HookManager $hooks): void
    {
        // 预留：暂无广播钩子
    }
}