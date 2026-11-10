<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\service\WithdrawService;
use app\support\CsvHelper;
use support\Request;
use support\Response;

class WithdrawController extends BaseController
{
    use DefinesAdminMenu;

    public static function menuConfig(): ?array
    {
        return [
            'group' => [
                'name' => '账单管理',
                'slug' => 'finance',
                'path' => '/finance',
                'icon' => 'money',
                'sort' => 40,
            ],
            'menu' => [
                'name' => '提现订单',
                'slug' => 'finance-withdraw',
                'path' => '/finance/withdraw',
                'icon' => 'wallet',
                'component' => 'views/finance/withdraw/index',
                'sort' => 3,
            ],
            'apis' => [
                ['name' => '提现列表', 'slug' => 'finance-withdraw:list', 'path' => '/admin/withdraw-orders', 'method' => 'GET', 'sort' => 1],
                ['name' => '提现详情', 'slug' => 'finance-withdraw:show', 'path' => '/admin/withdraw-orders/*', 'method' => 'GET', 'sort' => 2],
                ['name' => '提现审核', 'slug' => 'finance-withdraw:audit', 'path' => '/admin/withdraw-orders/*/audit', 'method' => 'PUT', 'sort' => 3],
                ['name' => '线下转账', 'slug' => 'finance-withdraw:offline', 'path' => '/admin/withdraw-orders/*/offline-transfer', 'method' => 'PUT', 'sort' => 4],
                ['name' => '卡审核', 'slug' => 'finance-withdraw:card', 'path' => '/admin/withdraw-orders/*/card', 'method' => 'PUT', 'sort' => 5],
                ['name' => '进入出款', 'slug' => 'finance-withdraw:start-payout', 'path' => '/admin/withdraw-orders/*/start-payout', 'method' => 'PUT', 'sort' => 6],
                ['name' => '出款失败', 'slug' => 'finance-withdraw:fail', 'path' => '/admin/withdraw-orders/*/fail', 'method' => 'PUT', 'sort' => 7],
                ['name' => '锁单', 'slug' => 'finance-withdraw:lock', 'path' => '/admin/withdraw-orders/*/lock', 'method' => 'PUT', 'sort' => 8],
                ['name' => '解锁', 'slug' => 'finance-withdraw:unlock', 'path' => '/admin/withdraw-orders/*/unlock', 'method' => 'PUT', 'sort' => 9],
                ['name' => '批量操作', 'slug' => 'finance-withdraw:batch', 'path' => '/admin/withdraw-orders/batch', 'method' => 'POST', 'sort' => 10],
                ['name' => '批量站内信', 'slug' => 'finance-withdraw:batch-message', 'path' => '/admin/withdraw-orders/batch-message', 'method' => 'POST', 'sort' => 11],
                ['name' => '导出提现', 'slug' => 'finance-withdraw:export', 'path' => '/admin/withdraw-orders/export', 'method' => 'GET', 'sort' => 12],
            ],
        ];
    }

    protected WithdrawService $service;

    public function __construct(?WithdrawService $service = null)
    {
        $this->service = $service ?? new WithdrawService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        $filters = $request->only([
            'keyword', 'user_id', 'leader_id', 'order_no', 'status', 'type', 'is_virtual', 'channel',
            'tab', 'time_type', 'start_date', 'end_date', 'start_time', 'end_time',
        ]);
        return success($this->service->list($page, $limit, $filters));
    }

    public function export(Request $request): Response
    {
        $filters = $request->only([
            'keyword', 'user_id', 'leader_id', 'order_no', 'status', 'type', 'is_virtual', 'channel',
            'tab', 'time_type', 'start_date', 'end_date', 'start_time', 'end_time',
        ]);
        $rows = $this->service->exportRows($filters);
        $csvRows = array_map(static fn (array $row) => [
            $row['id'],
            $row['order_no'],
            $row['user_id'],
            $row['username'],
            $row['leader_label'],
            $row['original_amount'],
            $row['handling_fee'],
            $row['withdraw_amount'],
            $row['channel_text'],
            $row['payee_account'],
            $row['true_name'],
            $row['status_text'],
            implode('|', $row['risk_tags'] ?? []),
            $row['risk_score'],
            $row['created_at'],
            $row['checked_at'],
            $row['paid_at'],
        ], $rows);

        $content = CsvHelper::build(
            [
                'ID', '订单号', '用户ID', '用户名', '所属代理', '提现金额', '手续费', '到账金额',
                '渠道', '收款账号', '真实姓名', '状态', '风险标签', '风险分', '申请时间', '审核时间', '出款时间',
            ],
            $csvRows
        );

        return response($content, 200, [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="withdraw_orders_' . date('YmdHis') . '.csv"',
        ]);
    }

    public function show(Request $request, int $id)
    {
        return success($this->service->show($id));
    }

    public function audit(Request $request, int $id)
    {
        $status = (int) $request->post('status', 0);
        $remark = (string) $request->post('remark', '');
        $adminId = $this->adminId($request);
        return success($this->service->audit($id, $status, $remark, $adminId), '审核完成');
    }

    public function offlineTransfer(Request $request, int $id)
    {
        $remark = (string) $request->post('remark', '');
        $adminId = $this->adminId($request);
        return success($this->service->offlineTransfer($id, $remark, $adminId), '线下转账完成');
    }

    public function card(Request $request, int $id)
    {
        $enable = (int) $request->post('enable', 1) === 1;
        $reason = (string) $request->post('reason', $request->post('remark', ''));
        return success($this->service->setCardReview($id, $enable, $reason, $this->adminId($request)), $enable ? '已转入卡审核' : '已解除卡审核');
    }

    public function startPayout(Request $request, int $id)
    {
        return success($this->service->startPayout($id, $this->adminId($request)), '已进入出款');
    }

    public function fail(Request $request, int $id)
    {
        $remark = (string) $request->post('remark', '');
        return success($this->service->failPayout($id, $remark, $this->adminId($request)), '已标记出款失败');
    }

    public function lock(Request $request, int $id)
    {
        $roles = (array) ($request->admin_roles ?? []);
        $force = in_array('super_admin', $roles, true);
        return success($this->service->lock($id, $this->adminId($request), $force), '锁单成功');
    }

    public function unlock(Request $request, int $id)
    {
        $roles = (array) ($request->admin_roles ?? []);
        $force = in_array('super_admin', $roles, true);
        return success($this->service->unlock($id, $this->adminId($request), $force), '已解锁');
    }

    public function batch(Request $request)
    {
        $ids = (array) $request->post('ids', []);
        $action = (string) $request->post('action', '');
        $remark = (string) $request->post('remark', '');
        return success($this->service->batchAction($ids, $action, $remark, $this->adminId($request)), '批量操作完成');
    }

    public function batchMessage(Request $request)
    {
        $ids = (array) $request->post('ids', []);
        $title = (string) $request->post('title', '');
        $content = (string) $request->post('content', '');
        return success(
            $this->service->batchMessage($ids, $title, $content, $this->adminId($request)),
            '站内信已发送'
        );
    }
}
