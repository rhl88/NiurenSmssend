<?php

namespace App\Apps\NiurenSmssend;

use App\Models\ConfigGroup;
use App\Models\ConfigItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 短信发送 · 安装/卸载/升级脚本
 *
 * 遵循《CmsPro-v5-应用开发文档》第 12 章约定：
 * - install(): 迁移兜底 + 配置同步（seedDefaultConfigs）
 * - uninstall(): 迁移回滚兜底 + 配置清理（removeConfigs），禁止在此删除数据库表
 * - upgrade(): 迁移兜底 + 配置同步
 *
 * 配置 code 前缀与系统 AppInstallerService::registerConfigGroups() 保持一致：
 *   code = "app_" . str_replace('.', '_', $appId) . "_" . $name
 *   即本应用 item code = app_niuren_smssend_<key>，group code = app_niuren_smssend_<groupName>
 *   （与 helpers.php 中 niuren_smssend_config() 读取逻辑对齐）
 */
class Install
{
    /**
     * 应用 ID（与 manifest.json 一致）
     */
    private const APP_ID = 'niuren.smssend';

    /**
     * 配置项 code 前缀
     */
    private const CODE_PREFIX = 'app_niuren_smssend_';

    /**
     * 安装：先跑迁移兜底，再同步默认配置
     */
    public function install(): void
    {
        $this->runMigrations();
        $this->seedDefaultConfigs();
    }

    /**
     * 卸载：回滚迁移兜底 + 清理配置与缓存
     *
     * 注意：不得在此删除数据库表，表的删除交由系统 rollbackMigrations() 执行。
     */
    public function uninstall(): void
    {
        $this->rollbackMigrations();
        $this->removeConfigs();
        $this->forgetConfigCache();
    }

    /**
     * 升级：跑迁移兜底并同步配置结构（不覆盖用户已设置的 value）
     *
     * @param string $fromVersion 升级前版本
     * @param string $toVersion   升级后版本
     */
    public function upgrade(string $fromVersion, string $toVersion): void
    {
        $this->runMigrations();
        $this->seedDefaultConfigs();
    }

