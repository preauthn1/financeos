<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\support\CsvHelper;
use support\Request;
use support\Response;
use app\service\MemberService;

class MemberController extends BaseController
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
                'name' => '会员列表',
                'slug' => 'member-list',
                'path' => '/member/list',
                'icon' => 'list',
                'component' => 'views/member/list/index',
                'sort' => 1,
            ],
            'apis' => [
                ['name' => '会员列表', 'slug' => 'member:list', 'path' => '/admin/members', 'method' => 'GET', 'sort' => 1],
                ['name' => '会员详情', 'slug' => 'member:show', 'path' => '/admin/members/*', 'method' => 'GET', 'sort' => 2],
                ['name' => '创建会员', 'slug' => 'member:create', 'path' => '/admin/members', 'method' => 'POST', 'sort' => 3],
                ['name' => '批量创建会员', 'slug' => 'member:batch-create', 'path' => '/admin/members/batch-create', 'method' => 'POST', 'sort' => 3],
                ['name' => '更新会员', 'slug' => 'member:update', 'path' => '/admin/members/*', 'method' => 'PUT', 'sort' => 4],
                ['name' => '批量操作', 'slug' => 'member:batch', 'path' => '/admin/members/batch', 'method' => 'POST', 'sort' => 6],
                ['name' => '状态切换', 'slug' => 'member:toggle', 'path' => '/admin/members/*/toggle', 'method' => 'PUT', 'sort' => 7],
                ['name' => '钱包调整', 'slug' => 'member:wallet', 'path' => '/admin/members/*/wallet', 'method' => 'PUT', 'sort' => 8],
                ['name' => '重置登录密码', 'slug' => 'member:reset-pwd', 'path' => '/admin/members/*/password', 'method' => 'PUT', 'sort' => 9],
                ['name' => '重置支付密码', 'slug' => 'member:reset-pay-pwd', 'path' => '/admin/members/*/pay-password', 'method' => 'PUT', 'sort' => 9],
                ['name' => '修改上级', 'slug' => 'member:parent', 'path' => '/admin/members/*/parent', 'method' => 'PUT', 'sort' => 10],
                ['name' => '下级列表', 'slug' => 'member:subordinates', 'path' => '/admin/members/*/subordinates', 'method' => 'GET', 'sort' => 11],
                ['name' => '团队信息', 'slug' => 'member:team', 'path' => '/admin/members/*/team', 'method' => 'GET', 'sort' => 12],
                ['name' => '会员ID列表', 'slug' => 'member:ids', 'path' => '/admin/members/ids', 'method' => 'GET', 'sort' => 13],
                ['name' => '导出会员', 'slug' => 'member:export', 'path' => '/admin/members/export', 'method' => 'GET', 'sort' => 14],
            ],
        ];
    }

    protected MemberService $memberService;

    public function __construct(?MemberService $memberService = null)
    {
        $this->memberService = $memberService ?? new MemberService();
    }

    // ==================== 列表 ====================

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        $filters = $this->listFilters($request);
        return success($this->memberService->list($page, $limit, $filters));
    }

    // ==================== 详情 ====================

    public function show(Request $request, int $id)
    {
        return success($this->memberService->show($id));
    }

    // ==================== 创建 ====================

    public function store(Request $request)
    {
        $data = $request->only([
            'username', 'password', 'pay_password', 'nickname', 'real_name',
            'mobile', 'phone_code', 'email', 'avatar', 'lang',
            'status', 'level_id', 'parent_invite_code', 'remark',
            'ip', 'is_virtual', 'is_invite_code_enabled', 'account_type',
        ]);

        if (empty($data['username'])) {
            return fail(42201, '用户账号不能为空');
        }

        return success($this->memberService->create($data, $this->adminId($request)), '创建成功');
    }

    public function batchStore(Request $request)
    {
        $accounts = (array) $request->post('accounts', []);
        $defaults = (array) $request->post('defaults', []);

        if ($accounts === []) {
            return fail(42201, '请填写账号');
        }

        return success(
            $this->memberService->batchCreate($accounts, $defaults, $this->adminId($request)),
            '批量创建完成'
        );
    }

    // ==================== 编辑 ====================

    public function update(Request $request, int $id)
    {
        $data = $request->only([
            'nickname', 'real_name', 'mobile', 'phone_code', 'email',
            'avatar', 'lang', 'status', 'level_id', 'remark',
            'is_invite_code_enabled', 'is_credit_score_enabled', 'is_transfer_enabled',
            'ad_plan_id', 'password', 'pay_password',
        ]);
        return success($this->memberService->update($id, $data), '更新成功');
    }

    // ==================== 删除 ====================

    public function destroy(Request $request, int $id)
    {
        return fail(42201, '会员不允许删除');
    }

    // ==================== 批量操作 ====================

    public function batch(Request $request)
    {
        $ids = (array) $request->post('ids', []);
        $action = (string) $request->post('action', '');
        $params = (array) $request->post('params', []);

        if ($ids === []) {
            return fail(42201, '请选择用户');
        }

        $ids = array_map('intval', $ids);

        if (in_array($action, ['enable', 'disable'], true)) {
            $count = $this->memberService->batchUpdateStatus($ids, $action === 'enable' ? 1 : 0);

            return success(['count' => $count, 'success' => $count, 'failed' => count($ids) - $count], "已处理 {$count} 个用户");
        }

        $result = $this->memberService->batchExecute($ids, $action, $params);

        return success($result, "完成：成功 {$result['success']}，失败 {$result['failed']}");
    }

    public function ids(Request $request)
    {
        $filters = $this->listFilters($request);
        $agentLine = (string) $request->get('agent_line', '');
        if ($agentLine !== '') {
            return success(['ids' => $this->memberService->listIdsByAgentLine($agentLine)]);
        }

        return success(['ids' => $this->memberService->listIds($filters)]);
    }

    public function export(Request $request): Response
    {
        $filters = $this->listFilters($request);
        $rows = $this->memberService->exportRows($filters);
        $csvRows = array_map(static fn (array $row) => [
            $row['id'],
            $row['username'],
            $row['nickname'],
            $row['mobile'],
            $row['invite_code'],
            $row['level_id'],
            $row['balance'],
            $row['total_recharge'],
            $row['total_withdraw'],
            $row['status'] ? '正常' : '禁用',
            $row['is_virtual'] ? '是' : '否',
            $row['is_proxy'] ? '是' : '否',
            $row['withdraw_status'] ? '开启' : '关闭',
            $row['registered_at_text'],
        ], $rows);

        $content = CsvHelper::build(
            ['ID', '账号', '昵称', '手机', '邀请码', '等级', '余额', '累计充值', '累计提现', '状态', '虚拟号', '代理', '提现', '注册时间'],
            $csvRows
        );

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="members_' . date('YmdHis') . '.csv"',
        ]);
    }

    private function listFilters(Request $request): array
    {
        return $request->only([
            'keyword', 'id', 'username', 'invite_code', 'real_name', 'contact',
            'parent_invite_code', 'user_type', 'status', 'is_virtual', 'is_activated',
            'kyc_status', 'is_proxy', 'withdraw_status', 'is_invite_code_enabled',
            'level_id', 'leader_id', 'register_start', 'register_end', 'ip', 'ids',
        ]);
    }

    // ==================== 状态切换 ====================

    public function toggleStatus(Request $request, int $id)
    {
        $field = (string) $request->post('field', '');
        $value = (int) $request->post('value', 0);

        if ($field === '') {
            return fail(42201, 'field 不能为空');
        }

        return success($this->memberService->toggleStatus($id, $field, $value), '状态更新成功');
    }

    // ==================== 钱包调整 ====================

    public function adjustWallet(Request $request, int $id)
    {
        $walletType = (string) $request->post('type', '');
        $direction  = (string) $request->post('direction', 'income');
        $amount     = (string) $request->post('amount', '0');
        $remark     = (string) $request->post('remark', '');

        if (!in_array($direction, ['income', 'expense'], true)) {
            return fail(42201, 'direction 必须是 income 或 expense');
        }

        if ((float) $amount <= 0) {
            return fail(42201, '金额必须大于 0');
        }

        $result = $this->memberService->adjustWallet(
            $id, $walletType, $direction, $amount, $remark, $this->adminId($request)
        );

        return success($result, '操作成功');
    }

    // ==================== 密码 ====================

    public function resetPassword(Request $request, int $id)
    {
        $password = (string) $request->post('password', '');
        if ($password === '') {
            return fail(42201, '新密码不能为空');
        }
        if (strlen($password) < 6) {
            return fail(42201, '密码至少 6 位');
        }

        $this->memberService->resetPassword($id, $password);
        return success(null, '密码重置成功');
    }

    public function resetPayPassword(Request $request, int $id)
    {
        $payPassword = trim((string) ($request->post('pay_password')
            ?? $request->post('paypwd')
            ?? ''));
        if ($payPassword === '') {
            return fail(42201, '支付密码不能为空');
        }
        if (strlen($payPassword) < 6) {
            return fail(42201, '支付密码至少 6 位');
        }

        $this->memberService->resetPayPassword($id, $payPassword);
        return success(null, '支付密码重置成功');
    }

    // ==================== 上级 ====================

    public function changeParent(Request $request, int $id)
    {
        $parentId = (int) $request->post('parent_id', 0);
        if ($parentId <= 0) {
            return fail(42201, '上级ID不能为空');
        }

        return success($this->memberService->changeParent($id, $parentId), '上级修改成功');
    }

    // ==================== 下级 ====================

    public function subordinates(Request $request, int $id)
    {
        [$page, $limit] = $this->pageParams($request);
        $level = (int) $request->get('level', 99); // 99=全部
        $filters = $request->only(['uid', 'username', 'invite_code']);
        return success($this->memberService->subordinates($id, $level, $page, $limit, $filters));
    }

    // ==================== 团队 ====================

    public function teamInfo(Request $request, int $id)
    {
        return success($this->memberService->teamInfo($id));
    }

    public function getAdBufferCountdownConfig(Request $request, int $id)
    {
        return success((new \app\service\MemberAdCountdownService())->getConfig($id));
    }

    public function updateAdBufferCountdownConfig(Request $request, int $id)
    {
        return success(
            (new \app\service\MemberAdCountdownService())->saveConfig($id, $request->post()),
            '保存成功'
        );
    }

    public function getAdRatioConfig(Request $request, int $id)
    {
        return success((new \app\service\MemberAdRatioService())->getBundle($id));
    }

    public function updateAdRatioConfig(Request $request, int $id)
    {
        return success(
            (new \app\service\MemberAdRatioService())->saveConfig($id, $request->post()),
            '保存成功'
        );
    }

    public function toggleAdRatioConfig(Request $request, int $id)
    {
        $enabled = (int) $request->post('enabled', 0) === 1;
        return success(
            (new \app\service\MemberAdRatioService())->setEnabled($id, $enabled),
            $enabled ? '单控比例已启用' : '单控比例已关闭'
        );
    }
}
