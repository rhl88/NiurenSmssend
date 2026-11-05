# CmsPro 牛人短信 · 特性清单

> 版本：1.0.3 | 更新日期：2026-10-23
> 应用版本：1.0.3 | 应用 ID：niuren.smssend

本清单罗列「短信发送」应用已交付的全部能力，按「应用体系 / 路由与终端 / 数据层 / 服务商适配 / 业务模块 / 安全与健壮性 / 测试覆盖」七个维度组织，每条特性标注唯一实现位置，便于验收与维护对照。

---

## 一、应用体系

| # | 特性 | 说明 | 实现位置 |
|---|------|------|----------|
| 1 | 应用标识 | 应用 ID `niuren.smssend`，名称「短信发送」，版本 `1.0.3`，作者 Niuren | `app/Apps/NiurenSmssend/manifest.json` |
| 2 | 应用类映射 | `app_id_to_class_name('niuren.smssend')` → `NiurenSmssend`，命名空间 `App\Apps\NiurenSmssend` | `ServiceProvider.php` |
| 3 | 元数据单一来源 | 菜单、配置组、权限全部由 `manifest.json` 声明，安装时据此三向同步落库 | `manifest.json` |
| 4 | 依赖要求 | 运行要求 PHP `>=8.3`、CmsPro `>=5.0.0` | `manifest.json` |
| 5 | 服务提供者 | 注册辅助函数、合并应用配置、注册后台路由 / 视图命名空间 / 钩子 | `ServiceProvider.php` |
| 6 | 安装器 | 安装时执行迁移并播种默认配置；卸载时回滚迁移并清理配置项 | `Install.php` |
| 7 | 钩子扩展点 | 提供 `Hooks` 钩子类，预留 `niuren.smssend.sent` 等事件扩展位 | `Hooks.php` |
| 8 | 后台菜单 | 一级菜单「短信发送」（order 62），下辖发送短信 / 短信模板 / 发送记录 / 短信设置 4 个子菜单 | `manifest.json` |
| 9 | 权限点 | 4 个权限码：`niuren.smssend.setting` / `.template` / `.send` / `.record` | `manifest.json` |
| 10 | 配置组 | 4 个配置组共 18 项：短信发送设置(1) + 阿里云(5) + 腾讯云(6) + 火山引擎(6) | `manifest.json` |
| 11 | 配置兜底 | 数据库 `config_items` 尚未写入时，回退到应用默认配置，保证调用不中断 | `Config/smssend.php` |
| 12 | 视图命名空间 | 视图通过 `niuren.smssend::Admin.*` 引用，避免与框架或其他应用冲突 | `ServiceProvider.php` |

---

## 二、路由与终端

