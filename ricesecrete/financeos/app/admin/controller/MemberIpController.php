<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\service\MemberIpService;
use support\Request;

class MemberIpController extends BaseController
{
    use DefinesAdminMenu;

    public static function menuConfig(): ?array
    {
        return [
            'group' => [
                'name' => '会员管理',
                'slug' => 'member',
                'path' => '/member/ip',
                'icon' => 'people',
                'sort' => 30,
            ],
            'menu' => [
                'name' => 'IP监控',
                'slug' => 'member-ip',
                'path' => '/member/ip',
                'icon' => 'monitor',
                'component' => 'views/member/ip/index',
                'sort' => 10,
            ],
            'apis' => [
                ['name' => 'IP监控列表', 'slug' => 'member-ip:list', 'path' => '/admin/member-ips', 'method' => 'GET', 'sort' => 1],
                ['name' => '同IP用户', 'slug' => 'member-ip:same', 'path' => '/admin/member-ips/same', 'method' => 'GET', 'sort' => 2],
                ['name' => 'IP风险分析', 'slug' => 'member-ip:analysis', 'path' => '/admin/member-ips/analysis', 'method' => 'GET', 'sort' => 3],
                ['name' => '登录日志', 'slug' => 'member-ip:logs', 'path' => '/admin/member-ips/logs', 'method' => 'GET', 'sort' => 4],
            ],
        ];
    }

    public function __construct(private ?MemberIpService $service = null)
    {
        $this->service = $service ?? new MemberIpService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        $filters = $request->only([
            'user_name', 'username', 'ip', 'uuid',
            'start_time', 'end_time', 'startTime', 'endTime',
            'user_id',
        ]);

        return success($this->service->monitorList($page, $limit, $filters));
    }

    public function analysis(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        $filters = $request->only(['ip', 'risk']);

        return success($this->service->analysis($page, $limit, $filters));
    }

    public function sameIpUsers(Request $request)
    {
        $userId = (int) $request->get('user_id', 0);
        $ip = (string) $request->get('ip', '');

        if ($userId <= 0 && $ip === '') {
            return fail(42201, '请提供用户ID或IP');
        }

        $list = $this->service->getSameIpUsers($userId, $ip);

        return success([
            'list'  => $list,
            'total' => count($list),
        ]);
    }

    public function logs(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        $filters = $request->only(['user_id', 'ip', 'type', 'start_date', 'end_date', 'start_time', 'end_time']);

        return success($this->service->logList($page, $limit, $filters));
    }
}
