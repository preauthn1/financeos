<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\service\MemberCreditScoreService;
use support\Request;

class MemberCreditScoreController extends BaseController
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
                'name' => '信誉分管理',
                'slug' => 'member-credit-manage',
                'path' => '/member/credit/manage',
                'icon' => 'star',
                'component' => 'views/member/credit/manage/index',
                'sort' => 3,
            ],
            'apis' => [
                ['name' => '信誉分总览', 'slug' => 'member-credit-manage:overview', 'path' => '/admin/member-credit-scores/overview', 'method' => 'GET', 'sort' => 1],
                ['name' => '调整信誉分', 'slug' => 'member-credit-manage:adjust', 'path' => '/admin/member-credit-scores/adjust', 'method' => 'POST', 'sort' => 2],
                ['name' => '重新计算信誉分', 'slug' => 'member-credit-manage:recalc', 'path' => '/admin/member-credit-scores/recalculate', 'method' => 'POST', 'sort' => 3],
            ],
        ];
    }

    public function __construct(private ?MemberCreditScoreService $service = null)
    {
        $this->service = $service ?? new MemberCreditScoreService();
    }

    public function overview(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        $filters = $request->only(['user_id', 'username', 'user_name', 'period']);

        return success($this->service->overview($page, $limit, $filters));
    }

    public function recalculate(Request $request)
    {
        $period = (string) $request->post('period', date('Ym'));
        $count = $this->service->recalculate($period);

        return success(['count' => $count], "已重新计算 {$count} 个用户的信用分");
    }

    public function adjust(Request $request)
    {
        $userId = (int) ($request->post('user_id', 0) ?: $request->post('uid', 0));
        $type = (int) $request->post('type', 0);
        $score = (int) $request->post('score', 0);
        $remarks = (string) ($request->post('remarks', '') ?: $request->post('remark', ''));

        return success(
            $this->service->adjust($userId, $type, $score, $remarks, $this->adminId($request)),
            '调整成功'
        );
    }
}
