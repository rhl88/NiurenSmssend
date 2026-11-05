<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 短信发送 · 模板表单字段表迁移
 *
 * 存储模板内容中每个 ${变量} 对应的表单控件配置：
 * 一个变量（var_name）允许配置多条记录（即多个控件），
 * 发送时按 sort 顺序将各控件取值拼接为该变量的最终值。
 * 例如 ${name} 可搭配「姓名(单行文本) + 性别(单选)」→ 拼接为「张三先生」。
 * 遵循《CmsPro-v5-应用开发文档》第七章迁移规范。
 */
return new class extends Migration
{
    /**
     * 执行迁移：创建模板表单字段表
     */
    public function up(): void
    {
        if (Schema::hasTable('app_niuren_smssend_template_fields')) {
            return;
        }

        Schema::create('app_niuren_smssend_template_fields', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('主键ID');
            $table->unsignedBigInteger('template_id')->comment('所属模板ID');
            $table->string('var_name', 50)->comment('变量名（不含 ${}），如 name；同变量可配置多条控件');
            $table->string('label', 100)->comment('字段标签，如：姓名');
            $table->string('field_type', 20)->default('text')->comment('控件类型：text/textarea/radio/select/number/date/checkbox/mobile');
            $table->text('options')->nullable()->comment('选项配置 JSON（radio/select/checkbox 用），如 [{"label":"男","value":"先生"}]');
            $table->string('default_value', 255)->default('')->comment('默认值');
            $table->string('placeholder', 255)->default('')->comment('输入提示');
            $table->unsignedTinyInteger('is_required')->default(1)->comment('是否必填：0-否 1-是');
            $table->unsignedInteger('sort')->default(0)->comment('同变量内控件拼接顺序（升序）');
            $table->unsignedTinyInteger('status')->default(1)->comment('状态：0-禁用 1-启用');
            $table->timestamp('create_time')->nullable()->comment('创建时间');
            $table->timestamp('update_time')->nullable()->comment('更新时间');

            $table->index('template_id', 'idx_niuren_smssend_fields_template');
            $table->index(['template_id', 'var_name'], 'idx_niuren_smssend_fields_var');
        });

        // 表备注仅 MySQL 支持，SQLite 测试环境跳过
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE `app_niuren_smssend_template_fields` COMMENT '短信发送-模板表单字段表'");
        }
    }

    /**
     * 回滚迁移：删除模板表单字段表
     */
    public function down(): void
    {
        Schema::dropIfExists('app_niuren_smssend_template_fields');
    }
};