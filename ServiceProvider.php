<?php

namespace App\Apps\NiurenSmssend;

use App\Services\HookManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

/**
 * 短信发送 · 服务提供者
 *
 * 对接阿里云/腾讯云/火山引擎短信 API 的短信发送管理系统应用入口，
 * 遵循《CmsPro-v5-应用开发文档》：
 * 1. 应用级 helpers.php 加载（register 阶段 require_once，保证控制器与视图可用）
 * 2. 应用配置合并（Config/smssend.php → config('apps.niuren.smssend')）
 * 3. 路由注册：admin 后台管理端（前缀 /admin/niuren/smssend）
 * 4. 视图命名空间注册（niuren.smssend:: → Views/）
 * 5. 钩子注册（ServiceProvider 通道，每次请求 boot 时生效）
 *
 * 本应用范围已确认：全部收敛到后台管理端（auth:admin），
 * 无前台展示端 / 用户中心端，无对外公开回调路由（短信为单向发送）。
 *
 * 注意：系统 AppServiceProvider 在 register 阶段注册本 Provider，
 * 在 $this->app->booted() 回调中延迟调用 boot()，因此 boot() 中可安全访问数据库。
 */
class ServiceProvider extends BaseServiceProvider
{
    /**
     * 应用根目录相对路径（基于 base_path()）
     */
    private const APP_PATH = 'app/Apps/NiurenSmssend';

    /**
     * 应用 ID（用于钩子归属标记、配置 code 前缀推导）
     */
    private const APP_ID = 'niuren.smssend';

    /**
     * 应用配置命名空间（config('apps.niuren.smssend.*')）
     */
    private const CONFIG_KEY = 'apps.niuren.smssend';

    /**
     * 视图命名空间（view('niuren.smssend::Admin.send.index')）
     */
    private const VIEW_NAMESPACE = 'niuren.smssend';

    /**
     * 注册阶段
     *
     * 只做「绑定」不做「执行」：加载辅助函数、合并默认配置。
     * 此阶段数据库连接可能尚未就绪，禁止在此查询数据库。
     */
    public function register(): void
    {
        // 加载应用级辅助函数（niuren_smssend_config 等）
        // require_once 保证多次注册幂等；函数内部亦有 function_exists 双保险
        $helpers = base_path(self::APP_PATH . '/helpers.php');
        if (file_exists($helpers)) {
            require_once $helpers;
        }

        // 合并应用默认配置：数据库 config_items 无记录时的兜底值
        $this->mergeConfigFrom(
            base_path(self::APP_PATH . '/Config/smssend.php'),
            self::CONFIG_KEY
        );
    }

    /**
     * 启动阶段
     *
     * 由系统在 $this->app->booted() 回调中调用，此时数据库、Session、
     * 路由容器均已就绪，可安全读取配置项决定路由注册策略。
     */
    public function boot(): void
    {
        $this->registerRoutes();
        $this->loadViews();
        $this->registerHooks();
    }

    /**
     * 注册全部路由
     *
     * 终端划分与中间件对照（详见开发文档第 8 章）：
     * - admin 后台：['web', 'auth:admin', 'permission:admin']，前缀 /admin/niuren/smssend
     *
     * 关键设计：本应用短信为单向发送，无外部服务回调，
     * 因此不注册公开路由，鉴权完全由后台登录态 + 权限中间件保证。
     */
    protected function registerRoutes(): void
    {
        // ── 后台管理端：始终通过主域名访问，挂权限中间件 ──
        Route::prefix('admin/niuren/smssend')
            ->middleware(['web', 'auth:admin', 'permission:admin'])
            ->name('niuren.smssend.admin.')
            ->group(base_path(self::APP_PATH . '/Routes/admin.php'));
    }

    /**
     * 注册视图命名空间
     *
     * 注册后可通过 view('niuren.smssend::Admin.send.index') 访问
     * app/Apps/NiurenSmssend/Views/Admin/send/index.blade.php。
     * 目录首字母大写（Admin），与视图规范一致。
     */
    protected function loadViews(): void
    {
        $this->loadViewsFrom(
            base_path(self::APP_PATH . '/Views'),
            self::VIEW_NAMESPACE
        );
    }

    /**
     * 注册钩子（ServiceProvider 通道）
     *
     * CmsPro 钩子注册存在两条通道，本应用双通道兼容：
     * 1. 系统通道：AppManagerService::registerAppHooks() 在应用「启用」时，
     *    读取 manifest.json 的 "hooks": "Hooks" 字段实例化并调用 register()。
     * 2. 应用通道（本方法）：每次请求 boot 时注册，保证进程重启后钩子仍在。
     *
     * HookManager 为单例，重复注册会导致回调执行两次，因此这里通过
     * getRegisteredHooks() 检查本应用是否已注册，避免重复。
     */
    protected function registerHooks(): void
    {
        try {
            /** @var HookManager $hookManager */
            $hookManager = $this->app->make(HookManager::class);

            $registered = $hookManager->getRegisteredHooks(self::APP_ID);
            if (! empty($registered['actions']) || ! empty($registered['filters'])) {
                return; // 系统通道已注册过，跳过
            }

            (new Hooks())->register($hookManager);
        } catch (\Throwable $e) {
            // 钩子注册失败不应阻断应用启动（如容器未绑定 HookManager 的极端场景）
        }
    }
}