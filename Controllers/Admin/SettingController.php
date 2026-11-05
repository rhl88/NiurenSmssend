<?php

namespace App\Apps\NiurenSmssend\Controllers\Admin;

use App\Apps\NiurenSmssend\Controllers\BaseAdminController;
use App\Apps\NiurenSmssend\Models\SmsTemplate;
use App\Http\Responses\ApiResponse;
use App\Models\ConfigGroup;
use App\Models\ConfigItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 短信发送 · 短信设置控制器
 *
 * 严格遵循开发文档「后台设置页规范」：
 * 1. 白名单强制：只处理 ALLOWED_KEYS 列出的键，禁止 $request->all() 落库；
 * 2. 敏感项明文存读：access_key_secret / secret_key 明文入库，读取时明文回显；
 * 3. 敏感项留空保持原值：编辑时留空表示保持原值，避免误清空；
 * 4. 保存后清理应用配置缓存，使 niuren_smssend_config 立即生效。
 *
 * 三家服务商配置各存一份，互不覆盖；current_provider 决定「当前启用」的服务商。
 */
class SettingController extends BaseAdminController
{
    /**
     * 配置项 code 前缀（app_ + app_id 点转下划线 + _）
     */
    private const CONFIG_PREFIX = 'app_niuren_smssend_';

    /**
     * 允许写入的配置键白名单（与 manifest.json config_groups.items[].name 完全一致）
     */
    private const ALLOWED_KEYS = [
        'current_provider',
        'ssl_verify',
        'ssl_ca_path',
        'aliyun_access_key_id',
        'aliyun_access_key_secret',
        'aliyun_sign_name',
        'aliyun_region_id',
        'aliyun_endpoint',
        'tencent_secret_id',
        'tencent_secret_key',
        'tencent_sms_sdk_app_id',
        'tencent_sign_name',
        'tencent_region',
        'tencent_endpoint',
        'volcano_access_key',
        'volcano_secret_key',
        'volcano_sign_name',
        'volcano_sms_account',
        'volcano_region',
        'volcano_endpoint',
    ];

    /**
     * 留空保持原值的敏感键（明文存储可回显；编辑时留空表示不改动）
     */
    private const SECRET_KEYS = [
        'aliyun_access_key_secret',
        'tencent_secret_key',
        'volcano_secret_key',
    ];

    /**
     * 设置页面
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('niuren.smssend::Admin.settings', [
            'providers' => SmsTemplate::providerMap(),
        ]);
    }

    /**
     * 读取当前配置
     *
     * 所有配置（含各服务商 secret）明文返回，供表单 value 回显。
     *
     * @return JsonResponse
     */
    public function getSettings(): JsonResponse
    {
        if (! $this->hasPermission('niuren.smssend.setting')) {
            return $this->permissionDenied();
        }

        $settings = [];

        foreach (self::ALLOWED_KEYS as $key) {
            $settings[$key] = (string) niuren_smssend_config($key, '');
        }

        // 当前启用服务商兜底：非三家中任一则回退阿里云
        $settings['current_provider'] = in_array(
            $settings['current_provider'],
            array_keys(SmsTemplate::providerMap()),
            true
        ) ? $settings['current_provider'] : SmsTemplate::PROVIDER_ALIYUN;

        return response()->json(ApiResponse::success($settings));
    }

