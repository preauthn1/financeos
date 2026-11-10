<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as DB;
use app\support\Menu\AdminMenuSynchronizer;

return new class {
    public function run(): void
    {
        $rolesSeeded = DB::table('sy_roles')->where('slug', 'super_admin')->exists();

        if (!$rolesSeeded) {
            echo "  -> Seeding roles...\n";
            $superAdminRoleId = DB::table('sy_roles')->insertGetId([
                'name' => '超级管理员',
                'slug' => 'super_admin',
                'description' => '拥有所有权限',
                'data_scope' => 'all',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $editorRoleId = DB::table('sy_roles')->insertGetId([
                'name' => '编辑员',
                'slug' => 'editor',
                'description' => '负责内容编辑',
                'data_scope' => 'self',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            echo "  -> Seeding permissions...\n";
            (new AdminMenuSynchronizer())->sync(false);

            echo "  -> Seeding admin user...\n";
            $adminId = DB::table('sy_admins')->insertGetId([
                'username' => 'admin',
                'password' => password_hash('admin123', PASSWORD_BCRYPT),
                'nickname' => '超级管理员',
                'avatar' => '',
                'status' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            DB::table('sy_admin_roles')->insert(['admin_id' => $adminId, 'role_id' => $superAdminRoleId]);

            \database\support\PermissionSeeder::assignEditorPermissions($editorRoleId);
        }

        if (!$rolesSeeded) {
            echo "  -> Seeding dicts...\n";
            $articleStatusTypeId = DB::table('sy_dict_types')->insertGetId([
                'name' => '文章状态',
                'code' => 'article_status',
                'status' => 1,
                'remark' => '文章发布状态',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            DB::table('sy_dict_items')->insert([
                ['dict_type_id' => $articleStatusTypeId, 'label' => '草稿', 'value' => '0', 'sort' => 0, 'status' => 1, 'remark' => '', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
                ['dict_type_id' => $articleStatusTypeId, 'label' => '已发布', 'value' => '1', 'sort' => 1, 'status' => 1, 'remark' => '', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ]);

            echo "  -> Seeding welcome notification...\n";
            DB::table('sy_admin_notifications')->insert([
                'admin_id' => 0,
                'title' => '欢迎使用后台管理系统',
                'content' => '系统已启用登录安全、Token 会话管理、数据权限与字典管理等功能。',
                'type' => 'announcement',
                'is_read' => 0,
                'read_at' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            echo "  -> Seeding settings...\n";
            DB::table('sy_settings')->insert([
                ['key' => 'site_name', 'value' => json_encode('Webman Admin'), 'description' => '站点名称', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
                ['key' => 'site_logo', 'value' => json_encode(''), 'description' => '站点 Logo', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
                ['key' => 'site_description', 'value' => json_encode('后台管理系统'), 'description' => '站点描述', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
                ['key' => 's3_config', 'value' => json_encode([
                    'credentials_key' => '',
                    'credentials_secret' => '',
                    'region' => 'ap-east-1',
                    'bucket' => '',
                    'url' => '',
                    'proxy' => null,
                    'presign_expires' => 900,
                ], JSON_UNESCAPED_UNICODE), 'description' => 'S3 对象存储配置', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ]);
        }

        echo "  -> Seeding finance product configs...\n";
        (new \app\service\FinanceProductConfigService())->seedDefaults();

        echo "  -> Seeding article category dict...\n";
        require dirname(__DIR__, 2) . '/scripts/seed_article_category_dict.php';
    }
};
