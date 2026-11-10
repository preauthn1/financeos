<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\service\AdConfigService;
use support\Request;

class AdConfigController extends BaseController
{
    use DefinesAdminMenu;

    public static function menuConfig(): ?array
    {
        return [
            'group' => ['name' => '任务中心', 'slug' => 'task', 'path' => '/task', 'icon' => 'list', 'sort' => 45],
            'menu' => [
                'name' => '广告位配置', 'slug' => 'task-ad-config', 'path' => '/task/ad-config',
                'icon' => 'setting', 'component' => 'views/task/ad-config/index', 'sort' => 1,
            ],
            'apis' => [
                ['name' => '广告位配置列表', 'slug' => 'task-ad-config:list', 'path' => '/admin/ad-configs', 'method' => 'GET', 'sort' => 1],
                ['name' => '广告位配置详情', 'slug' => 'task-ad-config:show', 'path' => '/admin/ad-configs/*', 'method' => 'GET', 'sort' => 2],
                ['name' => '广告位配置创建', 'slug' => 'task-ad-config:create', 'path' => '/admin/ad-configs', 'method' => 'POST', 'sort' => 3],
                ['name' => '广告位配置更新', 'slug' => 'task-ad-config:update', 'path' => '/admin/ad-configs/*', 'method' => 'PUT', 'sort' => 4],
                ['name' => '广告位配置删除', 'slug' => 'task-ad-config:delete', 'path' => '/admin/ad-configs/*', 'method' => 'DELETE', 'sort' => 5],
                ['name' => '广告位配置切换', 'slug' => 'task-ad-config:toggle', 'path' => '/admin/ad-configs/*/toggle', 'method' => 'PUT', 'sort' => 6],
                ['name' => '广告位编码列表', 'slug' => 'task-ad-config:passwords', 'path' => '/admin/ad-configs/passwords', 'method' => 'GET', 'sort' => 7],
                ['name' => '广告位编码更新', 'slug' => 'task-ad-config:passwords-update', 'path' => '/admin/ad-configs/passwords', 'method' => 'PUT', 'sort' => 8],
                ['name' => '广告位弹窗读取', 'slug' => 'task-ad-config:popup-show', 'path' => '/admin/ad-configs/popup-dialog', 'method' => 'GET', 'sort' => 9],
                ['name' => '广告位弹窗保存', 'slug' => 'task-ad-config:popup-save', 'path' => '/admin/ad-configs/popup-dialog', 'method' => 'PUT', 'sort' => 10],
                ['name' => '平台规模读取', 'slug' => 'task-ad-config:platform-show', 'path' => '/admin/ad-configs/platform-scale', 'method' => 'GET', 'sort' => 11],
                ['name' => '平台规模保存', 'slug' => 'task-ad-config:platform-save', 'path' => '/admin/ad-configs/platform-scale', 'method' => 'PUT', 'sort' => 12],
                ['name' => '超额弹窗读取', 'slug' => 'task-ad-config:overdue-show', 'path' => '/admin/ad-configs/overdue-dialog', 'method' => 'GET', 'sort' => 13],
                ['name' => '超额弹窗保存', 'slug' => 'task-ad-config:overdue-save', 'path' => '/admin/ad-configs/overdue-dialog', 'method' => 'PUT', 'sort' => 14],
                ['name' => '广告位时段读取', 'slug' => 'task-ad-config:sessions-show', 'path' => '/admin/ad-configs/task-sessions', 'method' => 'GET', 'sort' => 15],
                ['name' => '广告位时段保存', 'slug' => 'task-ad-config:sessions-save', 'path' => '/admin/ad-configs/task-sessions', 'method' => 'PUT', 'sort' => 16],
            ],
        ];
    }

    public function __construct(private ?AdConfigService $service = null)
    {
        $this->service = $service ?? new AdConfigService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        return success($this->service->list($page, $limit, $request->only(['keyword', 'type', 'is_show'])));
    }

    public function meta(Request $request)
    {
        return success(['types' => $this->service->typeOptions()]);
    }

    public function show(Request $request, int $id)
    {
        return success($this->service->show($id));
    }

    public function store(Request $request)
    {
        return success($this->service->create($request->all()), '创建成功');
    }

    public function update(Request $request, int $id)
    {
        return success($this->service->update($id, $request->all()), '更新成功');
    }

    public function destroy(Request $request, int $id)
    {
        $this->service->delete($id);
        return success(null, '删除成功');
    }

    public function toggle(Request $request, int $id)
    {
        $field = (string) $request->post('field', $request->get('field', ''));
        $value = (int) $request->post('value', $request->get('value', 0));
        return success($this->service->toggle($id, $field, $value), '操作成功');
    }

    public function passwords(Request $request)
    {
        return success($this->service->passwordGroups());
    }

    public function updatePasswords(Request $request)
    {
        return success(
            $this->service->updatePasswordByType(
                (int) $request->post('type', 0),
                $request->post('password') ? (string) $request->post('password') : null,
                (int) $request->post('minutes', $request->post('password_refresh_frequency', 0))
            ),
            '编码更新成功'
        );
    }

    public function popupDialog(Request $request)
    {
        return success($this->service->getPopupDialog());
    }

    public function savePopupDialog(Request $request)
    {
        return success(
            $this->service->savePopupDialog((array) $request->post('items', [])),
            '保存成功'
        );
    }

    public function platformScale(Request $request)
    {
        return success($this->service->getPlatformScale());
    }

    public function savePlatformScale(Request $request)
    {
        return success($this->service->savePlatformScale($request->post()), '保存成功');
    }

    public function overdueDialog(Request $request)
    {
        return success($this->service->getOverdueDialog());
    }

    public function saveOverdueDialog(Request $request)
    {
        return success($this->service->saveOverdueDialog($request->post()), '保存成功');
    }

    public function taskSessions(Request $request)
    {
        return success($this->service->getTaskSessions());
    }

    public function saveTaskSessions(Request $request)
    {
        return success($this->service->saveTaskSessions((array) $request->post('sessions', [])), '保存成功');
    }
}
