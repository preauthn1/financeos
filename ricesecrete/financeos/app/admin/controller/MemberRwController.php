<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\service\MemberRwService;
use app\support\CsvHelper;
use support\Request;
use support\Response;

class MemberRwController extends BaseController
{
    use DefinesAdminMenu;

    public static function menuConfig(): ?array
    {
        return [
            'group' => [
                'name' => '会员管理', 'slug' => 'member', 'path' => '/member', 'icon' => 'people', 'sort' => 30,
            ],
            'menu' => [
                'name' => '充值提现流水', 'slug' => 'member-rw', 'path' => '/member/rw',
                'icon' => 'wallet', 'component' => 'views/member/rw/index', 'sort' => 8,
            ],
            'apis' => [
                ['name' => '充提流水', 'slug' => 'member-rw:flow', 'path' => '/admin/member-rw/flow', 'method' => 'GET', 'sort' => 1],
                ['name' => '导出充提流水', 'slug' => 'member-rw:export', 'path' => '/admin/member-rw/export', 'method' => 'GET', 'sort' => 2],
            ],
        ];
    }

    protected MemberRwService $service;

    public function __construct(?MemberRwService $service = null)
    {
        $this->service = $service ?? new MemberRwService();
    }

    public function flow(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        $filters = $request->only(['user_id', 'flow_type', 'is_virtual', 'start_date', 'end_date']);
        return success($this->service->flow($page, $limit, $filters));
    }

    public function export(Request $request): Response
    {
        $filters = $request->only(['user_id', 'flow_type', 'is_virtual', 'start_date', 'end_date']);
        $rows = $this->service->exportRows($filters);

        $csvRows = array_map(static fn (array $row) => [
            $row['id'],
            $row['order_no'],
            $row['user_id'],
            $row['username'],
            $row['nickname'],
            $row['flow_type_text'],
            $row['amount'],
            ((int) $row['is_virtual']) === 1 ? '是' : '否',
            $row['created_at'],
            $row['paid_at'],
        ], $rows);

        $content = CsvHelper::build(
            ['ID', '订单号', '用户ID', '用户名', '昵称', '类型', '金额', '虚拟账号', '创建时间', '支付时间'],
            $csvRows
        );

        return response($content, 200, [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="member_rw_' . date('YmdHis') . '.csv"',
        ]);
    }
}
