<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\service\MemberWaterService;
use app\support\CsvHelper;
use support\Request;
use support\Response;

class MemberWaterController extends BaseController
{
    use DefinesAdminMenu;

    public static function menuConfig(): ?array
    {
        return [
            'group' => [
                'name' => '会员管理',
                'slug' => 'member',
                'path' => '/member',
                'icon' => 'people',
                'sort' => 30,
            ],
            'menu' => [
                'name' => '会员流水',
                'slug' => 'member-water',
                'path' => '/member/water',
                'icon' => 'money',
                'component' => 'views/member/water/index',
                'sort' => 7,
            ],
            'apis' => [
                ['name' => '流水列表', 'slug' => 'member-water:list', 'path' => '/admin/member-waters', 'method' => 'GET', 'sort' => 1],
                ['name' => '充值提现流水', 'slug' => 'member-water:flow', 'path' => '/admin/member-waters/flow', 'method' => 'GET', 'sort' => 2],
                ['name' => '流水筛选元信息', 'slug' => 'member-water:meta', 'path' => '/admin/member-waters/meta', 'method' => 'GET', 'sort' => 3],
                ['name' => '流水显示切换', 'slug' => 'member-water:toggle', 'path' => '/admin/member-waters/*/toggle', 'method' => 'PUT', 'sort' => 4],
                ['name' => '导出流水', 'slug' => 'member-water:export', 'path' => '/admin/member-waters/export', 'method' => 'GET', 'sort' => 5],
            ],
        ];
    }

    protected MemberWaterService $service;

    public function __construct(?MemberWaterService $service = null)
    {
        $this->service = $service ?? new MemberWaterService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        $filters = $request->only([
            'user_id', 'username',
            'type', 'detail_type',
            'direction', 'change',
            'is_virtual',
            'start_date', 'end_date',
            'startTime', 'endTime',
        ]);
        return success($this->service->list($page, $limit, $filters));
    }

    public function export(Request $request): Response
    {
        $filters = $request->only([
            'user_id', 'username',
            'type', 'detail_type',
            'direction', 'change',
            'is_virtual',
            'start_date', 'end_date',
            'startTime', 'endTime',
        ]);
        $rows = $this->service->exportRows($filters);

        $csvRows = array_map(static fn (array $row) => [
            $row['id'],
            $row['user_id'],
            $row['user_info'],
            $row['title'],
            $row['type_str'],
            $row['detail_type_str'],
            $row['user_money'],
            $row['money'],
            $row['last_money'],
            $row['change_str'],
            $row['remarks'],
            $row['admin_name'],
            $row['create_time'],
            ((int) ($row['is_virtual'] ?? 0)) === 1 ? '是' : '否',
        ], $rows);

        $content = CsvHelper::build(
            [
                'ID', '用户ID', '用户信息', '账单标题', '钱包类型', '明细类型',
                '操作前金额', '操作金额', '操作后金额', '收支类型', '备注',
                '管理员', '添加时间', '虚拟账号',
            ],
            $csvRows
        );

        return response($content, 200, [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="member_water_' . date('YmdHis') . '.csv"',
        ]);
    }

    public function rechargeWithdrawFlow(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        $filters = $request->only(['user_id', 'start_date', 'end_date', 'is_virtual']);
        return success($this->service->rechargeWithdrawFlow($page, $limit, $filters));
    }

    public function meta(Request $request)
    {
        return success($this->service->meta());
    }

    public function toggle(Request $request, int $id)
    {
        return success($this->service->toggleDisplay($id), '更新成功');
    }
}
