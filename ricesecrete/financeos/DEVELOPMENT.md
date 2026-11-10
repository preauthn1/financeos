# 开发指南

> 项目概述与快速开始见 [README.md](./README.md)  
> 架构设计见 [docs/ARCHITECTURE.md](./docs/ARCHITECTURE.md)  
> 接口文档见 [docs/API.md](./docs/API.md)  
> 数据库设计见 [docs/DATABASE.md](./docs/DATABASE.md)

---

## 一、环境搭建

### 1.1 后端 (Webman)

```bash
# 创建项目
composer create-project workerman/webman:~2.2.3 . --no-interaction

# 安装依赖
composer require illuminate/database illuminate/redis illuminate/events
composer require firebase/php-jwt vlucas/phpdotenv

# 配置环境变量
cp .env.example .env
# 编辑 .env，填入数据库、Redis、JWT 配置

# 创建数据库
mysql -u root -p -e "CREATE DATABASE webman_admin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 同步表结构与填充
php webman model
php database/seed.php

# 启动开发服务器
php webman start
```

### 1.2 前端 (Vue Element Plus Admin)

```bash
git clone https://github.com/kailong321200875/vue-element-plus-admin.git frontend
cd frontend
pnpm install

# 配置环境变量
echo "VITE_GLOB_API_URL=http://127.0.0.1:8787" > .env.development

pnpm run dev
```

---

## 二、后端开发

### 2.1 路由定义

```php
<?php
// config/route.php
declare(strict_types=1);

use Webman\Route;
use app\admin\controller\AuthController;
use app\admin\controller\AdminController;
use app\admin\controller\RoleController;
use app\admin\controller\PermissionController;
use app\admin\controller\ArticleController;
use app\admin\controller\CategoryController;
use app\admin\controller\SettingController;
use app\admin\controller\LogController;
use app\middleware\AuthMiddleware;
use app\middleware\AdminLogMiddleware;

// 无需认证
Route::post('/admin/login', [AuthController::class, 'login']);
Route::post('/admin/refresh', [AuthController::class, 'refresh']);

// 需要认证
Route::group('/admin', function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/menus', [AuthController::class, 'menus']);

    Route::get('/admins', [AdminController::class, 'index']);
    Route::post('/admins', [AdminController::class, 'store']);
    Route::put('/admins/{id}', [AdminController::class, 'update']);
    Route::delete('/admins/{id}', [AdminController::class, 'destroy']);
    Route::put('/admins/{id}/password', [AdminController::class, 'updatePassword']);

    Route::get('/roles', [RoleController::class, 'index']);
    Route::post('/roles', [RoleController::class, 'store']);
    Route::put('/roles/{id}', [RoleController::class, 'update']);
    Route::delete('/roles/{id}', [RoleController::class, 'destroy']);
    Route::get('/roles/{id}/permissions', [RoleController::class, 'permissions']);
    Route::put('/roles/{id}/permissions', [RoleController::class, 'assignPermissions']);

    Route::get('/permissions', [PermissionController::class, 'index']);
    Route::post('/permissions', [PermissionController::class, 'store']);
    Route::put('/permissions/{id}', [PermissionController::class, 'update']);
    Route::delete('/permissions/{id}', [PermissionController::class, 'destroy']);

    Route::get('/articles', [ArticleController::class, 'index']);
    Route::get('/articles/{id}', [ArticleController::class, 'show']);
    Route::post('/articles', [ArticleController::class, 'store']);
    Route::put('/articles/{id}', [ArticleController::class, 'update']);
    Route::delete('/articles/{id}', [ArticleController::class, 'destroy']);

    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

    Route::get('/settings', [SettingController::class, 'index']);
    Route::get('/settings/{key}', [SettingController::class, 'show']);
    Route::put('/settings', [SettingController::class, 'batchUpdate']);
    Route::put('/settings/{key}', [SettingController::class, 'update']);

    Route::get('/logs', [LogController::class, 'index']);
})->middleware([AuthMiddleware::class, AdminLogMiddleware::class]);
```

```php
<?php
// config/route.php — 前端公共接口
use Webman\Route;
use app\api\controller\ArticleController;

Route::get('/api/articles', [ArticleController::class, 'index']);
Route::get('/api/articles/{id}', [ArticleController::class, 'show']);
```

