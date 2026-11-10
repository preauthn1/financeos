<?php

declare(strict_types=1);

namespace app\support\Menu;

use app\admin\controller\concerns\DefinesAdminMenu;
use ReflectionClass;

class AdminMenuScanner
{
    /**
     * @return array<int, array>
     */
    public function scan(): array
    {
        $configs = [];
        $pattern = base_path('app/admin/controller/*.php');

        foreach (glob($pattern) ?: [] as $file) {
            $baseName = basename($file, '.php');
            if ($baseName === 'BaseController') {
                continue;
            }

            $class = "app\\admin\\controller\\{$baseName}";
            if (!class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            if (!$reflection->hasMethod('menuConfig')) {
                continue;
            }

            if (!in_array(DefinesAdminMenu::class, class_uses($class), true)) {
                continue;
            }

            $config = $class::menuConfig();
            if ($config === null) {
                continue;
            }

            $configs[] = $config;
        }

        return $configs;
    }

    /**
     * @return array{menus: array<int, array>, apis: array<int, array>, buttons: array<int, array>}
     */
    public function flatten(): array
    {
        $menus = [];
        $apis = [];
        $buttons = [];
        $seenGroups = [];

        foreach ($this->scan() as $config) {
            $group = $config['group'] ?? null;
            $groupSlug = null;

            if ($group !== null) {
                $groupSlug = (string) ($group['slug'] ?? '');
                $groupNormalized = $this->normalizeMenu($group);
                $groupKey = $groupNormalized['id'] ?? $groupSlug;
                if (!isset($seenGroups[$groupKey])) {
                    $menus[] = $groupNormalized;
                    $seenGroups[$groupKey] = true;
                }

                foreach ($group['children'] ?? [] as $child) {
                    $menus[] = $this->normalizeMenu($child, $groupSlug);
                }
            }

            if (!empty($config['menu'])) {
                $menus[] = $this->normalizeMenu($config['menu'], $groupSlug);
            }

            $menuSlug = (string) ($config['menu']['slug'] ?? '');
            $defaultApiParent = (string) ($config['api_parent_menu_slug'] ?? '');
            if ($defaultApiParent === '' && $menuSlug !== '') {
                $defaultApiParent = $menuSlug;
            }
            foreach ($config['apis'] ?? [] as $api) {
                $apis[] = $this->normalizeApi($api, $defaultApiParent !== '' ? $defaultApiParent : null);
            }
            foreach ($config['buttons'] ?? [] as $button) {
                $buttons[] = $this->normalizeButton($button, $defaultApiParent !== '' ? $defaultApiParent : null);
            }
        }

        usort($menus, static function (array $a, array $b): int {
            $aRoot = empty($a['parent_slug']) && ($a['parent_id'] ?? 0) === 0;
            $bRoot = empty($b['parent_slug']) && ($b['parent_id'] ?? 0) === 0;
            if ($aRoot !== $bRoot) {
                return $aRoot ? -1 : 1;
            }

            $sort = ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0);
            if ($sort !== 0) {
                return $sort;
            }

            return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
        });

        usort($apis, static function (array $a, array $b): int {
            $sort = ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0);
            if ($sort !== 0) {
                return $sort;
            }

            return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
        });

        usort($buttons, static function (array $a, array $b): int {
            $sort = ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0);
            if ($sort !== 0) {
                return $sort;
            }

            return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
        });

        return ['menus' => $menus, 'apis' => $apis, 'buttons' => $buttons];
    }

    private function normalizeMenu(array $menu, ?string $parentSlug = null): array
    {
        return [
            'id' => isset($menu['id']) ? (int) $menu['id'] : null,
            'name' => (string) $menu['name'],
            'slug' => (string) $menu['slug'],
            'parent_id' => (int) ($menu['parent_id'] ?? 0),
            'parent_slug' => (string) ($menu['parent_slug'] ?? $parentSlug ?? ''),
            'path' => (string) ($menu['path'] ?? ''),
            'icon' => (string) ($menu['icon'] ?? ''),
            'component' => (string) ($menu['component'] ?? ''),
            'sort' => (int) ($menu['sort'] ?? 0),
        ];
    }

    private function normalizeApi(array $api, ?string $parentMenuSlug = null): array
    {
        return [
            'id' => isset($api['id']) ? (int) $api['id'] : null,
            'name' => (string) $api['name'],
            'slug' => (string) $api['slug'],
            'parent_id' => (int) ($api['parent_id'] ?? 0),
            'parent_menu_slug' => (string) ($api['parent_menu_slug'] ?? $parentMenuSlug ?? ''),
            'path' => (string) ($api['path'] ?? ''),
            'method' => strtoupper((string) ($api['method'] ?? 'GET')),
            'sort' => (int) ($api['sort'] ?? 0),
        ];
    }

    private function normalizeButton(array $button, ?string $parentMenuSlug = null): array
    {
        return [
            'id' => isset($button['id']) ? (int) $button['id'] : null,
            'name' => (string) $button['name'],
            'slug' => (string) $button['slug'],
            'parent_id' => (int) ($button['parent_id'] ?? 0),
            'parent_menu_slug' => (string) ($button['parent_menu_slug'] ?? $parentMenuSlug ?? ''),
            'path' => (string) ($button['path'] ?? ''),
            'sort' => (int) ($button['sort'] ?? 0),
        ];
    }
}
