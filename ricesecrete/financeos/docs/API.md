# API 接口文档

> **维护说明**：日常接口维护以 `doc/接口文档.md` 为准（广告刷单系统业务接口）。  
> 本文档保留系统管理（RBAC、内容、设置等）早期接口说明，供对照参考。

## 通用约定

### Base URL

```
开发环境: http://127.0.0.1:8787
```

### 请求头

| Header | 值 | 说明 |
|--------|-----|------|
| `Content-Type` | `application/json` | 请求体格式 |
| `Authorization` | `Bearer <access_token>` | 认证 Token（登录接口除外） |

### 响应格式

```json
{
  "code": 200,
  "msg": "success",
  "data": {}
}
```

| 字段 | 类型 | 说明 |
|------|------|------|
| code | int | `200` 成功，非 `200` 失败 |
| msg | string | 提示信息 |
| data | mixed | 响应数据，失败时为 `null` |

### 分页参数

列表接口统一支持以下查询参数：

| 参数 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| page | int | 1 | 页码 |
| limit | int | 10 | 每页条数（最大 100） |

### 分页响应

```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "total": 100,
    "items": []
  }
}
```

---

## 错误码

| code | 说明 |
|------|------|
| 0 | 成功 |
| 40101 | 未登录 / Token 缺失 |
| 40102 | Token 无效或已过期 |
| 40103 | refresh_token 无效 |
| 40301 | 无权限访问 |
| 40401 | 资源不存在 |
| 42201 | 参数校验失败 |
| 42202 | 用户名或密码错误 |
| 42203 | 用户名已存在 |
| 42204 | 角色标识已存在 |
| 42205 | 不能删除超级管理员 |
| 42206 | 不能删除最后一个超级管理员 |
| 50000 | 服务器内部错误 |

---

## 一、认证模块

### 1.1 登录

```
POST /admin/login
```

**无需认证**

**请求体：**

```json
{
  "username": "admin",
  "password": "admin123"
}
```

**响应：**

```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "token": "eyJhbGciOiJIUzI1NiJ9...",
    "refresh_token": "a1b2c3d4...",
    "expires_in": 7200,
    "user": {
      "id": 1,
      "username": "admin",
      "nickname": "超级管理员",
      "avatar": "",
      "roles": ["super_admin"]
    }
  }
}
```

### 1.2 刷新 Token

```
POST /admin/refresh
```

**无需认证**

**请求体：**

```json
{
  "refresh_token": "a1b2c3d4..."
}
```

**响应：**

```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "token": "eyJhbGciOiJIUzI1NiJ9...",
    "expires_in": 7200
  }
}
```

### 1.3 登出

```
POST /admin/logout
```

**需要认证**

**响应：**

```json
{
  "code": 200,
  "msg": "success",
  "data": null
}
```

### 1.4 获取当前用户信息

```
GET /admin/me
```

**需要认证**

**响应：**

```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "id": 1,
    "username": "admin",
    "nickname": "超级管理员",
    "avatar": "",
    "roles": [
      { "id": 1, "name": "超级管理员", "slug": "super_admin" }
    ],
    "permissions": [
      "article:list",
      "article:create",
      "article:update",
      "article:delete"
    ]
  }
}
```

### 1.5 获取当前用户菜单

```
GET /admin/menus
```

**需要认证**

**响应：**

```json
{
  "code": 200,
  "msg": "success",
  "data": [
    {
      "id": 1,
      "name": "仪表盘",
      "path": "/dashboard",
      "icon": "dashboard",
      "component": "views/dashboard/index",
      "children": []
    },
    {
      "id": 2,
      "name": "内容管理",
      "path": "/content",
      "icon": "document",
      "children": [
        {
          "id": 3,
          "name": "文章管理",
          "path": "/article",
          "icon": "edit",
          "component": "views/article/index",
          "children": []
        }
      ]
    }
  ]
}
```

---

## 二、管理员模块

### 2.1 管理员列表

```
GET /admin/admins
```

**权限：** `admin:list`

**查询参数：**

| 参数 | 类型 | 说明 |
|------|------|------|
| page | int | 页码 |
| limit | int | 每页条数 |
| keyword | string | 搜索用户名 / 昵称 |
| status | int | 状态：1-启用 0-禁用 |

**响应：**

```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "total": 10,
    "items": [
      {
        "id": 1,
        "username": "admin",
        "nickname": "超级管理员",
        "avatar": "",
        "status": 1,
        "roles": [{ "id": 1, "name": "超级管理员" }],
        "created_at": "2026-01-01 00:00:00"
      }
    ]
  }
}
```