### 2.2 统一响应助手

```php
<?php
// support/Response.php
declare(strict_types=1);

function success(mixed $data = null, string $msg = 'success'): \Webman\Http\Response
{
    return json(['code' => 0, 'msg' => $msg, 'data' => $data]);
}

function fail(int $code, string $msg, int $httpStatus = 200): \Webman\Http\Response
{
    return json(['code' => $code, 'msg' => $msg, 'data' => null], $httpStatus);
}
```

### 2.3 认证服务

```php
<?php
// app/service/AuthService.php
declare(strict_types=1);

namespace app\service;

use app\model\AdminModel;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use support\Redis;

class AuthService
{
    public function login(string $username, string $password): array
    {
        $admin = AdminModel::where('username', $username)->where('status', 1)->first();
        if (!$admin || !password_verify($password, $admin->password)) {
            throw new \app\exception\BusinessException(42202, '用户名或密码错误');
        }

        $roles = $admin->roles()->pluck('slug')->toArray();
        $accessToken = $this->generateAccessToken($admin, $roles);
        $refreshToken = $this->generateRefreshToken($admin->id);

        return [
            'token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => (int) env('JWT_ACCESS_TTL', 7200),
            'user' => [
                'id' => $admin->id,
                'username' => $admin->username,
                'nickname' => $admin->nickname,
                'avatar' => $admin->avatar,
                'roles' => $roles,
            ],
        ];
    }

    public function refresh(string $refreshToken): array
    {
        $adminId = Redis::get("refresh_token:{$refreshToken}");
        if (!$adminId) {
            throw new \app\exception\BusinessException(40103, 'refresh_token 无效');
        }

        $admin = Admin::find($adminId);
        $roles = $admin->roles()->pluck('slug')->toArray();

        return [
            'token' => $this->generateAccessToken($admin, $roles),
            'expires_in' => (int) env('JWT_ACCESS_TTL', 7200),
        ];
    }

    public function logout(string $refreshToken): void
    {
        Redis::del("refresh_token:{$refreshToken}");
    }

    private function generateAccessToken(Admin $admin, array $roles): string
    {
        $payload = [
            'iss' => env('APP_NAME', 'webman-admin'),
            'sub' => $admin->id,
            'admin_id' => $admin->id,
            'username' => $admin->username,
            'roles' => $roles,
            'iat' => time(),
            'exp' => time() + (int) env('JWT_ACCESS_TTL', 7200),
        ];
        return JWT::encode($payload, env('JWT_SECRET'), 'HS256');
    }

    private function generateRefreshToken(int $adminId): string
    {
        $token = bin2hex(random_bytes(32));
        $ttl = (int) env('JWT_REFRESH_TTL', 604800);
        Redis::setex("refresh_token:{$token}", $ttl, $adminId);
        return $token;
    }
}
```

### 2.4 认证中间件

```php
<?php
// app/middleware/AuthMiddleware.php
declare(strict_types=1);

namespace app\middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;
use app\service\PermissionService;

class AuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $token = $request->header('Authorization');
        if (!$token || !str_starts_with($token, 'Bearer ')) {
            return fail(40101, '未登录');
        }

        try {
            $jwt = substr($token, 7);
            $decoded = JWT::decode($jwt, new Key(env('JWT_SECRET'), 'HS256'));

            $request->admin_id = $decoded->admin_id;
            $request->admin_roles = $decoded->roles ?? [];

            // 超级管理员跳过权限校验
            if (in_array('super_admin', $request->admin_roles, true)) {
                return $next($request);
            }

            $permissionService = new PermissionService();
            if (!$permissionService->checkApiPermission(
                $request->admin_id,
                $request->path(),
                $request->method()
            )) {
                return fail(40301, '无权限访问');
            }

            return $next($request);
        } catch (\Exception $e) {
            return fail(40102, 'Token 无效或已过期');
        }
    }
}
```

### 2.5 权限服务