    /**
     * 保存配置
     *
     * @param Request $request 请求对象
     * @return JsonResponse
     */
    public function update(Request $request): JsonResponse
    {
        if (! $this->hasPermission('niuren.smssend.setting')) {
            return $this->permissionDenied();
        }

        $validated = $request->validate([
            'current_provider' => 'required|string|in:aliyun,tencent,volcano',
            'ssl_verify' => 'nullable|string|in:0,1',
            'ssl_ca_path' => 'nullable|string|max:255',
            'aliyun_access_key_id' => 'nullable|string|max:255',
            'aliyun_access_key_secret' => 'nullable|string|max:255',
            'aliyun_sign_name' => 'nullable|string|max:100',
            'aliyun_region_id' => 'nullable|string|max:50',
            'aliyun_endpoint' => 'nullable|string|max:255',
            'tencent_secret_id' => 'nullable|string|max:255',
            'tencent_secret_key' => 'nullable|string|max:255',
            'tencent_sms_sdk_app_id' => 'nullable|string|max:50',
            'tencent_sign_name' => 'nullable|string|max:100',
            'tencent_region' => 'nullable|string|max:50',
            'tencent_endpoint' => 'nullable|string|max:255',
            'volcano_access_key' => 'nullable|string|max:255',
            'volcano_secret_key' => 'nullable|string|max:255',
            'volcano_sign_name' => 'nullable|string|max:100',
            'volcano_sms_account' => 'nullable|string|max:100',
            'volcano_region' => 'nullable|string|max:50',
            'volcano_endpoint' => 'nullable|string|max:255',
        ], [
            'current_provider.required' => '请选择当前启用的短信服务商',
            'current_provider.in' => '当前启用的服务商不合法',
        ], [
            'current_provider' => '当前启用服务商',
            'aliyun_access_key_id' => '阿里云 AccessKey ID',
            'aliyun_access_key_secret' => '阿里云 AccessKey Secret',
            'aliyun_sign_name' => '阿里云短信签名',
            'aliyun_region_id' => '阿里云地域',
            'aliyun_endpoint' => '阿里云接口地址',
            'tencent_secret_id' => '腾讯云 SecretId',
            'tencent_secret_key' => '腾讯云 SecretKey',
            'tencent_sms_sdk_app_id' => '腾讯云 SdkAppId',
            'tencent_sign_name' => '腾讯云短信签名',
            'tencent_region' => '腾讯云地域',
            'tencent_endpoint' => '腾讯云接口地址',
            'volcano_access_key' => '火山引擎 AccessKey',
            'volcano_secret_key' => '火山引擎 SecretKey',
            'volcano_sign_name' => '火山引擎短信签名',
            'volcano_sms_account' => '火山引擎短信服务账号',
            'volcano_region' => '火山引擎地域',
            'volcano_endpoint' => '火山引擎接口地址',
        ]);

        $groupMap = $this->resolveConfigGroupMap();

        foreach (self::ALLOWED_KEYS as $key) {
            // 敏感项：明文存储，留空则保持原值（避免误清空）
            if (in_array($key, self::SECRET_KEYS, true)) {
                $value = trim((string) ($validated[$key] ?? ''));
                if ($value === '') {
                    continue;
                }
                $this->saveConfigItem($key, $value, $groupMap);
                continue;
            }

            if (! array_key_exists($key, $validated)) {
                continue;
            }
            $this->saveConfigItem($key, (string) $validated[$key], $groupMap);
        }

        // 清理应用配置缓存，确保 helper 立即读到新值
        niuren_smssend_forget_config_cache(self::ALLOWED_KEYS);

        return response()->json(ApiResponse::success(null, '设置保存成功'));
    }

    // ──────────────────────────────────────────────────────────────
    // SSL 证书检查与 CA 证书安装
    // ──────────────────────────────────────────────────────────────

    /**
     * 检查 SSL 证书配置状态
     *
     * 汇总 php.ini（curl.cainfo / openssl.cafile）与应用级配置（ssl_verify / ssl_ca_path），
     * 按发送链路的实际判定顺序给出结论，供设置页「SSL 证书安全」卡片展示。
     *
     * @return JsonResponse
     */
    public function checkSsl(): JsonResponse
    {
        if (! $this->hasPermission('niuren.smssend.setting')) {
            return $this->permissionDenied();
        }

        $curlCainfo = trim((string) ini_get('curl.cainfo'));
        $opensslCafile = trim((string) ini_get('openssl.cafile'));
        $sslCaPath = trim((string) niuren_smssend_config('ssl_ca_path', ''));
        $sslVerify = trim((string) niuren_smssend_config('ssl_verify', '1'));

        $appCaExists = $sslCaPath !== '' && is_file(storage_path($sslCaPath));
        $iniCaExists = $curlCainfo !== '' && $this->safeFileExists($curlCainfo);

        // 状态判定顺序与 SslVerifyOptions::build() 保持一致
        if ($sslVerify === '0') {
            $status = 'disabled';
            $message = '当前已关闭 SSL 证书验证（不推荐，存在安全风险，仅限应急排查使用）';
        } elseif ($appCaExists) {
            $status = 'ok';
            $message = '使用应用级 CA 证书：' . $sslCaPath;
        } elseif ($iniCaExists) {
            $status = 'ok';
            $message = '使用 php.ini 配置的 CA 证书：' . $curlCainfo;
        } elseif ($curlCainfo !== '') {
            // 已配置 curl.cainfo 但应用内无法探测（如系统 CA 被 open_basedir 拦截），
            // libcurl 在 C 层读取 CA 不受 open_basedir 限制，路径正确时请求可正常验证
            $status = 'ok';
            $message = 'php.ini 已配置 curl.cainfo：' . $curlCainfo
                . '（受 open_basedir 限制无法在应用内校验文件，若路径正确则 HTTPS 请求可正常验证）';
        } else {
            $status = 'ok';
            $message = '未配置显式 CA 证书，将使用 curl 默认 CA。Linux 系统自带 CA（如 /etc/ssl/certs/）通常可直接验证；'
                . '如证书验证失败（cURL error 60/77），请点击「下载CA证书」或手动配置 php.ini 的 curl.cainfo';
        }

        return response()->json(ApiResponse::success([
            'status' => $status,
            'message' => $message,
            'curl_cainfo' => $curlCainfo,
            'openssl_cafile' => $opensslCafile,
            'ssl_verify' => $sslVerify,
            'ssl_ca_path' => $sslCaPath,
            'ca_file_exists' => $appCaExists,
        ]));
    }

