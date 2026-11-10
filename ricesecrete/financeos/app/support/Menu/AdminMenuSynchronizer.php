<?php

declare(strict_types=1);

namespace app\support\Menu;

use database\support\PermissionSeeder;
use Illuminate\Database\Capsule\Manager as DB;

class AdminMenuSynchronizer
{
    private AdminMenuScanner $scanner;
    private ?int $nextId = null;
    /** @var array<string, int> */
    private array $slugToId = [];

    public function __construct(?AdminMenuScanner $scanner = null)
    {
        $this->scanner = $scanner ?? new AdminMenuScanner();
    }

    /**
     * @return array{menus: int, apis: int, buttons: int, removed: int}
     */
    public function sync(bool $fresh = false): array
    {
        $data = $this->scanner->flatten();
        $now = date('Y-m-d H:i:s');
        $ids = [];
        $removed = 0;
        $this->slugToId = [];

        DB::transaction(function () use ($data, $now, $fresh, &$ids, &$removed) {
            $this->nextId = (int) DB::table('sy_permissions')->lockForUpdate()->max('id') + 1;

            foreach ($data['menus'] as $menu) {
                $menu = $this->resolveMenuParentId($menu);
                $menuId = $this->resolveId($menu, 'menu');
                $menu['id'] = $menuId;
                $ids[] = $menuId;
                $this->upsertPermission($menu, 'menu', $now);
                $this->slugToId[$menu['slug']] = $menuId;
            }

            foreach ($data['apis'] as $api) {
                $api = $this->resolveApiParentId($api);
                $apiId = $this->resolveId($api, 'api');
                $api['id'] = $apiId;
                $ids[] = $apiId;
                $this->upsertPermission($api, 'api', $now);
            }

            foreach ($data['buttons'] as $button) {
                $button = $this->resolveButtonParentId($button);
                $buttonId = $this->resolveId($button, 'button');
                $button['id'] = $buttonId;
                $ids[] = $buttonId;
                $this->upsertPermission($button, 'button', $now);
            }

            if ($fresh && $ids !== []) {
                $removed = (int) DB::table('sy_permissions')->whereNotIn('id', $ids)->count();
                DB::table('sy_role_permissions')
                    ->whereNotIn('permission_id', $ids)
                    ->delete();
                DB::table('sy_permissions')
                    ->whereNotIn('id', $ids)
                    ->delete();
            }
        });

        PermissionSeeder::clearRedisCache();

        return [
            'menus' => count($data['menus']),
            'apis' => count($data['apis']),
            'buttons' => count($data['buttons']),
            'removed' => $removed,
        ];
    }

    private function resolveButtonParentId(array $button): array
    {
        if (($button['parent_id'] ?? 0) > 0 || empty($button['parent_menu_slug'])) {
            return $button;
        }

        $button['parent_id'] = $this->lookupMenuIdBySlug((string) $button['parent_menu_slug']);
        return $button;
    }

    private function resolveMenuParentId(array $menu): array
    {
        if (($menu['parent_id'] ?? 0) > 0 || empty($menu['parent_slug'])) {
            return $menu;
        }

        $menu['parent_id'] = $this->lookupMenuIdBySlug((string) $menu['parent_slug']);
        return $menu;
    }

    private function resolveApiParentId(array $api): array
    {
        if (($api['parent_id'] ?? 0) > 0 || empty($api['parent_menu_slug'])) {
            return $api;
        }

        $api['parent_id'] = $this->lookupMenuIdBySlug((string) $api['parent_menu_slug']);
        return $api;
    }

    private function lookupMenuIdBySlug(string $slug): int
    {
        if ($slug === '') {
            return 0;
        }

        if (isset($this->slugToId[$slug])) {
            return $this->slugToId[$slug];
        }

        return (int) (DB::table('sy_permissions')
            ->where('type', 'menu')
            ->where('slug', $slug)
            ->value('id') ?? 0);
    }

    private function resolveId(array $item, string $type): int
    {
        $id = (int) ($item['id'] ?? 0);
        if ($id > 0) {
            return $id;
        }

        $query = DB::table('sy_permissions')->where('type', $type);
        $query->where('slug', (string) ($item['slug'] ?? ''));
        $query->where('path', (string) ($item['path'] ?? ''));

        if ($type === 'api') {
            $query->where('method', (string) ($item['method'] ?? 'GET'));
        }

        $existingId = $query->value('id');
        if ($existingId) {
            return (int) $existingId;
        }

        if ($this->nextId === null) {
            $this->nextId = (int) DB::table('sy_permissions')->max('id') + 1;
        }

        return $this->nextId++;
    }

    private function upsertPermission(array $item, string $type, string $now): void
    {
        $payload = [
            'name' => $item['name'],
            'slug' => $item['slug'],
            'path' => $item['path'],
            'method' => $type === 'api' ? $item['method'] : '',
            'parent_id' => $item['parent_id'],
            'type' => $type,
            'icon' => $type === 'menu' ? ($item['icon'] ?? '') : '',
            'component' => $type === 'menu' ? ($item['component'] ?? '') : '',
            'sort' => $item['sort'],
            'hidden' => 0,
            'updated_at' => $now,
        ];

        $exists = DB::table('sy_permissions')->where('id', $item['id'])->exists();
        if ($exists) {
            DB::table('sy_permissions')->where('id', $item['id'])->update($payload);
            return;
        }

        DB::table('sy_permissions')->insert(array_merge($payload, [
            'id' => $item['id'],
            'created_at' => $now,
        ]));
    }

    public function reseedAll(): void
    {
        DB::transaction(function () {
            DB::table('sy_role_permissions')->delete();
            DB::table('sy_permissions')->delete();
            $this->sync(false);

            $editorRoleId = DB::table('sy_roles')->where('slug', 'editor')->value('id');
            if ($editorRoleId) {
                PermissionSeeder::assignEditorPermissions((int) $editorRoleId);
            }
        });

        PermissionSeeder::clearRedisCache();
    }
}