```php
<?php
// app/service/PermissionService.php
declare(strict_types=1);

namespace app\service;

use app\model\PermissionModel;
use support\Redis;

class PermissionService
{
    public function checkApiPermission(int $adminId, string $path, string $method): bool
    {
        $permissions = $this->getAdminApiPermissions($adminId);

        foreach ($permissions as $perm) {
            if (strtoupper($perm['method']) !== strtoupper($method)) {
                continue;
            }
            if ($this->matchPath($perm['path'], $path)) {
                return true;
            }
        }
        return false;
    }

    public function getAdminPermissions(int $adminId): array
    {
        $cacheKey = "admin:permissions:{$adminId}";
        $cached = Redis::get($cacheKey);
        if ($cached) {
            return json_decode($cached, true);
        }

        $slugs = Permission::whereHas('roles.admins', fn($q) => $q->where('admins.id', $adminId))
            ->pluck('slug')
            ->toArray();

        Redis::setex($cacheKey, 3600, json_encode($slugs));
        return $slugs;
    }

    private function getAdminApiPermissions(int $adminId): array
    {
        return Permission::where('type', 'api')
            ->whereHas('roles.admins', fn($q) => $q->where('admins.id', $adminId))
            ->get(['path', 'method'])
            ->toArray();
    }

    private function matchPath(string $pattern, string $path): bool
    {
        $regex = '#^' . str_replace('\*', '[^/]+', preg_quote($pattern, '#')) . '$#';
        return (bool) preg_match($regex, $path);
    }
}
```

### 2.6 文章服务层

```php
<?php
// app/service/ArticleService.php
declare(strict_types=1);

namespace app\service;

use app\model\ArticleModel;

class ArticleService
{
    public function list(int $page, int $limit, array $filters = []): array
    {
        $query = ArticleModel::query();

        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }
        if (!empty($filters['keyword'])) {
            $query->where('title', 'like', '%' . $filters['keyword'] . '%');
        }

        $total = $query->count();
        $items = $query->with(['author:id,nickname', 'category:id,name'])
            ->orderBy('created_at', 'desc')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return ['total' => $total, 'items' => $items];
    }

    public function create(array $data, int $authorId): Article
    {
        $data['author_id'] = $authorId;
        return Article::create($data);
    }

    public function update(int $id, array $data): bool
    {
        $article = Article::find($id);
        if (!$article) {
            throw new \app\exception\BusinessException(40401, '文章不存在');
        }
        return $article->update($data);
    }

    public function delete(int $id): bool
    {
        $article = Article::find($id);
        if (!$article) {
            throw new \app\exception\BusinessException(40401, '文章不存在');
        }
        return $article->delete();
    }
}
```

### 2.7 跨域中间件

```php
<?php
// app/middleware/CorsMiddleware.php
declare(strict_types=1);

namespace app\middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

class CorsMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        if ($request->method() === 'OPTIONS') {
            return $this->addCorsHeaders(response('', 204));
        }

        $response = $next($request);
        return $this->addCorsHeaders($response);
    }

    private function addCorsHeaders(Response $response): Response
    {
        $origin = env('CORS_ORIGIN', '*');
        $response->withHeaders([
            'Access-Control-Allow-Origin' => $origin,
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
            'Access-Control-Max-Age' => '86400',
        ]);
        return $response;
    }
}
```

---

## 三、前端开发

### 3.1 API 请求封装

```typescript
// src/api/base.ts
import axios from 'axios';
import { useUserStore } from '@/stores/user';
import router from '@/router';

const api = axios.create({
  baseURL: import.meta.env.VITE_GLOB_API_URL,
  timeout: 15000,
});

api.interceptors.request.use((config) => {
  const userStore = useUserStore();
  if (userStore.token) {
    config.headers.Authorization = `Bearer ${userStore.token}`;
  }
  return config;
});

api.interceptors.response.use(
  (response) => {
    const { code, msg, data } = response.data;
    if (code === 0) return data;
    return Promise.reject(new Error(msg || '请求失败'));
  },
  async (error) => {
    const { response } = error;
    if (response?.data?.code === 40102) {
      // Token 过期，尝试刷新
      const userStore = useUserStore();
      try {
        await userStore.refreshToken();
        return api(error.config);
      } catch {
        userStore.logout();
        router.push('/login');
      }
    }
    if (response?.data?.code === 40101) {
      const userStore = useUserStore();
      userStore.logout();
      router.push('/login');
    }
    return Promise.reject(error);
  }
);

export default api;
```

