<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\service\DictService;
use support\Request;

class DictController extends BaseController
{
    use DefinesAdminMenu;

    public static function menuConfig(): ?array
    {
        return [
            'group' => [
                'name' => '系统配置',
                'slug' => 'system-config',
                'path' => '/system-config',
                'icon' => 'setting',
                'sort' => 30,
            ],
            'menu' => [
                'name' => '字典管理',
                'slug' => 'dict-menu',
                'path' => '/system-config/dict',
                'icon' => 'collection',
                'component' => 'views/system/dict/index',
                'sort' => 6,
            ],
            'apis' => [
                ['name' => '字典类型列表', 'slug' => 'dict:list', 'path' => '/admin/dicts', 'method' => 'GET', 'sort' => 1],
                ['name' => '创建字典类型', 'slug' => 'dict:create', 'path' => '/admin/dicts', 'method' => 'POST', 'sort' => 2],
                ['name' => '更新字典类型', 'slug' => 'dict:update', 'path' => '/admin/dicts/*', 'method' => 'PUT', 'sort' => 3],
                ['name' => '删除字典类型', 'slug' => 'dict:delete', 'path' => '/admin/dicts/*', 'method' => 'DELETE', 'sort' => 4],
                ['name' => '字典项列表', 'slug' => 'dict:items', 'path' => '/admin/dicts/*/items', 'method' => 'GET', 'sort' => 5],
                ['name' => '保存字典项', 'slug' => 'dict:items-save', 'path' => '/admin/dicts/*/items', 'method' => 'PUT', 'sort' => 6],
                ['name' => '按编码获取字典', 'slug' => 'dict:by-code', 'path' => '/admin/dicts/code/*', 'method' => 'GET', 'sort' => 7],
            ],
        ];
    }

    protected DictService $service;

    public function __construct(?DictService $service = null)
    {
        $this->service = $service ?? new DictService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        return success($this->service->listTypes($page, $limit, $request->only(['keyword'])));
    }

    public function store(Request $request)
    {
        $data = $request->only(['name', 'code', 'status', 'remark']);
        return success($this->service->createType($data), '创建成功');
    }

    public function update(Request $request, int $id)
    {
        $data = $request->only(['name', 'status', 'remark']);
        return success($this->service->updateType($id, $data), '更新成功');
    }

    public function destroy(Request $request, int $id)
    {
        $this->service->deleteType($id);
        return success(null, '删除成功');
    }

    public function items(Request $request, int $id)
    {
        return success($this->service->listItems($id));
    }

    public function saveItems(Request $request, int $id)
    {
        $items = (array) $request->post('items', []);
        $this->service->saveItems($id, $items);
        return success(null, '保存成功');
    }

    public function byCode(Request $request, string $code)
    {
        return success($this->service->getByCode($code));
    }
}
