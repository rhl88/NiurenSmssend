<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 短信发送 · 短信模板表迁移
 *
 * 存储对接短信服务商的模板信息：模板名称、模板 CODE、
 * 所属服务商、含 ${变量} 占位符的模板内容、可选签名覆盖。
 * 遵循《CmsPro-v5-应用开发文档》第七章迁移规范（幂等/注释/时间字段）。
 */
return new class extends Migration
{
    /**
     * 执行迁移：创建短信模板表
     */
    public function up(): void
    {
        if (Schema::hasTable('app_niuren_smssend_templates')) {
            return;
        }

        Schema::create('app_niuren_smssend_templates', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('主键ID');
            $table->string('name', 100)->comment('模板名称，如：体检通知');
            $table->string('code', 100)->comment('服务商侧模板 CODE，如：SMS_512651836');
            $table->string('provider', 20)->default('aliyun')->comment('所属服务商：aliyun-阿里云 tencent-腾讯云 volcano-火山引擎');
            $table->text('content')->comment('模板内容，含 ${变量名} 占位符');
            $table->string('sign_name', 100)->default('')->comment('模板签名（留空使用服务商配置的全局签名）');
            $table->string('remark', 255)->default('')->comment('备注说明');
            $table->unsignedTinyInteger('status')->default(1)->comment('状态：0-禁用 1-启用');
            $table->timestamp('create_time')->nullable()->comment('创建时间');
            $table->timestamp('update_time')->nullable()->comment('更新时间');

            $table->unique(['provider', 'code'], 'uk_niuren_smssend_templates_provider_code');
            $table->index('status', 'idx_niuren_smssend_templates_status');
        });

        // 表备注仅 MySQL 支持，SQLite 测试环境跳过
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE `app_niuren_smssend_templates` COMMENT '短信发送-短信模板表'");
        }
    }

    /**
     * 回滚迁移：删除短信模板表
     */
    public function down(): void
    {
        Schema::dropIfExists('app_niuren_smssend_templates');
    }
};