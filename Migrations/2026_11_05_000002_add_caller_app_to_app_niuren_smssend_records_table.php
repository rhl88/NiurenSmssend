<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 短信发送 · 发送记录表增加调用方应用标识字段
 *
 * caller_app 记录发起本次发送的应用标识（由调用方通过服务层参数主动传入），
 * 空串表示后台手动发送，用于发送记录列表筛选与问题排查。
 */
return new class extends Migration
{
    /**
     * 执行迁移：新增 caller_app 字段与普通索引
     */
    public function up(): void
    {
        if (! Schema::hasTable('app_niuren_smssend_records')) {
            return;
        }

        if (Schema::hasColumn('app_niuren_smssend_records', 'caller_app')) {
            return;
        }

        Schema::table('app_niuren_smssend_records', function (Blueprint $table) {
            $table->string('caller_app', 100)->default('')->comment('调用方应用标识（空为后台手动发送）')->after('operator_name');
            $table->index('caller_app', 'idx_niuren_smssend_records_caller');
        });
    }

    /**
     * 回滚迁移：删除索引与 caller_app 字段
     */
    public function down(): void
    {
        if (! Schema::hasTable('app_niuren_smssend_records')) {
            return;
        }

        if (! Schema::hasColumn('app_niuren_smssend_records', 'caller_app')) {
            return;
        }

        Schema::table('app_niuren_smssend_records', function (Blueprint $table) {
            $table->dropIndex('idx_niuren_smssend_records_caller');
            $table->dropColumn('caller_app');
        });
    }
};
