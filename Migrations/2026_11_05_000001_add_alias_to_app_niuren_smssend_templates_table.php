<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 短信发送 · 短信模板表增加应用级模板标识字段
 *
 * alias 为选填的全局唯一标识（区别于服务商侧模板 CODE），
 * 供其他应用按标识调用发送服务，不依赖自增 ID。
 * 留空存 NULL：唯一索引允许多个 NULL，多个空串会冲突。
 */
return new class extends Migration
{
    /**
     * 执行迁移：新增 alias 字段与全局唯一索引
     */
    public function up(): void
    {
        if (! Schema::hasTable('app_niuren_smssend_templates')) {
            return;
        }

        if (Schema::hasColumn('app_niuren_smssend_templates', 'alias')) {
            return;
        }

        Schema::table('app_niuren_smssend_templates', function (Blueprint $table) {
            $table->string('alias', 100)->nullable()->comment('应用级模板标识（供其他应用按标识调用），全局唯一')->after('code');
            $table->unique('alias', 'uk_niuren_smssend_templates_alias');
        });
    }

    /**
     * 回滚迁移：删除唯一索引与 alias 字段
     */
    public function down(): void
    {
        if (! Schema::hasTable('app_niuren_smssend_templates')) {
            return;
        }

        if (! Schema::hasColumn('app_niuren_smssend_templates', 'alias')) {
            return;
        }

        Schema::table('app_niuren_smssend_templates', function (Blueprint $table) {
            $table->dropUnique('uk_niuren_smssend_templates_alias');
            $table->dropColumn('alias');
        });
    }
};