### 3.2 用户状态管理

```typescript
// src/stores/user.ts
import { defineStore } from 'pinia';
import { ref } from 'vue';
import { login as loginApi, logout as logoutApi, refresh as refreshApi, getMe } from '@/api/auth';

export const useUserStore = defineStore('user', () => {
  const token = ref(localStorage.getItem('token') || '');
  const refreshToken = ref(localStorage.getItem('refresh_token') || '');
  const userInfo = ref<any>(null);
  const permissions = ref<string[]>([]);

  async function login(username: string, password: string) {
    const data = await loginApi({ username, password });
    token.value = data.token;
    refreshToken.value = data.refresh_token;
    localStorage.setItem('token', data.token);
    localStorage.setItem('refresh_token', data.refresh_token);
    userInfo.value = data.user;
  }

  async function refreshTokenAction() {
    const data = await refreshApi({ refresh_token: refreshToken.value });
    token.value = data.token;
    localStorage.setItem('token', data.token);
  }

  async function fetchUserInfo() {
    const data = await getMe();
    userInfo.value = data;
    permissions.value = data.permissions;
  }

  function logout() {
    token.value = '';
    refreshToken.value = '';
    userInfo.value = null;
    permissions.value = [];
    localStorage.removeItem('token');
    localStorage.removeItem('refresh_token');
  }

  function hasPermission(slug: string): boolean {
    return permissions.value.includes(slug);
  }

  return { token, userInfo, permissions, login, refreshToken: refreshTokenAction, fetchUserInfo, logout, hasPermission };
});
```

### 3.3 按钮权限指令

```typescript
// src/utils/permission.ts
import type { App, Directive } from 'vue';
import { useUserStore } from '@/stores/user';

const permission: Directive = {
  mounted(el, binding) {
    const userStore = useUserStore();
    if (!userStore.hasPermission(binding.value)) {
      el.parentNode?.removeChild(el);
    }
  },
};

export function setupPermission(app: App) {
  app.directive('permission', permission);
}
```

### 3.4 TypeScript 类型定义

```typescript
// src/types/article.ts
export interface Article {
  id: number;
  title: string;
  summary: string;
  content: string;
  category_id: number | null;
  status: 0 | 1;
  author_id: number;
  view_count: number;
  created_at: string;
  updated_at: string;
  author?: { id: number; nickname: string };
  category?: { id: number; name: string };
}

export interface ArticleListParams {
  page?: number;
  limit?: number;
  keyword?: string;
  status?: number | '';
  category_id?: number;
}

export interface PaginatedResult<T> {
  total: number;
  items: T[];
}
```

### 3.5 文章管理页面

```vue
<!-- src/views/article/index.vue -->
<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { getArticleList, deleteArticle } from '@/api/article';
import type { Article, ArticleListParams } from '@/types/article';

const loading = ref(false);
const list = ref<Article[]>([]);
const total = ref(0);
const queryParams = ref<ArticleListParams>({
  page: 1,
  limit: 10,
  keyword: '',
  status: '',
});

const loadData = async () => {
  loading.value = true;
  try {
    const res = await getArticleList(queryParams.value);
    list.value = res.items;
    total.value = res.total;
  } catch {
    ElMessage.error('加载失败');
  } finally {
    loading.value = false;
  }
};

const handleDelete = (id: number) => {
  ElMessageBox.confirm('确认删除该文章吗？', '提示', { type: 'warning' })
    .then(async () => {
      await deleteArticle(id);
      ElMessage.success('删除成功');
      loadData();
    });
};

onMounted(loadData);
</script>

<template>
  <div class="article-manage">
    <div class="search-bar">
      <el-input v-model="queryParams.keyword" placeholder="搜索标题" clearable />
      <el-select v-model="queryParams.status" placeholder="状态" clearable>
        <el-option label="已发布" :value="1" />
        <el-option label="草稿" :value="0" />
      </el-select>
      <el-button type="primary" @click="loadData">搜索</el-button>
      <el-button v-permission="'article:create'" type="success" @click="$router.push('/article/create')">
        新建文章
      </el-button>
    </div>

    <el-table :data="list" v-loading="loading">
      <el-table-column prop="id" label="ID" width="80" />
      <el-table-column prop="title" label="标题" min-width="200" />
      <el-table-column prop="category.name" label="分类" width="120" />
      <el-table-column prop="status" label="状态" width="100">
        <template #default="{ row }">
          <el-tag :type="row.status === 1 ? 'success' : 'info'">
            {{ row.status === 1 ? '已发布' : '草稿' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="view_count" label="阅读量" width="100" />
      <el-table-column prop="created_at" label="发布时间" width="180" />
      <el-table-column label="操作" width="180" fixed="right">
        <template #default="{ row }">
          <el-button v-permission="'article:update'" type="primary" size="small"
            @click="$router.push(`/article/edit/${row.id}`)">编辑</el-button>
          <el-button v-permission="'article:delete'" type="danger" size="small"
            @click="handleDelete(row.id)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-pagination
      :total="total"
      v-model:page-size="queryParams.limit"
      v-model:current-page="queryParams.page"
      @change="loadData"
    />
  </div>
</template>
```

