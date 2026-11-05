<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 短信发送 · 短信发送记录表迁移
 *
 * 记录每次短信发送的服务商、模板、手机号、参数、渲染后内容、
 * 服务商返回结果与错误信息，用于发送记录查询与结果追踪。
 * 遵循《CmsPro-v5-应用开发文档》第七章迁移规范。
 */
return new class extends Migration
{
    /**
     * 执行迁移：创建短信发送记录表
     */
    public function up(): void
    {
        if (Schema::hasTable('app_niuren_smssend_records')) {
            return;
        }

        Schema::create('app_niuren_smssend_records', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('主键ID');
            $table->string('provider', 20)->default('aliyun')->comment('发送服务商：aliyun/tencent/volcano');
            $table->unsignedBigInteger('template_id')->default(0)->comment('所用模板ID（模板已删时为 0）');
            $table->string('template_code', 100)->default('')->comment('模板 CODE（快照）');
            $table->string('template_name', 100)->default('')->comment('模板名称（快照）');
            $table->string('mobile', 20)->comment('接收手机号');
            $table->string('sign_name', 100)->default('')->comment('实际使用的短信签名');
            $table->text('params')->nullable()->comment('模板参数 JSON（变量名 → 值）');
            $table->text('content')->nullable()->comment('渲染后的短信内容（占位符已替换）');
            $table->string('vendor_msg_id', 191)->nullable()->comment('服务商返回的消息ID（阿里云 BizId / 腾讯云 SerialNo）');
            $table->string('biz_id', 191)->nullable()->comment('服务商请求标识（如阿里云 RequestId / 腾讯云 RequestId）');
            $table->string('send_status', 20)->default('pending')->comment('发送状态：pending-待发送 success-成功 failed-失败');
            $table->string('error_code', 50)->default('')->comment('失败错误码');
            $table->string('error_message', 500)->default('')->comment('失败原因（面向用户）');
            $table->text('response')->nullable()->comment('服务商原始响应 JSON');
            $table->unsignedBigInteger('operator_id')->default(0)->comment('操作管理员ID');
            $table->string('operator_name', 50)->default('')->comment('操作管理员名称');
            $table->unsignedTinyInteger('status')->default(1)->comment('状态：0-禁用 1-启用');
            $table->timestamp('create_time')->nullable()->comment('创建时间');
            $table->timestamp('update_time')->nullable()->comment('更新时间');

            $table->index('mobile', 'idx_niuren_smssend_records_mobile');
            $table->index('send_status', 'idx_niuren_smssend_records_status');
            $table->index('template_id', 'idx_niuren_smssend_records_template');
            $table->index('create_time', 'idx_niuren_smssend_records_time');
        });

        // 表备注仅 MySQL 支持，SQLite 测试环境跳过
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE `app_niuren_smssend_records` COMMENT '短信发送-发送记录表'");
        }
    }

    /**
     * 回滚迁移：删除短信发送记录表
     */
    public function down(): void
    {
        Schema::dropIfExists('app_niuren_smssend_records');
    }
};