    /**
     * 下载 CA 证书到 storage/app/niuren_smssend/certs/cacert.pem
     *
     * 证书来源为 curl 官方维护的 Mozilla CA 列表；成功后自动写入
     * ssl_ca_path 配置并清理配置缓存，下一次发送即生效。
     *
     * @return JsonResponse
     */
    public function downloadCa(): JsonResponse
    {
        if (! $this->hasPermission('niuren.smssend.setting')) {
            return $this->permissionDenied();
        }

        $dir = storage_path('app/niuren_smssend/certs');
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            return response()->json(ApiResponse::error(1, '无法创建证书目录：' . $dir));
        }

        $target = $dir . DIRECTORY_SEPARATOR . 'cacert.pem';

        try {
            $content = @file_get_contents('https://curl.se/ca/cacert.pem');

            if ($content === false || strlen($content) < 1000) {
                return response()->json(ApiResponse::error(1, 'CA 证书下载失败，请检查服务器外网访问（https://curl.se/ca/cacert.pem）'));
            }

            file_put_contents($target, $content);

            $relativePath = 'app/niuren_smssend/certs/cacert.pem';
            $this->saveConfigItem('ssl_ca_path', $relativePath, $this->resolveConfigGroupMap());
            niuren_smssend_forget_config_cache(['ssl_ca_path']);

            return response()->json(ApiResponse::success([
                'path' => $relativePath,
                'size' => strlen($content),
            ], 'CA 证书下载成功，已自动填入路径'));
        } catch (Throwable $e) {
            Log::error('[niuren.smssend] CA 证书下载失败：' . $e->getMessage());

            return response()->json(ApiResponse::error(1, 'CA 证书下载失败：' . $e->getMessage()));
        }
    }

    // ──────────────────────────────────────────────────────────────
    // 内部工具
    // ──────────────────────────────────────────────────────────────

    /**
     * 安全探测文件是否存在（规避 open_basedir 限制触发的警告异常）
     *
     * 宝塔等面板常用 open_basedir 限制站点目录，此时用 file_exists() 探测
     * 受限路径之外的文件（如 php.ini 中 curl.cainfo 指向的系统 CA）会触发
     * warning 并被框架转为 ErrorException 导致 500，此处对受限路径直接返回 false。
     *
     * @param string $path 待探测的文件路径
     * @return bool 文件是否存在（受限路径返回 false）
     */
    private function safeFileExists(string $path): bool
    {
        $openBasedir = ini_get('open_basedir');
        if ($openBasedir !== '' && $openBasedir !== false) {
            $allowed = false;
            foreach (explode(PATH_SEPARATOR, (string) $openBasedir) as $dir) {
                if ($dir !== '' && str_starts_with($path, rtrim($dir, '/\\') . DIRECTORY_SEPARATOR)) {
                    $allowed = true;
                    break;
                }
            }

            if (! $allowed) {
                return false;
            }
        }

        return file_exists($path);
    }

    /**
     * 写入单个配置项（存在则更新值，不存在按 manifest 结构补建）
     *
     * @param string            $key      配置键名（不含前缀）
     * @param string            $value    配置值（明文）
     * @param array<string,int> $groupMap 键名 → 配置组 ID 映射
     */
    protected function saveConfigItem(string $key, string $value, array $groupMap): void
    {
        $code = self::CONFIG_PREFIX . $key;
        $item = ConfigItem::where('code', $code)->first();

        if ($item !== null) {
            $item->update(['value' => $value]);

            return;
        }

        $groupId = $groupMap[$key] ?? 0;
        if ($groupId <= 0) {
            Log::warning('[niuren.smssend][setting] 配置项所属分组未找到，跳过补建：' . $code);

            return;
        }

        ConfigItem::create([
            'group_id' => $groupId,
            'name' => $key,
            'code' => $code,
            'value' => $value,
            'type' => 'text',
            'sort' => 0,
            'status' => 1,
        ]);
    }

    /**
     * 解析「配置键名 → 配置组 ID」映射（以 manifest.json 声明为基准）
     *
     * @return array<string,int>
     */
    protected function resolveConfigGroupMap(): array
    {
        $manifestPath = base_path('app/Apps/NiurenSmssend/manifest.json');

        if (! file_exists($manifestPath)) {
            return [];
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (! is_array($manifest)) {
            return [];
        }

        $normalizedAppId = str_replace('.', '_', 'niuren.smssend');
        $map = [];

        foreach ($manifest['config_groups'] ?? [] as $group) {
            $groupCode = 'app_' . $normalizedAppId . '_' . ($group['name'] ?? '');
            $groupId = (int) ConfigGroup::where('code', $groupCode)->value('id');

            if ($groupId <= 0) {
                continue;
            }

            foreach ($group['items'] ?? [] as $item) {
                if (isset($item['name'])) {
                    $map[$item['name']] = $groupId;
                }
            }
        }

        return $map;
    }
}