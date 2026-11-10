# Webman Admin System

基于 **Webman 2.2** + **Vue Element Plus Admin** 的全栈后台管理系统，采用前后端分离架构，内置 RBAC 权限管理。

## 技术栈

| 层级 | 技术 |
|------|------|
| 后端 | PHP 8.1+ / Webman ~2.2.3 / Eloquent ORM |
| 前端 | Vue 3 / Element Plus / Vite / Pinia / TypeScript |
| 数据库 | MySQL 8.0+ |
| 缓存 | Redis 7.0+ |
| 认证 | JWT (Bearer Token) |

## 功能模块

- **认证中心** — 登录、登出、Token 刷新、当前用户信息
- **管理员管理** — 管理员 CRUD、状态启停、角色分配
- **角色管理** — 角色 CRUD、权限分配
- **权限管理** — 菜单 / 按钮 / API 权限树形管理
- **文章管理** — 文章 CRUD、分类、草稿 / 发布
- **系统配置** — 键值对配置管理
- **操作日志** — 管理员操作审计

## 项目结构

```
.
├── app/                        # 后端应用
│   ├── admin/controller/       # 后台管理控制器
│   ├── api/controller/         # 前端公共 API 控制器
│   ├── model/                  # 数据模型（命名：XxxModel.php）
│   ├── service/                # 业务逻辑层
│   └── middleware/             # 中间件
├── config/                     # 后端配置
├── database/                   # 迁移与填充脚本
├── config/
│   ├── route.php               # 路由入口
│   └── routes/                 # 路由定义（admin.php / api.php）
├── public/                     # 静态资源
├── docs/                       # 项目文档
├── .env.example                # 环境变量模板
├── DEVELOPMENT.md              # 开发指南
└── README.md
```

## 快速开始

### 环境要求

- PHP >= 8.1（扩展：pdo_mysql、redis、mbstring、json）
- Composer 2.x
- MySQL >= 8.0
- Redis >= 7.0

### 1. 后端

```bash
# 安装依赖
composer install

# 配置环境变量
cp .env.example .env
# 编辑 .env 填入数据库、Redis、JWT 等配置

# 创建数据库
mysql -u root -p -e "CREATE DATABASE webman_admin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 根据模型 tableSchema 同步表结构（创建/增量更新字段与索引）
php webman model
# 或: composer migrate
# 同步单个模型: php webman model ArticleModel
# 生成模型存根: php webman model PostModel --make

# 填充初始数据
php database/seed.php
# 或: composer seed

# 扫描控制器 menuConfig 同步菜单与 API 权限
php webman menu
# 预览扫描结果: php webman menu --scan
# 重置权限数据: php webman menu --reseed

# 启动开发服务器 (Windows)
php windows.php

# 启动开发服务器 (Linux)
php start.php start
```

后端默认监听 `http://127.0.0.1:8787`。

### 2. 前端

```bash
git clone https://github.com/kailong321200875/vue-element-plus-admin.git frontend
cd frontend
pnpm install
echo "VITE_GLOB_API_URL=http://127.0.0.1:8787" > .env.development
pnpm run dev
```

### 3. 默认账号

| 用户名 | 密码 | 角色 |
|--------|------|------|
| admin | admin123 | 超级管理员 |

## 文档索引

| 文档 | 说明 |
|------|------|
| [DEVELOPMENT.md](./DEVELOPMENT.md) | 开发环境搭建、代码示例、规范与部署 |
| [docs/ARCHITECTURE.md](./docs/ARCHITECTURE.md) | 系统架构、认证流程、权限校验流程 |
| [docs/API.md](./docs/API.md) | 完整 REST API 接口文档 |
| [docs/DATABASE.md](./docs/DATABASE.md) | 数据库表结构、索引、迁移说明 |

## 接口约定

```json
{ "code": 0, "msg": "success", "data": {} }
{ "code": 40001, "msg": "错误描述", "data": null }
```

认证方式：请求头 `Authorization: Bearer <token>`

## License

MIT

## 测试

```bash
# 确保后端已启动 (php windows.php)
composer test
# 或: vendor/bin/phpunit
```

Postman 集合：`docs/postman/Webman-Admin.postman_collection.json`

## 部署

详见 [deploy/DEPLOY.md](./deploy/DEPLOY.md)

- Nginx 配置：`deploy/nginx/webman-admin.conf`
- 生产环境变量：`.env.production.example`
- 加密 PHAR 发布：`deploy/ENCRYPT_PHAR.md`
