<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 短信发送 · 发送记录表增加请求接口信息字段
 *
 * request_info 存储发送时调用的接口地址、请求方式与业务参数（JSON），
 * 用于在发送记录详情中排查「请求打到哪个接口、发了什么数据」。
 * 约定：仅记录端点与业务参数，不含 AccessKeyId / Signature 等鉴权参数。
 */
return new class extends Migration
{
    /**
     * 执行迁移：新增 request_info 字段
     */
    public function up(): void
    {
        if (! Schema::hasTable('app_niuren_smssend_records')) {
            return;
        }

        if (Schema::hasColumn('app_niuren_smssend_records', 'request_info')) {
            return;
        }

        Schema::table('app_niuren_smssend_records', function (Blueprint $table) {
            $table->text('request_info')->nullable()->comment('请求接口信息 JSON（url/method/params，不含鉴权参数）')->after('response');
        });
    }

    /**
     * 回滚迁移：删除 request_info 字段
     */
    public function down(): void
    {
        if (! Schema::hasTable('app_niuren_smssend_records')) {
            return;
        }

        if (! Schema::hasColumn('app_niuren_smssend_records', 'request_info')) {
            return;
        }

        Schema::table('app_niuren_smssend_records', function (Blueprint $table) {
            $table->dropColumn('request_info');
        });
    }
};
