<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\service\LangService;
use support\Request;

class LangController extends BaseController
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
                'name' => '语言管理',
                'slug' => 'lang-menu',
                'path' => '/system-config/lang',
                'icon' => 'peoples',
                'component' => 'views/system/lang/index',
                'sort' => 7,
            ],
            'apis' => [
                ['name' => '语言列表', 'slug' => 'lang:list', 'path' => '/admin/langs', 'method' => 'GET', 'sort' => 1],
                ['name' => '语言选项', 'slug' => 'lang:options', 'path' => '/admin/langs/options', 'method' => 'GET', 'sort' => 2],
                ['name' => '语言详情', 'slug' => 'lang:show', 'path' => '/admin/langs/*', 'method' => 'GET', 'sort' => 3],
                ['name' => '创建语言', 'slug' => 'lang:create', 'path' => '/admin/langs', 'method' => 'POST', 'sort' => 4],
                ['name' => '更新语言', 'slug' => 'lang:update', 'path' => '/admin/langs/*', 'method' => 'PUT', 'sort' => 5],
                ['name' => '删除语言', 'slug' => 'lang:delete', 'path' => '/admin/langs/*', 'method' => 'DELETE', 'sort' => 6],
            ],
        ];
    }

    public function __construct(private ?LangService $service = null)
    {
        $this->service = $service ?? new LangService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        return success($this->service->list($page, $limit, $request->only(['keyword', 'status'])));
    }

    public function options(Request $request)
    {
        $enabledOnly = (int) $request->get('enabled_only', 1) === 1;
        return success($this->service->options($enabledOnly));
    }

    public function show(Request $request, int $id)
    {
        return success($this->service->show($id));
    }

    public function store(Request $request)
    {
        $data = $request->only(['title', 'lang', 'remark', 'is_default', 'is_default_lang', 'flag', 'status', 'sort']);
        if (empty($data['title']) || empty($data['lang'])) {
            return fail(42201, '请填写语言名称和代码');
        }
        return success($this->service->create($data), '创建成功');
    }

    public function update(Request $request, int $id)
    {
        $data = $request->only(['title', 'lang', 'remark', 'is_default', 'is_default_lang', 'flag', 'status', 'sort']);
        return success($this->service->update($id, $data), '更新成功');
    }

    public function destroy(Request $request, int $id)
    {
        $this->service->delete($id);
        return success(null, '删除成功');
    }
}