### 2.2 创建管理员

```
POST /admin/admins
```

**权限：** `admin:create`

**请求体：**

```json
{
  "username": "editor",
  "password": "123456",
  "nickname": "编辑员",
  "status": 1,
  "role_ids": [2]
}
```

### 2.3 更新管理员

```
PUT /admin/admins/{id}
```

**权限：** `admin:update`

**请求体：**

```json
{
  "nickname": "高级编辑",
  "status": 1,
  "role_ids": [2, 3]
}
```

> 密码修改通过独立接口，不在此接口中处理。

### 2.4 删除管理员

```
DELETE /admin/admins/{id}
```

**权限：** `admin:delete`

### 2.5 修改密码

```
PUT /admin/admins/{id}/password
```

**权限：** `admin:update`（修改他人）或当前用户修改自己

**请求体：**

```json
{
  "old_password": "admin123",
  "new_password": "newpass123"
}
```

---

## 三、角色模块

### 3.1 角色列表

```
GET /admin/roles
```

**权限：** `role:list`

**查询参数：** page, limit, keyword

### 3.2 创建角色

```
POST /admin/roles
```

**权限：** `role:create`

**请求体：**

```json
{
  "name": "编辑员",
  "slug": "editor",
  "description": "负责内容编辑"
}
```

### 3.3 更新角色

```
PUT /admin/roles/{id}
```

**权限：** `role:update`

**请求体：**

```json
{
  "name": "高级编辑员",
  "description": "负责内容审核与编辑"
}
```

> `slug` 创建后不可修改。

### 3.4 删除角色

```
DELETE /admin/roles/{id}
```

**权限：** `role:delete`

### 3.5 获取角色权限

```
GET /admin/roles/{id}/permissions
```

**权限：** `role:list`

**响应：**

```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "permission_ids": [1, 2, 3, 5, 8]
  }
}
```

### 3.6 分配角色权限

```
PUT /admin/roles/{id}/permissions
```

**权限：** `role:assign`

**请求体：**

```json
{
  "permission_ids": [1, 2, 3, 5, 8]
}
```

---

## 四、权限模块

### 4.1 权限树

```
GET /admin/permissions
```

**权限：** `permission:list`

**查询参数：**

| 参数 | 类型 | 说明 |
|------|------|------|
| type | string | 筛选类型：menu / button / api |

**响应：**

```json
{
  "code": 200,
  "msg": "success",
  "data": [
    {
      "id": 1,
      "name": "系统管理",
      "slug": "system",
      "type": "menu",
      "path": "/system",
      "icon": "setting",
      "sort": 1,
      "children": [
        {
          "id": 2,
          "name": "管理员管理",
          "slug": "admin",
          "type": "menu",
          "path": "/system/admin",
          "component": "views/system/admin/index",
          "sort": 1,
          "children": [
            {
              "id": 3,
              "name": "新增管理员",
              "slug": "admin:create",
              "type": "button",
              "sort": 1,
              "children": []
            }
          ]
        }
      ]
    }
  ]
}
```

### 4.2 创建权限

```
POST /admin/permissions
```

**权限：** `permission:create`

**请求体：**

```json
{
  "name": "文章管理",
  "slug": "article",
  "type": "menu",
  "parent_id": 0,
  "path": "/article",
  "component": "views/article/index",
  "icon": "document",
  "sort": 10
}
```

### 4.3 更新权限

```
PUT /admin/permissions/{id}
```

**权限：** `permission:update`

### 4.4 删除权限

```
DELETE /admin/permissions/{id}
```

**权限：** `permission:delete`

> 存在子权限时不可删除。

---

## 五、文章模块

### 5.1 文章列表（后台）

```
GET /admin/articles
```

**权限：** `article:list`

**查询参数：**

| 参数 | 类型 | 说明 |
|------|------|------|
| page | int | 页码 |
| limit | int | 每页条数 |
| keyword | string | 标题搜索 |
| status | int | 1-已发布 0-草稿 |
| category_id | int | 分类 ID |

### 5.2 文章详情

```
GET /admin/articles/{id}
```

**权限：** `article:list`

### 5.3 创建文章

```
POST /admin/articles
```

**权限：** `article:create`

**请求体：**

```json
{
  "title": "文章标题",
  "content": "<p>文章内容</p>",
  "category_id": 1,
  "status": 1,
  "summary": "文章摘要"
}
```

### 5.4 更新文章