    /**
     * 迁移兜底：绕过 Artisan::call('migrate') 在 Windows 下的路径解析问题，
     * 直接 require 迁移文件并调用 up()，同时写入 migrations 表记录防止重复执行。
     */
    protected function runMigrations(): void
    {
        $migrationFiles = glob(__DIR__ . '/Migrations/*.php');

        if ($migrationFiles === false) {
            return;
        }

        sort($migrationFiles);

        foreach ($migrationFiles as $file) {
            $migrationName = pathinfo($file, PATHINFO_FILENAME);

            try {
                if (DB::table('migrations')->where('migration', $migrationName)->exists()) {
                    continue;
                }
            } catch (\Throwable $e) {
                // migrations 表不可读（安装初始化前）时，不阻断后续逻辑
                continue;
            }

            $migration = require $file;

            if (! method_exists($migration, 'up')) {
                continue;
            }

            try {
                $migration->up();

                DB::table('migrations')->insert([
                    'migration' => $migrationName,
                    'batch' => (int) DB::table('migrations')->max('batch') + 1,
                ]);
            } catch (\Throwable $e) {
                Log::error('NiurenSmssend 迁移执行失败', [
                    'app' => self::APP_ID,
                    'file' => basename($file),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * 迁移回滚兜底：逆序执行 down()，确保数据库表被清理。
     */
    protected function rollbackMigrations(): void
    {
        $migrationFiles = glob(__DIR__ . '/Migrations/*.php');

        if ($migrationFiles === false) {
            return;
        }

        foreach (array_reverse($migrationFiles) as $file) {
            $migrationName = pathinfo($file, PATHINFO_FILENAME);

            try {
                if (! DB::table('migrations')->where('migration', $migrationName)->exists()) {
                    continue;
                }
            } catch (\Throwable $e) {
                continue;
            }

            $migration = require $file;

            if (! method_exists($migration, 'down')) {
                continue;
            }

            try {
                $migration->down();

                DB::table('migrations')->where('migration', $migrationName)->delete();
            } catch (\Throwable $e) {
                Log::error('NiurenSmssend 迁移回滚失败', [
                    'app' => self::APP_ID,
                    'file' => basename($file),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * 以 manifest.json 为唯一基准同步配置组/配置项
     *
     * 三向同步：
     * - 新增配置项 → 创建记录，写入 manifest 默认值
     * - 保留配置项 → 仅更新结构（name/type/tips/sort），保留用户已设置的 value
     * - 移除配置项 → 清理 DB 残留记录
     */
    protected function seedDefaultConfigs(): void
    {
        if (! class_exists(ConfigGroup::class) || ! class_exists(ConfigItem::class)) {
            return;
        }

        $manifestPath = __DIR__ . '/manifest.json';
        if (! file_exists($manifestPath)) {
            return;
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        $groups = $manifest['config_groups'] ?? [];

        if (! is_array($groups)) {
            return;
        }

        $manifestCodes = [];

        foreach ($groups as $groupDef) {
            $groupName = (string) ($groupDef['name'] ?? '');
            $groupCode = self::CODE_PREFIX . $groupName;

            $group = ConfigGroup::updateOrCreate(
                ['code' => $groupCode],
                [
                    'name' => (string) ($groupDef['title'] ?? $groupName),
                    'app_id' => self::APP_ID,
                    'sort' => 0,
                    'status' => 1,
                ]
            );

            $items = $groupDef['items'] ?? [];
            foreach ($items as $index => $itemDef) {
                $itemName = (string) ($itemDef['name'] ?? '');
                $fullCode = self::CODE_PREFIX . $itemName;
                $manifestCodes[] = $fullCode;

                $existing = ConfigItem::where('code', $fullCode)->first();

                if ($existing) {
                    $existing->update([
                        'group_id' => $group->id,
                        'name' => (string) ($itemDef['title'] ?? $itemName),
                        'type' => (string) ($itemDef['type'] ?? 'text'),
                        'tips' => (string) ($itemDef['tips'] ?? ''),
                        'sort' => $index,
                        'status' => 1,
                    ]);
                } else {
                    ConfigItem::create([
                        'code' => $fullCode,
                        'group_id' => $group->id,
                        'name' => (string) ($itemDef['title'] ?? $itemName),
                        'value' => (string) ($itemDef['value'] ?? ''),
                        'type' => (string) ($itemDef['type'] ?? 'text'),
                        'tips' => (string) ($itemDef['tips'] ?? ''),
                        'sort' => $index,
                        'status' => 1,
                    ]);
                }
            }
        }

        // 清理 manifest 中已删除的配置项（防残留）
        ConfigItem::where('code', 'like', self::CODE_PREFIX . '%')
            ->whereNotIn('code', $manifestCodes)
            ->delete();

        // 清理 manifest 中已删除的配置组（无子项的组）
        ConfigGroup::where('app_id', self::APP_ID)
            ->whereDoesntHave('items')
            ->delete();

        $this->forgetConfigCache();
    }

    /**
     * 卸载时全量清理本应用配置组与配置项（不可逆）
     */
    protected function removeConfigs(): void
    {
        if (! class_exists(ConfigGroup::class) || ! class_exists(ConfigItem::class)) {
            return;
        }

        $groupIds = ConfigGroup::where('app_id', self::APP_ID)->pluck('id');

        if ($groupIds->isNotEmpty()) {
            ConfigItem::whereIn('group_id', $groupIds->toArray())->delete();
            ConfigGroup::whereIn('id', $groupIds->toArray())->delete();
        }
    }

    /**
     * 清除本应用配置缓存，使读取函数立即反映最新配置
     */
    protected function forgetConfigCache(): void
    {
        $keys = [
            'current_provider',
            'aliyun_access_key_id', 'aliyun_access_key_secret', 'aliyun_sign_name',
            'aliyun_region_id', 'aliyun_endpoint',
            'tencent_secret_id', 'tencent_secret_key', 'tencent_sms_sdk_app_id',
            'tencent_sign_name', 'tencent_region', 'tencent_endpoint',
            'volcano_access_key', 'volcano_secret_key', 'volcano_sign_name',
            'volcano_sms_account', 'volcano_region', 'volcano_endpoint',
        ];

        foreach ($keys as $key) {
            Cache::forget('niuren_smssend:config:' . $key);
        }
    }
}