---

## 四、开发规范

### 4.1 接口规范

统一响应格式（HTTP 状态码始终为 200，通过 `code` 区分业务结果）：

```json
{ "code": 0, "msg": "success", "data": {} }
{ "code": 40001, "msg": "错误描述", "data": null }
```

错误码详见 [docs/API.md](./docs/API.md#错误码)。

### 4.2 命名规范

| 类型 | 规范 | 示例 |
|------|------|------|
| PHP 控制器 | XxxController | ArticleController |
| PHP 服务 | XxxService | ArticleService |
| PHP 模型 | Xxx（单数） | Article |
| Vue 组件 | PascalCase | ArticleList.vue |
| API 文件 | 小写模块名 | article.ts |
| 权限 slug | 模块:操作 | article:create |

### 4.3 Git 工作流

```bash
git checkout -b feature/your-feature
git add .
git commit -m "feat: 添加文章管理功能"
git push origin feature/your-feature
```

提交信息格式：`feat` / `fix` / `docs` / `style` / `refactor` / `test` / `chore`

### 4.4 开发顺序

```
后端: 迁移 → 模型 → Service → 控制器 → 路由 → 中间件
前端: 类型定义 → API → Store → 页面 → 路由 → 权限指令
```

---

## 五、部署

### 5.1 后端部署

```bash
# 安装生产依赖
composer install --no-dev --optimize-autoloader

# 配置 .env（APP_DEBUG=false）
php webman migrate:run
php webman seed:run

# 守护进程启动
php webman start -d

# 平滑重启
php webman reload

# 停止
php webman stop
```

### 5.2 前端构建

```bash
cd frontend
pnpm run build:prod
# 产物在 frontend/dist/
```

### 5.3 Nginx 配置

```nginx
server {
    listen 80;
    server_name admin.example.com;

    # 前端静态文件
    root /var/www/admin/frontend/dist;
    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }

    # 后端 API 反向代理
    location /admin {
        proxy_pass http://127.0.0.1:8787;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }

    location /api {
        proxy_pass http://127.0.0.1:8787;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }

    # 上传文件
    location /uploads {
        alias /var/www/admin/public/uploads;
    }
}
```

---

## 六、常见问题

| 问题 | 解决方案 |
|------|----------|
| CORS 跨域报错 | 配置 `CORS_ORIGIN` 环境变量，确保 CorsMiddleware 已注册 |
| JWT Token 过期 | 前端拦截 40102 自动调用 `/admin/refresh` 刷新 |
| Webman 端口占用 | 修改 `.env` 中 `SERVER_LISTEN` 或 `config/server.php` |
| 前端构建失败 | 检查 Node >= 18，删除 `node_modules` 重新 `pnpm install` |
| 数据库连接失败 | 检查 `.env` 中 DB_* 配置，确认 MySQL 服务运行中 |
| 权限校验不生效 | 确认 permissions 表中有对应 type=api 记录，且角色已分配 |
| Redis 连接失败 | 检查 `.env` 中 REDIS_* 配置，确认 Redis 服务运行中 |
