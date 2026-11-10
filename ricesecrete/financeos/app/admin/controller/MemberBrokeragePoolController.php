<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use support\Request;
use app\service\MemberBrokeragePoolService;

class MemberBrokeragePoolController extends BaseController
{
    use DefinesAdminMenu;

    public static function menuConfig(): ?array
    {
        return [
            'api_parent_menu_slug' => 'member-list',
            'apis' => [
                ['name' => '佣金池列表', 'slug' => 'member-brokerage:list', 'path' => '/admin/member-brokerage-pools', 'method' => 'GET', 'sort' => 1],
            ],
        ];
    }

    protected MemberBrokeragePoolService $service;

    public function __construct(?MemberBrokeragePoolService $service = null)
    {
        $this->service = $service ?? new MemberBrokeragePoolService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        $filters = $request->only(['user_id', 'start_date', 'end_date']);
        return success($this->service->list($page, $limit, $filters));
    }
}
