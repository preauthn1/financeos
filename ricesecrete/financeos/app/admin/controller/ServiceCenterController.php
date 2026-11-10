<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\service\ServiceCenterService;
use support\Request;

class ServiceCenterController extends BaseController
{
    use DefinesAdminMenu;

    public static function menuConfig(): ?array
    {
        return [
            'group' => [
                'name' => '内容管理', 'slug' => 'content', 'path' => '/content', 'icon' => 'document', 'sort' => 10,
            ],
            'menu' => [
                'name' => '服务中心', 'slug' => 'content-service-center', 'path' => '/content/service-center',
                'icon' => 'location', 'component' => 'views/ops/service-center/index', 'sort' => 20,
            ],
            'apis' => [
                ['name' => '服务中心列表', 'slug' => 'content-service-center:list', 'path' => '/admin/service-centers', 'method' => 'GET', 'sort' => 1],
                ['name' => '服务中心创建', 'slug' => 'content-service-center:create', 'path' => '/admin/service-centers', 'method' => 'POST', 'sort' => 2],
                ['name' => '服务中心详情', 'slug' => 'content-service-center:show', 'path' => '/admin/service-centers/*', 'method' => 'GET', 'sort' => 3],
                ['name' => '服务中心更新', 'slug' => 'content-service-center:update', 'path' => '/admin/service-centers/*', 'method' => 'PUT', 'sort' => 4],
                ['name' => '服务中心删除', 'slug' => 'content-service-center:delete', 'path' => '/admin/service-centers/*', 'method' => 'DELETE', 'sort' => 5],
                ['name' => '服务中心申请列表', 'slug' => 'content-service-center:apply-list', 'path' => '/admin/service-center-applications', 'method' => 'GET', 'sort' => 6],
                ['name' => '服务中心申请审核', 'slug' => 'content-service-center:apply-audit', 'path' => '/admin/service-center-applications/*/audit', 'method' => 'PUT', 'sort' => 7],
            ],
        ];
    }

    public function __construct(private ?ServiceCenterService $service = null)
    {
        $this->service = $service ?? new ServiceCenterService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        $filters = array_merge($request->get(), ['admin_all' => 1]);

        return success($this->service->list($page, $limit, $filters));
    }

    public function show(Request $request, int $id)
    {
        return success($this->service->show($id));
    }

    public function store(Request $request)
    {
        return success($this->service->create($request->post()), '创建成功');
    }

    public function update(Request $request, int $id)
    {
        return success($this->service->update($id, $request->post()), '更新成功');
    }

    public function destroy(Request $request, int $id)
    {
        $this->service->delete($id);

        return success(null, '删除成功');
    }

    public function applications(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);

        return success($this->service->applyList($page, $limit, $request->get()));
    }

    public function auditApplication(Request $request, int $id)
    {
        return success($this->service->audit(
            $id,
            (int) $request->post('status', 0),
            (string) $request->post('remark', '')
        ), '审核成功');
    }
}
