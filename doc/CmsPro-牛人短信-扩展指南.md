# CmsPro 牛人短信 · 扩展指南

> 版本：1.0.0 | 更新日期：2026-10-22
> 应用版本：1.0.3 | 应用 ID：niuren.smssend

本指南面向二次开发者，说明如何在「短信发送」应用基础上进行功能扩展：调用应用的服务层与辅助函数、新增短信服务商适配、对接钩子事件，以及统一的错误处理约定。

---

## 一、概述

应用的扩展面由三层构成：

| 层次 | 目录 | 扩展用途 |
|------|------|----------|
| 服务层 | `Services/` | 供其他模块或控制器复用模板、发送、记录能力 |
| 服务商适配层 | `Contracts/` + `Providers/` | 接入新的短信服务商，或替换现有实现 |
| 扩展点 | `helpers.php` + `Hooks.php` | 读取配置、监听发送事件 |

命名空间统一为 `App\Apps\NiurenSmssend\*`。所有对外可复用的能力均通过服务类暴露，请勿在业务代码中直接操作模型。

---

## 二、前置检查

进行扩展前请确认：

1. 应用已在后台安装并启用（迁移已执行、配置组与权限已写入）；
2. 新增文件一律位于 `app/Apps/NiurenSmssend/` 白名单目录内，禁止改动框架文件；
3. 若涉及数据表结构变更，需另建迁移并同步更新 `Install.php` 的迁移回滚逻辑；
4. 改动后同步更新 `doc/` 下的对应文档。

---

## 三、辅助函数

应用级辅助函数由 `ServiceProvider::register()` 通过 `require_once helpers.php` 加载，函数名均带 `niuren_smssend_` 前缀，可全局调用。

| 函数 | 签名 | 说明 |
|------|------|------|
| 读取配置 | `niuren_smssend_config(string $key, mixed $default = null): mixed` | 优先读数据库 `config_items`（键为 `app_niuren_smssend_{key}`），未命中回退 `config('apps.niuren.smssend.{key}')`；带 3600 秒缓存 |
| 当前服务商 | `niuren_smssend_current_provider(): string` | 返回 `aliyun` / `tencent` / `volcano`，非法值兜底 `aliyun` |
| 清配置缓存 | `niuren_smssend_forget_config_cache(array $keys): void` | 保存配置后清理指定键的缓存（`$keys` 为不含前缀的键名数组） |

示例：

```php
$accessKeyId = niuren_smssend_config('aliyun_access_key_id', '');
$provider    = niuren_smssend_current_provider();
niuren_smssend_forget_config_cache(['aliyun_access_key_id']);
```

> 说明：全部配置（含 `access_key_secret`、`secret_key`）均明文存储、明文读取，与后台设置页回显保持一致。

---

## 四、Service 层调用

### 1. TemplateService（`Services/TemplateService.php`）

| 方法 | 签名 | 说明 |
|------|------|------|
| 分页 | `paginate(?string $keyword, ?string $provider, int $pageSize = 15): LengthAwarePaginator` | 按关键字（名称 / CODE / 模板标识）与服务商筛选，`id` 倒序 |
| 下拉 | `options(?string $provider = null): array` | 启用状态模板的精简列表，元素含 `id/name/code/alias/provider` |
| 查询 | `find(int $id): SmsTemplate` | 返回模板（含字段）；不存在抛 `SmsException` |
| 按标识查询 | `findByAlias(string $alias): SmsTemplate` | 按应用级模板标识查询模板；不存在抛 `SmsException`，停用不在此拦截 |
| 表单回显 | `form(int $id): array` | 模板编辑数据（含 `variables` 与 `fields` 表单结构） |
| 创建 | `create(array $data, array $fields): SmsTemplate` | 事务内创建模板与字段 |
| 更新 | `update(int $id, array $data, array $fields): SmsTemplate` | 事务内更新模板并整体重建字段 |
| 删除 | `delete(int $id): void` | 事务内删除模板及其字段 |

`$fields` 为字段数组，每项可含：`var_name`、`label`、`field_type`、`options`、`default_value`、`placeholder`、`is_required`；`sort` 由服务层按数组顺序自增写入。

### 2. SendService（`Services/SendService.php`）

| 方法 | 签名 | 说明 |
|------|------|------|
| 发送 | `send(int $templateId, string $mobile, array $fieldValues, array $operator = [], string $callerApp = ''): array` | 返回发送后记录详情数组；`$callerApp`（调用方应用标识）落库到记录 `caller_app`，空串表示后台手动发送；校验失败或服务商发送失败抛 `SmsException`（此时记录已落 `failed`） |
| 按标识发送 | `sendByAlias(string $alias, string $mobile, array $fieldValues, array $operator = [], string $callerApp = ''): array` | 按应用级模板标识查找模板后走 `send()` 主流程；模板不存在抛 `SmsException`，停用校验由 `send()` 统一处理 |