```
PUT /admin/articles/{id}
```

**权限：** `article:update`

### 5.5 删除文章

```
DELETE /admin/articles/{id}
```

**权限：** `article:delete`

### 5.6 文章列表（公共 API）

```
GET /api/article
```

**无需认证**

**查询参数：** page, limit, category_id, keyword

**响应：** 仅返回 `status = 1` 的已发布文章。

### 5.7 文章详情（公共 API）

```
GET /api/article/detail?id={id}
```

**无需认证**

> 访问时 `view_count` 自动 +1。

---

## 六、分类模块

### 6.1 分类列表

```
GET /admin/categories
```

**权限：** `category:list`

### 6.2 创建分类

```
POST /admin/categories
```

**权限：** `category:create`

**请求体：**

```json
{
  "name": "技术",
  "slug": "tech",
  "sort": 1
}
```

### 6.3 更新分类

```
PUT /admin/categories/{id}
```

**权限：** `category:update`

### 6.4 删除分类

```
DELETE /admin/categories/{id}
```

**权限：** `category:delete`

> 分类下存在文章时不可删除。

---

## 七、系统配置模块

### 7.1 配置列表

```
GET /admin/settings
```

**权限：** `setting:list`

### 7.2 获取单个配置

```
GET /admin/settings/{key}
```

**权限：** `setting:list`

**响应：**

```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "key": "site_name",
    "value": "Webman Admin",
    "description": "站点名称"
  }
}
```

### 7.3 更新配置

```
PUT /admin/settings/{key}
```

**权限：** `setting:update`

**请求体：**

```json
{
  "value": "新站点名称"
}
```

### 7.4 批量更新配置

```
PUT /admin/settings
```

**权限：** `setting:update`

**请求体：**

```json
{
  "settings": [
    { "key": "site_name", "value": "Webman Admin" },
    { "key": "site_logo", "value": "/uploads/logo.png" }
  ]
}
```

---

## 八、操作日志模块

### 8.1 日志列表

```
GET /admin/logs
```

**权限：** `log:list`

**查询参数：**

| 参数 | 类型 | 说明 |
|------|------|------|
| page | int | 页码 |
| limit | int | 每页条数 |
| admin_id | int | 操作人 ID |
| module | string | 模块名 |
| start_date | string | 开始日期 YYYY-MM-DD |
| end_date | string | 结束日期 YYYY-MM-DD |

**响应：**

```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "total": 50,
    "items": [
      {
        "id": 1,
        "admin_id": 1,
        "admin_name": "admin",
        "module": "article",
        "action": "create",
        "description": "创建文章：测试标题",
        "ip": "127.0.0.1",
        "created_at": "2026-01-01 12:00:00"
      }
    ]
  }
}
```

---

## 九、文件上传

### 9.1 获取 S3 预签名上传地址（后台）

```
POST /admin/upload/presign
```

**需要认证**

**请求体：**

```json
{
  "filename": "photo.jpg",
  "mime_type": "image/jpeg",
  "type": "image"
}
```

| 字段 | 类型 | 说明 |
|------|------|------|
| filename | string | 原始文件名 |
| mime_type | string | 文件 MIME 类型 |
| type | string | `image` / `document` |

**限制：**

- 图片：jpg/png/gif/webp，最大 5MB
- 文档：pdf/doc/docx，最大 10MB

**响应：**

```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "upload_url": "https://gamex3.s3.ap-east-1.amazonaws.com/images/2026/07/xxx.jpg?...",
    "key": "images/2026/07/xxx.jpg",
    "url": "http://gamex3.s3.ap-east-1.amazonaws.com/images/2026/07/xxx.jpg",
    "expires_in": 900
  }
}
```

**前端上传流程：**

1. 调用本接口获取 `upload_url`
2. 使用 `PUT` 方法将文件直接上传到 `upload_url`（请求头需带 `Content-Type`）
3. 上传完成后使用返回的 `url` 作为文件访问地址

### 9.2 通过 API 上传文件（公共接口）

```
POST /api/upload
```

**无需认证**

**请求：** `multipart/form-data`

| 字段 | 类型 | 说明 |
|------|------|------|
| file | File | 上传文件 |
| type | string | `image` / `document` |

**限制：** 同 9.1

**响应：**

```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "url": "http://gamex3.s3.ap-east-1.amazonaws.com/images/2026/07/abc123.jpg",
    "key": "images/2026/07/abc123.jpg",
    "filename": "photo.jpg",
    "size": 102400
  }
}
```
