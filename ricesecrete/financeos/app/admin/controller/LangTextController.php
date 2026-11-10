<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\service\LangTextService;
use support\Request;

class LangTextController extends BaseController
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
                'name' => '翻译文案',
                'slug' => 'lang-text-menu',
                'path' => '/system-config/lang-text',
                'icon' => 'documentation',
                'component' => 'views/system/lang-text/index',
                'sort' => 8,
            ],
            'apis' => [
                ['name' => '文案列表', 'slug' => 'lang-text:list', 'path' => '/admin/lang-texts', 'method' => 'GET', 'sort' => 1],
                ['name' => '文案详情', 'slug' => 'lang-text:show', 'path' => '/admin/lang-texts/*', 'method' => 'GET', 'sort' => 2],
                ['name' => '保存文案', 'slug' => 'lang-text:save', 'path' => '/admin/lang-texts', 'method' => 'POST', 'sort' => 3],
                ['name' => '删除文案', 'slug' => 'lang-text:delete', 'path' => '/admin/lang-texts/*', 'method' => 'DELETE', 'sort' => 4],
                ['name' => '导出翻译', 'slug' => 'lang-text:export', 'path' => '/admin/lang-texts/export', 'method' => 'POST', 'sort' => 5],
            ],
        ];
    }

    public function __construct(private ?LangTextService $service = null)
    {
        $this->service = $service ?? new LangTextService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        return success($this->service->list($page, $limit, $request->only(['keyword'])));
    }

    public function show(Request $request, int $id)
    {
        return success($this->service->show($id));
    }

    public function store(Request $request)
    {
        try {
            return success($this->service->save($request->post()), '保存成功');
        } catch (\InvalidArgumentException $e) {
            return fail(42201, $e->getMessage());
        }
    }

    public function destroy(Request $request, int $id)
    {
        $this->service->delete($id);
        return success(null, '删除成功');
    }

    public function export(Request $request)
    {
        $count = $this->service->export();
        return success(['files' => $count], '导出成功');
    }
}