`$fieldValues` 的键为**字段 ID**（多选控件传数组）；`$operator` 可传 `['id' => ..., 'name' => ...]`；`$callerApp` 为调用方应用标识，由调用方主动传入，落库到发送记录 `caller_app`（空串表示后台手动发送），便于排查外部调用问题。

发送流程：读取模板 → 状态校验 → 手机号校验 → 变量值组装 → 内容渲染 → 写入 `pending` 记录 → 调用服务商 → 更新记录为 `success`（失败则 `failed`）。

### 3. RecordService（`Services/RecordService.php`）

| 方法 | 签名 | 说明 |
|------|------|------|
| 分页 | `paginate(?string $provider = null, ?string $status = null, ?string $keyword = null, ?string $dateFrom = null, ?string $dateTo = null, int $pageSize = 15): LengthAwarePaginator` | 多条件筛选，日期含当日 `00:00:00` ~ `23:59:59`，`id` 倒序 |
| 详情 | `show(int $id): SmsRecord` | 单条记录；不存在抛 `SmsException` |

调用示例：

```php
use App\Apps\NiurenSmssend\Services\SendService;

$result = app(SendService::class)->sendByAlias('user.register.verify', '13800138000', $fieldValues, [
    'id'   => auth('admin')->id(),
    'name' => auth('admin')->user()->name ?? '',
], 'niuren.form'); // 第 5 参：调用方应用标识，落库到发送记录便于排查
```

---

## 五、服务商适配层

### 1. 适配器契约（`Contracts/SmsProviderInterface.php`）

| 方法 | 签名 | 说明 |
|------|------|------|
| 标识 | `code(): string` | 服务商标识（`aliyun` / `tencent` / `volcano`） |
| 名称 | `name(): string` | 服务商中文名 |
| 配置检查 | `isConfigured(): bool` | 关键参数是否已配置 |
| 缺失提示 | `missingConfigMessage(): string` | 未配置时给用户的提示 |
| 发送 | `send(string $mobile, string $signName, string $templateCode, array $params): array` | 返回 `['vendor_msg_id' => ..., 'biz_id' => ..., 'response' => ..., 'request_info' => ...]`；`request_info` 为接口地址 / 请求方式 / 业务参数（排查用，不含鉴权参数），异常时经 `SmsException::withRequestInfo()` 携带 |

### 2. 适配器工厂（`Providers/ProviderFactory.php`）

`ProviderFactory::make(string $provider)` 按标识返回适配器实例；`isSupported()` 判断是否受支持；`supportedProviders()` 返回完整映射。不支持的服务商将抛 `SmsException`。

### 3. 请求异常描述（`Providers/Support/HttpErrorFormatter.php`）

适配器调用服务商接口失败时，统一调用 `HttpErrorFormatter::describe(Throwable $e)` 生成描述：网络类异常（`ConnectionException`）提示「网络连接异常，请检查服务器网络与短信服务商接口地址（cURL error N）」；其余异常为「请求异常：」+ 截断后的原始消息（上限 200 字符）。

该方法**刻意不输出完整请求 URL**——阿里云签名参数（AccessKeyId / Signature / SignatureNonce）就拼在 URL 中，原样落库展示会造成敏感信息泄露。新增服务商时请一并复用。

### 4. SSL 证书验证选项（`Providers/Support/SslVerifyOptions.php`）

适配器发起 HTTPS 请求前统一调用 `SslVerifyOptions::build()` 生成 `Http::withOptions()` 的 verify 选项：后台 `ssl_verify` 关闭时返回 `verify=false`（应急排查用）；`ssl_ca_path` 配置且文件存在时返回 `verify=<storage 绝对路径>`（应用级 CA，不依赖 php.ini）；否则返回空数组沿用 PHP 默认。新增服务商时请一并复用，保持三家证书行为一致。

---

## 六、扩展指引：新增短信服务商

以新增「××云」为例：

**第 1 步：新增适配器**

在 `Providers/` 下新建 `XxcloudProvider.php`，实现 `SmsProviderInterface`：

```php
namespace App\Apps\NiurenSmssend\Providers;

use App\Apps\NiurenSmssend\Contracts\SmsProviderInterface;
use App\Apps\NiurenSmssend\Exceptions\SmsException;

class XxcloudProvider implements SmsProviderInterface
{
    public function code(): string { return 'xxcloud'; }

    public function name(): string { return '××云'; }

    public function isConfigured(): bool
    {
        return niuren_smssend_config('xxcloud_access_key') !== ''
            && niuren_smssend_config('xxcloud_secret_key') !== '';
    }

    public function missingConfigMessage(): string
    {
        return '××云短信未完成配置，请在「短信设置」中填写接入参数';
    }

    public function send(string $mobile, string $signName, string $templateCode, array $params): array
    {
        if (! $this->isConfigured()) {
            throw new SmsException($this->missingConfigMessage());
        }

        // TODO: 调用 ××云 短信接口，成功返回统一结构，失败抛 SmsException（附友好中文提示）
        return [
            'vendor_msg_id' => '',
            'biz_id'        => '',
            'response'      => [],
        ];
    }
}
```

