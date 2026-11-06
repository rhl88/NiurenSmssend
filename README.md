# 短信发送 应用文档

> 应用ID：`niuren.smssend` | 文档版本：1.0.0 | 应用版本：1.0.4 | 依赖：PHP ≥ 8.3, CmsPro ≥ 5.0.0

- 官方地址：https://www.cmspro.cn/apps/niuren.smssend
- 本应用是后台短信发送管理系统，提供多家云服务商短信配置、模板化发送与发送记录追踪，请先安装 [CMSPRO](https://www.cmspro.cn/help/2)

## 1. 应用概述

面向后台管理员的短信发送管理系统，解决短信服务商参数分散、模板变量填写易错、发送结果难以追溯的问题：

- **服务商配置**：阿里云 / 腾讯云 / 火山引擎三家参数在线配置与一键切换当前服务商；提供 SSL 证书状态检查、一键下载 CA 证书与应急关闭验证
- **模板管理**：短信模板增删改查，按 `${变量}` 解析模板内容，为每个变量搭配文本 / 数字 / 单选 / 多选等表单控件
- **模板化发送**：选择模板动态渲染表单，实时预览短信内容，按字段顺序拼装变量并调用服务商接口发送
- **发送记录**：按服务商 / 状态 / 关键字 / 日期范围检索，详情查看参数、回执、失败原因、服务商响应与请求接口信息
- **对外调用**：模板支持全局唯一的应用级标识，其他应用可按标识调用发送服务；发送记录落库调用方应用标识，便于追溯外部调用

## 2. 应用截图

> 截图来源：[官方应用页 · niuren.smssend](https://www.cmspro.cn/apps/niuren.smssend)（官网标注 v1.0.0）。每次发布与更新必须核对本节截图与官网「应用截图」是否一致，不一致时以官网为准同步更新。

![应用截图 1](https://www.cmspro.cn/uploads/appstore/2026/10/22/G2nwbD7QtSJajFyGbBYiCkIXO2YB3fHjedWILvLG.png)

![应用截图 2](https://www.cmspro.cn/uploads/appstore/2026/10/22/2FDajGcC3pXrUCPYyXDls9pdEKumxbnuiCXVAMFe.png)

![应用截图 3](https://www.cmspro.cn/uploads/appstore/2026/10/22/VqszEwOJ3KjsoaPV72uRvifPJ5XnuYpB3W7wExb1.png)

![应用截图 4](https://www.cmspro.cn/uploads/appstore/2026/10/22/uWq0rzX1p5iFduRtlobKAnzAvQggfPFiveQIxeyG.png)

![应用截图 5](https://www.cmspro.cn/uploads/appstore/2026/10/22/1yQbQIefIEnwahJUj5FNMYepV3WAEcEq7KwbtT8H.png)

![应用截图 6](https://www.cmspro.cn/uploads/appstore/2026/10/22/x926jni6vvr5GVWOphgYweTRF1x055xdBnKG6YM8.png)

![应用截图 7](https://www.cmspro.cn/uploads/appstore/2026/10/22/mDRSjT6fd12fzvbHG7cSjtelnUfu9fDuDJefTzIr.png)

## 3. 目录结构

```
NiurenSmssend/
├── Config/smssend.php                  # 默认配置兜底值
├── Contracts/SmsProviderInterface.php  # 短信服务商适配器契约
├── Controllers/
│   ├── Admin/                          # 后台控制器（发送 / 模板 / 记录 / 设置）
│   └── BaseAdminController.php         # 后台控制器基类（页面权限校验）
├── Models/                             # 模板、模板字段、发送记录模型
├── Services/                           # 业务层（模板 / 发送 / 记录）
├── Providers/                          # 服务商适配（阿里云 / 腾讯云 / 火山引擎）与 SSL 选项
├── Migrations/                         # 应用表迁移（app_niuren_smssend_ 前缀）
├── Routes/admin.php                    # 后台页面与数据接口路由
├── Tests/                              # 应用功能测试
├── Views/Admin/                        # 后台视图（发送 / 模板 / 记录 / 设置）
├── Exceptions/SmsException.php         # 短信业务异常
├── Hooks.php                           # 钩子注册
├── Install.php                         # 安装 / 卸载 / 升级逻辑
├── ServiceProvider.php                 # 路由 / 视图 / 配置注册
├── helpers.php                         # 应用级辅助函数
├── manifest.json                       # 应用元数据唯一声明源
├── README.md                           # 本文件
└── doc/                                # 应用文档
```

## 4. 应用文档

| 文档 | 说明 |
|------|------|
| [CmsPro-牛人短信-使用指南.md](doc/CmsPro-牛人短信-使用指南.md) | 安装、服务商配置、模板字段搭配、短信发送与记录查询的完整操作流程 |
| [CmsPro-牛人短信-特性清单.md](doc/CmsPro-牛人短信-特性清单.md) | 应用全部特性及其实现位置，便于验收与维护对照 |
| [CmsPro-牛人短信-扩展指南.md](doc/CmsPro-牛人短信-扩展指南.md) | 服务层调用、服务商适配扩展、钩子事件与错误处理约定 |
| [2026-11-05-对外调用能力加强-设计.md](doc/2026-11-05-对外调用能力加强-设计.md) | 模板应用级标识与调用方标识的设计说明 |
| [2026-11-05-对外调用能力加强-实现计划.md](doc/2026-11-05-对外调用能力加强-实现计划.md) | 对外调用能力的实现步骤与验证记录 |