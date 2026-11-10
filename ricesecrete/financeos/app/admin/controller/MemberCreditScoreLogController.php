<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\service\MemberCreditScoreService;
use app\support\CsvHelper;
use support\Request;
use support\Response;

class MemberCreditScoreLogController extends BaseController
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
                'name' => '信誉分记录',
                'slug' => 'member-credit-record',
                'path' => '/member/credit/record',
                'icon' => 'document',
                'component' => 'views/member/credit/record/index',
                'sort' => 4,
            ],
            'apis' => [
                ['name' => '信誉分记录列表', 'slug' => 'member-credit-record:list', 'path' => '/admin/member-credit-scores', 'method' => 'GET', 'sort' => 1],
                ['name' => '导出信誉分记录', 'slug' => 'member-credit-record:export', 'path' => '/admin/member-credit-scores/export', 'method' => 'GET', 'sort' => 2],
            ],
        ];
    }

    public function __construct(private ?MemberCreditScoreService $service = null)
    {
        $this->service = $service ?? new MemberCreditScoreService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        $filters = $request->only([
            'user_id', 'username', 'user_name', 'type',
            'is_virtual', 'is_xuni',
            'start_time', 'end_time', 'startTime', 'endTime',
        ]);

        return success($this->service->list($page, $limit, $filters));
    }

    public function export(Request $request): Response
    {
        $filters = $request->only([
            'user_id', 'username', 'user_name', 'type',
            'is_virtual', 'is_xuni',
            'start_time', 'end_time', 'startTime', 'endTime',
        ]);
        $rows = $this->service->exportRows($filters);

        $csvRows = array_map(static fn (array $row) => [
            $row['id'],
            $row['user_id'],
            $row['user_name'],
            $row['type_str'],
            $row['top_leader_remark'],
            $row['before'],
            $row['score'],
            $row['after'],
            $row['title'],
            $row['remarks'],
            $row['admin_name'],
            $row['tree_level'],
            $row['is_xuni_text'],
            $row['create_time'],
        ], $rows);

        $content = CsvHelper::build(
            [
                'ID', '用户ID', '用户名', '类型', '所属代理',
                '操作前信用分', '操作信用分', '操作后信用分', '标题', '备注',
                '管理员', '所在层级', '虚拟账号', '添加时间',
            ],
            $csvRows
        );

        return response($content, 200, [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="credit_score_log_' . date('YmdHis') . '.csv"',
        ]);
    }
}