**第 2 步：注册到工厂**

在 `Providers/ProviderFactory.php` 的 `PROVIDERS` 映射中加入 `'xxcloud' => XxcloudProvider::class`。

**第 3 步：补充标识与配置**

- 在 `Models/SmsTemplate.php` 的 `providerMap()` 中加入 `'xxcloud' => '××云'`；
- 在 `manifest.json` 中新增该服务商的 `config_groups`，并在 `Config/smssend.php` 补默认值；
- 在 `Controllers/Admin/SettingController.php` 的 `ALLOWED_KEYS` 中加入配置键，若含敏感字段则加入 `SECRET_KEYS`；
- 在 `helpers.php` 中 `niuren_smssend_current_provider()` 的合法值列表中加入 `xxcloud`；
- 涉及模板标识时注意：模板标识（alias）为**全局唯一**（跨服务商），与「同服务商下模板 CODE 唯一」相互独立，由 `TemplateService::assertAliasUnique()` 校验，冲突抛「模板标识已被使用」。

**第 4 步：同步界面与测试**

- 在 `Views/Admin/settings.blade.php` 增加对应配置卡片与下拉选项；
- 在 `Tests/Feature/ProviderTest.php` 补充适配器的签名与结果判定用例；
- 更新 `doc/` 下三份文档。

---

## 七、钩子扩展点

应用在 `Hooks.php` 中预留了钩子类，可在发送成功等时机接入自定义逻辑（如写入第三方日志、推送通知）。当前为预留实现，接入方式参考文件内注释的事件名约定（如 `niuren.smssend.sent`）。注册遵循 CmsPro 应用钩子的双通道去重机制，避免重复注册。

---

## 八、错误处理约定

- 所有业务异常统一使用 `App\Apps\NiurenSmssend\Exceptions\SmsException`（继承 `RuntimeException`）。
- 服务层在可预期的业务校验失败时抛出 `SmsException`，消息为**面向用户的中文提示**。
- 发送服务捕获 `SmsException` 与 `Throwable`：前者原样抛出（消息为面向用户的中文提示）；后者（意外异常，可能含 SQL、请求地址等底层细节）不向用户透出，全量写入日志并统一提示「短信发送失败，请稍后重试」。两种情况都会先把记录置为 `failed` 并留存 `error_message`。
- 适配器调用服务商接口的请求异常，统一经 `Providers/Support/HttpErrorFormatter::describe()` 归一化：网络类异常提示「网络连接异常」并保留 cURL 错误码，其余异常仅保留截断后的原始消息；**不输出完整请求 URL**，避免阿里云签名参数（AccessKeyId / Signature / SignatureNonce）泄露到发送记录。
- 写入发送记录时 `error_message` 按字段上限（500 字符）截断；写入失败仅记录日志，不会二次抛错覆盖原始业务异常。
- 适配器发起请求时同步组装 `request_info`（接口地址 / 请求方式 / 业务参数）：成功随返回值、失败经 `SmsException::withRequestInfo()` 携带，由发送服务落库到记录的 `request_info` 字段，供详情页排查；**不含 AccessKeyId / Signature 等鉴权参数**。
- 控制器统一 `try/catch (SmsException)`，转换为 `ApiResponse::error($message)`，业务码与页面提示一致。

新增功能时应沿用同一约定：业务校验抛 `SmsException`，避免向用户暴露底层异常细节。

---

## 九、注意事项

1. **白名单约束**：所有扩展文件必须位于 `app/Apps/NiurenSmssend/` 内，不得改动框架文件。
2. **配置同步**：`manifest.json`（后台可改配置的声明来源）与 `Config/smssend.php`（代码读取的兜底默认值）需同步维护。
3. **时间格式**：统一使用 `Y-m-d H:i:s`，模型层通过 `$casts` 与 `serializeDate` 保证输出格式。
4. **变量拼接**：同一变量的多控件按 `sort` 顺序无分隔符拼接，多选控件内部以「、」连接；调整拼接规则时须同步修改 `SendService::buildParams()` 与发送页预览逻辑。
5. **敏感信息**：扩展中不得硬编码密钥，一律通过 `niuren_smssend_config()` 读取配置。
6. **标识唯一性**：模板 CODE 同服务商唯一，模板标识（alias）全局唯一（跨服务商），两者相互独立；模板保存的唯一性校验统一由 `TemplateService` 处理。
7. **回归验证**：改动服务层或适配层后，务必执行 `php artisan test app/Apps/NiurenSmssend/Tests` 确认既有用例全部通过。