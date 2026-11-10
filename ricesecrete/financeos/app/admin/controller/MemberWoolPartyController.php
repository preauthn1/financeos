<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\service\MemberWoolPartyService;
use app\support\CsvHelper;
use support\Request;
use support\Response;

class MemberWoolPartyController extends BaseController
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
                'name' => '羊毛党',
                'slug' => 'member-wool',
                'path' => '/member/wool',
                'icon' => 'warning',
                'component' => 'views/member/wool/index',
                'sort' => 9,
            ],
            'apis' => [
                ['name' => '羊毛党列表', 'slug' => 'member-wool:list', 'path' => '/admin/member-wool-party', 'method' => 'GET', 'sort' => 1],
                ['name' => '导出羊毛党', 'slug' => 'member-wool:export', 'path' => '/admin/member-wool-party/export', 'method' => 'GET', 'sort' => 2],
            ],
        ];
    }

    public function __construct(private ?MemberWoolPartyService $service = null)
    {
        $this->service = $service ?? new MemberWoolPartyService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);

        return success($this->service->list(
            $page,
            $limit,
            (string) $request->get('risk_type', 'all'),
            $request->only(['keyword'])
        ));
    }

    public function export(Request $request): Response
    {
        $rows = $this->service->exportRows(
            (string) $request->get('risk_type', 'all'),
            $request->only(['keyword'])
        );

        $csvRows = array_map(static fn (array $row) => [
            $row['id'],
            $row['username'],
            $row['invite_code'],
            $row['total_recharge'],
            $row['total_withdraw'],
            $row['recharge_withdraw_diff'],
            $row['balance'],
            $row['frozen_balance'],
            $row['credit_score'],
            $row['last_login_ip'],
            $row['registered_at_text'],
            implode('|', $row['risk_tags'] ?? []),
        ], $rows);

        $content = CsvHelper::build(
            [
                '用户ID', '用户名', '邀请码', '累计充值', '累计提现', '充提差额',
                '余额', '冻结', '信用分', '最后登录IP', '注册时间', '风险标签',
            ],
            $csvRows
        );

        return response($content, 200, [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="member_wool_' . date('YmdHis') . '.csv"',
        ]);
    }
}
