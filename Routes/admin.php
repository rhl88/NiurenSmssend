<?php

use App\Apps\NiurenSmssend\Controllers\Admin\RecordController;
use App\Apps\NiurenSmssend\Controllers\Admin\SendController;
use App\Apps\NiurenSmssend\Controllers\Admin\SettingController;
use App\Apps\NiurenSmssend\Controllers\Admin\TemplateController;
use Illuminate\Support\Facades\Route;

// ────────────────────────────────────────────────────────────
// 短信发送 - 后台管理端路由
// 挂载信息（由 ServiceProvider::registerRoutes() 指定）：
//   - 前缀：admin/niuren/smssend
//   - 中间件：['web', 'auth:admin', 'permission:admin']
//   - 路由名前缀：niuren.smssend.admin.
// 组织约定（开发文档第 5 章 + 应用视图规范）：
//   - 页面路由直接挂在根，返回 Blade 视图，由后台菜单 iframe 加载
//   - 数据接口统一放到 api/ 子前缀下，返回 JSON
//     （路径 admin/niuren/smssend/api 下命中系统 CSRF 白名单 admin/api 前缀，无需 CSRF Token）
//   - 路由参数带数字约束 {id} 使用 whereNumber，避免与静态路径冲突
// ────────────────────────────────────────────────────────────

// 一、页面路由（返回视图，由后台菜单 iframe 加载）
Route::get('/', fn () => redirect()->route('niuren.smssend.admin.send.index'))->name('home');
Route::get('/send', [SendController::class, 'index'])->name('send.index');
Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
Route::get('/records', [RecordController::class, 'index'])->name('records.index');
Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');

// 二、数据接口路由（返回 JSON）
Route::prefix('api')->name('api.')->group(function () {
    // 短信模板管理
    Route::get('/templates', [TemplateController::class, 'list'])->name('templates.list');
    Route::get('/templates/options', [TemplateController::class, 'options'])->name('templates.options');
    Route::get('/templates/{id}/form', [TemplateController::class, 'form'])->whereNumber('id')->name('templates.form');
    Route::get('/templates/{id}', [TemplateController::class, 'show'])->whereNumber('id')->name('templates.show');
    Route::post('/templates', [TemplateController::class, 'store'])->name('templates.store');
    Route::put('/templates/{id}', [TemplateController::class, 'update'])->whereNumber('id')->name('templates.update');
    Route::delete('/templates/{id}', [TemplateController::class, 'destroy'])->whereNumber('id')->name('templates.destroy');

    // 发送短信
    Route::post('/send', [SendController::class, 'send'])->name('send.send');

    // 发送记录
    Route::get('/records', [RecordController::class, 'list'])->name('records.list');
    Route::get('/records/{id}', [RecordController::class, 'show'])->whereNumber('id')->name('records.show');

    // 短信设置
    Route::get('/settings', [SettingController::class, 'getSettings'])->name('settings.get');
    Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');

    // SSL 证书检查与 CA 证书安装
    Route::get('/settings/check-ssl', [SettingController::class, 'checkSsl'])->name('settings.checkSsl');
    Route::post('/settings/download-ca', [SettingController::class, 'downloadCa'])->name('settings.downloadCa');
});