路由前缀统一为 `/admin/niuren/smssend`，中间件为 `['web', 'auth:admin', 'permission:admin']`，路由名统一以 `niuren.smssend.admin.` 开头；业务接口位于 `api` 子前缀下（`/admin/niuren/smssend/api/*`）。定义见 [admin.php](file:///e:/wwwroot/cmspro/code/app/Apps/NiurenSmssend/Routes/admin.php)。

| # | 特性 | 说明 | 路由 / 实现位置 |
|---|------|------|------------------|
| 1 | 应用入口 | 访问应用根路径时重定向至「发送短信」页 | `GET /admin/niuren/smssend/` → redirect `send.index` |
| 2 | 发送短信页 | 选择模板、填写动态表单并发送短信的页面 | `GET /admin/niuren/smssend/send` |
| 3 | 短信模板页 | 模板的增删改查与变量字段搭配页面 | `GET /admin/niuren/smssend/templates` |
| 4 | 发送记录页 | 发送记录的检索、列表与详情页面 | `GET /admin/niuren/smssend/records` |
| 5 | 短信设置页 | 服务商选择与三家对接参数配置页面 | `GET /admin/niuren/smssend/settings` |
| 6 | 模板列表接口 | Layui 列表数据（分页 / 关键字 / 服务商筛选） | `GET api/templates` |
| 7 | 模板下拉接口 | 返回启用状态模板的 `id/name/code/provider` | `GET api/templates/options` |
| 8 | 模板表单接口 | 返回模板详情及其字段控件定义，供发送页动态渲染 | `GET api/templates/{id}/form` |
| 9 | 模板详情接口 | 返回单个模板完整信息（含字段） | `GET api/templates/{id}` |
| 10 | 模板创建接口 | 新建模板并同步其变量字段 | `POST api/templates` |
| 11 | 模板更新接口 | 更新模板并重建其变量字段 | `PUT api/templates/{id}` |
| 12 | 模板删除接口 | 删除模板及其字段 | `DELETE api/templates/{id}` |
| 13 | 发送接口 | 按模板与字段值组装参数并调用服务商发送 | `POST api/send` |
| 14 | 记录列表接口 | 发送记录多条件检索与分页 | `GET api/records` |
| 15 | 记录详情接口 | 单条发送记录完整详情（含参数、响应、失败原因） | `GET api/records/{id}` |
| 16 | 设置读取接口 | 读取当前服务商与三家配置（用于回填表单） | `GET api/settings` |
| 17 | 设置保存接口 | 保存当前服务商与三家配置参数 | `PUT api/settings` |

---

## 三、数据层

应用使用独立表前缀 `app_niuren_smssend_`，共 3 张表；模型统一以 `create_time` / `update_time` 记录时间，`serializeDate` 输出格式为 `Y-m-d H:i:s`。

| # | 特性 | 说明 | 实现位置 |
|---|------|------|----------|
| 1 | 模板表 | 表 `app_niuren_smssend_templates`，字段含名称、CODE、服务商、模板内容、签名、备注、状态；`(provider, code)` 唯一索引 | `Migrations/2026_10_22_000001_create_app_niuren_smssend_templates_table.php` |
| 2 | 模板字段表 | 表 `app_niuren_smssend_template_fields`，字段含变量名、字段名称、控件类型、选项(JSON)、默认值、提示、必填、排序；`template_id` 与 `(template_id, var_name)` 索引 | `Migrations/2026_10_22_000002_create_app_niuren_smssend_template_fields_table.php` |
| 3 | 发送记录表 | 表 `app_niuren_smssend_records`，记录服务商、模板、手机号、签名、参数、内容、回执、状态、错误信息、响应、接口信息、操作人；手机号 / 状态 / 模板 / 时间索引 | `Migrations/2026_10_22_000003_create_app_niuren_smssend_records_table.php` |
| 4 | 请求接口信息字段 | `request_info`（TEXT JSON）：发送时调用的接口地址、请求方式与业务参数，成功与失败记录均落库；不含鉴权参数 | `Migrations/2026_10_23_000001_add_request_info_to_app_niuren_smssend_records_table.php` |
| 5 | 模板模型 | `SmsTemplate`：常量、字段关联、状态作用域、`extractVariables()` 解析 `${变量}`、`providerMap()`、`toSummaryArray()` | `Models/SmsTemplate.php` |
| 6 | 模板字段模型 | `TemplateField`：8 种控件类型常量、`options` 数组转换、`typeMap()`、`hasOptions()`、`optionList()`、`toFormArray()` | `Models/TemplateField.php` |
| 7 | 发送记录模型 | `SmsRecord`：状态/服务商/关键字作用域、`params`/`response`/`request_info` 数组转换、`statusMap()`、`toSummaryArray()`/`toDetailArray()` | `Models/SmsRecord.php` |
| 8 | 变量解析规则 | 模板内容按 `${变量名}` 解析，变量名须匹配 `[A-Za-z_][A-Za-z0-9_]*`，按首次出现顺序去重 | `Models/SmsTemplate.php` |

---

## 四、服务商适配（外部集成）

以接口 `SmsProviderInterface` 统一三家服务商调用契约，`ProviderFactory` 按标识实例化适配器；阿里云、腾讯云为完整实现，火山引擎为预留（恒抛提示，不做实际发送）。

| # | 特性 | 说明 | 实现位置 |
|---|------|------|----------|
| 1 | 适配器契约 | 定义 `code/name/isConfigured/missingConfigMessage/send`，统一返回 `{vendor_msg_id, biz_id, response, request_info}`；`request_info` 为发送时的接口地址 / 请求方式 / 业务参数 | `Contracts/SmsProviderInterface.php` |
| 2 | 接口信息采集 | 阿里云 / 腾讯云适配器成功返回与异常（`SmsException::withRequestInfo()`）均携带接口信息，不含鉴权参数 | `Providers/AliyunProvider.php`、`Providers/TencentProvider.php`、`Exceptions/SmsException.php` |
| 3 | 适配器工厂 | 按 `aliyun/tencent/volcano` 映射并实例化；不支持的服务商抛异常 | `Providers/ProviderFactory.php` |
| 4 | 阿里云适配 | RPC 风格 GET 请求，HMAC-SHA1 签名，接口版本 `2017-05-25`，Endpoint `dysmsapi.aliyuncs.com` | `Providers/AliyunProvider.php` |
| 5 | 阿里云结果判定 | `Code === 'OK'` 视为成功，成功回执取 `BizId`，`RequestId` 作为业务 ID | `Providers/AliyunProvider.php` |
| 6 | 腾讯云适配 | TC3-HMAC-SHA256 签名，POST JSON，接口版本 `2021-01-11`，Action `SendSms` | `Providers/TencentProvider.php` |
| 7 | 腾讯云结果判定 | `SendStatusSet[0].Code === 'Ok'` 视为成功，回执取 `SerialNo`；11 位手机号自动补 `+86` | `Providers/TencentProvider.php` |
| 8 | 火山引擎预留 | 未配置时提示补全配置，已配置时恒抛「预留能力，暂未开放发送」 | `Providers/VolcanoProvider.php` |
| 9 | 错误码友好化 | 各家适配器内置错误码 → 中文提示映射 | `Providers/*Provider.php` |
| 10 | 统一异常 | `SmsException` 承载发送侧异常，供服务层与控制器统一捕获 | `Exceptions/SmsException.php` |

---

## 五、业务模块

| # | 特性 | 说明 | 实现位置 |
|---|------|------|----------|
| 1 | 设置控制器 | 读取 / 保存当前服务商与三家配置；仅允许白名单键；敏感字段留空时保持原值 | `Controllers/Admin/SettingController.php` |
| 2 | 设置页视图 | 服务商切换卡片 + 三家配置卡片 + 启用徽标 + 保存 | `Views/Admin/settings.blade.php` |
| 3 | 模板控制器 | 模板列表 / 下拉 / 详情 / 表单 / 增删改 | `Controllers/Admin/TemplateController.php` |
| 4 | 模板服务 | 分页查询、CODE 唯一校验、字段同步、选项校验，写入以事务包裹 | `Services/TemplateService.php` |
| 5 | 模板管理视图 | 搜索卡 + Layui 表格 + 新建/编辑弹窗，弹窗内可解析变量并搭配多控件 | `Views/Admin/templates.blade.php` |
| 6 | 变量控件搭配 | 同 `var_name` 可配置多个控件（如姓名文本 + 性别单选），按排序拼接为一个变量值 | `Views/Admin/templates.blade.php` / `Services/TemplateService.php` |
| 7 | 发送控制器 | 加载服务商与默认服务商，接收模板与字段值并触发发送 | `Controllers/Admin/SendController.php` |
| 8 | 发送服务 | 模板校验 → 手机号校验 → 变量值组装 → 内容渲染 → 落记录 → 调用服务商 → 更新状态 | `Services/SendService.php` |
| 9 | 发送页视图 | 手机号 + 模板选择 + 动态表单 + 实时短信预览 + 立即发送 + 结果弹窗 | `Views/Admin/send.blade.php` |
| 10 | 记录控制器 | 记录列表（多条件）与详情（只读，无编辑删除） | `Controllers/Admin/RecordController.php` |
| 11 | 记录服务 | 分页查询（服务商 / 状态 / 关键字 / 日期范围），详情读取 | `Services/RecordService.php` |
| 12 | 记录页视图 | 搜索卡 + 列表表格 + 详情弹窗（基本信息 / 内容 / 参数 / 失败原因 / 服务商响应） | `Views/Admin/records.blade.php` |
| 13 | 变量值拼接 | 同一变量多控件按 `sort` 顺序无分隔符拼接；多选控件值以「、」连接 | `Services/SendService.php` |
| 14 | 内容渲染 | 以变量值 `str_replace` 替换模板中的 `${变量名}`，生成最终短信内容 | `Services/SendService.php` |

---

## 六、安全与健壮性

| # | 特性 | 说明 | 实现位置 |
|---|------|------|----------|
| 1 | 页面权限 | 控制器 `BaseAdminController` 提供 `hasPermission()`，无权限返回业务码 `40301` | `Controllers/BaseAdminController.php` |
| 2 | 中间件鉴权 | 所有页面与接口经 `auth:admin` 与 `permission:admin` 中间件 | `Routes/admin.php` |
| 3 | 按钮级权限 | 视图按 `data-permission` 隐藏无权操作的按钮 | `Views/Admin/*.blade.php` |
| 4 | 配置键白名单 | 设置保存仅接受 20 个白名单键，杜绝任意配置写入 | `Controllers/Admin/SettingController.php` |
| 5 | 敏感字段保护 | AccessKeySecret / SecretKey 留空提交时保持数据库原值，避免误清空 | `Controllers/Admin/SettingController.php` |
| 6 | 手机号校验 | 正则 `/^\+?\d{6,20}$/` 校验，空值与格式错误分别给出提示 | `Services/SendService.php` |
| 7 | 模板状态校验 | 停用模板禁止发送，抛「该短信模板已停用，无法发送」 | `Services/SendService.php` |
| 8 | 必填校验 | 必填字段为空时抛「请填写「字段名称」」，拦截不完整提交 | `Services/SendService.php` |
| 9 | CODE 唯一 | 同服务商下模板 CODE 唯一，重复抛「该服务商下已存在相同的模板 CODE」 | `Services/TemplateService.php` |
| 10 | 变量一致性 | 字段变量必须存在于模板内容，否则拒绝保存 | `Services/TemplateService.php` |
| 11 | 选项兜底 | 选项类控件至少需配置一个选项，否则拒绝保存 | `Services/TemplateService.php` |
| 12 | 事务一致性 | 模板与字段的写入 / 更新 / 删除以数据库事务包裹 | `Services/TemplateService.php` |
| 13 | 记录留痕 | 发送前先落 `pending` 记录，成功后置 `success`、失败置 `failed` 并留存错误与响应 | `Services/SendService.php` |
| 14 | 异常兜底 | 捕获 `SmsException` 与 `Throwable`，统一转为友好提示并保留失败记录 | `Services/SendService.php` |
| 15 | 配置缓存 | 配置读取走 `Cache::remember(3600)`，保存后按需清理；`current_provider` 非法值回退 `aliyun` | `helpers.php` |
| 16 | SSL 证书安全 | 设置页支持检查 CA 配置状态、一键下载 curl 官方 CA（自动填入应用级路径）；发送请求经 `SslVerifyOptions` 应用证书，不依赖服务器 php.ini；支持应急关闭验证 | `Controllers/Admin/SettingController.php`、`Providers/Support/SslVerifyOptions.php` |

---

## 七、测试覆盖

应用测试位于 `app/Apps/NiurenSmssend/Tests/`，使用 SQLite 内存库并假配置 / Http 假响应，覆盖服务层与适配器核心逻辑，共 **49 个用例 / 152 个断言** 全部通过。执行命令：`php artisan test app/Apps/NiurenSmssend/Tests`。

| # | 测试文件 | 用例数 | 覆盖内容 | 实现位置 |
|---|----------|--------|----------|----------|
| 1 | `TemplateServiceTest` | 10 | 模板分页 / 创建 / 更新 / 删除、CODE 唯一、变量一致性、选项校验、字段搭建 | `Tests/Feature/TemplateServiceTest.php` |
| 2 | `SendServiceTest` | 14 | 发送成功 / 失败落记录、手机号校验、模板停用、必填校验、变量拼接与内容渲染、失败原因截断与接口信息落库 | `Tests/Feature/SendServiceTest.php` |
| 3 | `ProviderTest` | 15 | 工厂映射、阿里云 / 腾讯云签名与结果判定、错误码映射、火山引擎预留、请求异常不泄露签名地址 | `Tests/Feature/ProviderTest.php` |
| 4 | `RecordServiceTest` | 6 | 记录分页与多条件筛选、详情读取与异常 | `Tests/Feature/RecordServiceTest.php` |
| 5 | `SslVerifyOptionsTest` | 4 | SSL 验证选项三分支：未配置沿用默认、关闭验证、应用级 CA 路径存在 / 失效回退 | `Tests/Feature/SslVerifyOptionsTest.php` |
| 6 | 测试支撑 | — | 统一 setup trait：迁移、配置注入、造数辅助方法 | `Tests/Support/NiurenSmssendSetup.php` |

---

## 附：数据流概览

```
发送短信页
  └─ GET  api/templates/options      → 加载启用模板下拉
  └─ GET  api/templates/{id}/form    → 加载模板字段定义
  └─ POST api/send                   → SendService::send()
        ├─ 模板校验 / 手机号校验 / 变量值组装 / 内容渲染
        ├─ 写入 pending 发送记录
        ├─ ProviderFactory::make() → Aliyun/Tencent/Volcano::send()
        └─ 更新记录为 success / failed
  └─ 发送记录页
        └─ GET api/records、api/records/{id} → RecordService
```