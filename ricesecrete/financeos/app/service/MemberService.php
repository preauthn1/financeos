<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\ad\AdPlanModel;
use app\model\ad\AdPlanUserModel;
use app\model\member\UserCardModel;
use app\model\member\UserModel;
use app\model\member\UserWaterModel;
use app\model\member\UserLoginModel;
use app\model\member\UserLogModel;
use app\support\IpLocationResolver;
use app\model\member\UserIpModel;
use app\support\ErrorCode;
use app\support\UserWaterDetailType;
use app\support\UserWaterType;
use Illuminate\Database\Capsule\Manager as DB;

class MemberService
{
    private const WALLET_TYPES = [
        'balance'          => ['label' => '基础钱包', 'field' => 'balance'],
        'recharge_balance' => ['label' => '累计充值', 'field' => 'recharge_balance'],
        'trial_balance'    => ['label' => '彩金钱包', 'field' => 'trial_balance'],
        'frozen_balance'   => ['label' => '冻结钱包', 'field' => 'frozen_balance'],
        'brokerage_balance'=> ['label' => '佣金池',   'field' => 'brokerage_balance'],
        'points'           => ['label' => '积分',     'field' => 'points'],
    ];

    // ==================== 列表 ====================

    public function list(int $page, int $limit, array $filters = []): array
    {
        $query = UserModel::query();
        $this->applyListFilters($query, $filters);

        $total = (clone $query)->count();
        $items = $query->orderBy('id', 'desc')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        $ids = $items->pluck('id')->map(static fn ($id) => (int) $id)->all();
        $teamStats = $this->aggregateTeamStats($ids);
        $leaderIds = $this->collectLeaderIds($items);
        $leaderMap = $this->loadLeaderMap($leaderIds);
        $sameIpCounts = $this->aggregateSameIpCounts($items);
        $directCounts = $this->aggregateDirectCounts($ids);
        $todayStart = strtotime(date('Y-m-d 00:00:00'));

        $enrichedItems = $items->map(function (UserModel $user) use (
            $teamStats,
            $leaderMap,
            $sameIpCounts,
            $directCounts,
            $todayStart
        ) {
            return $this->formatListItem($user, $teamStats, $leaderMap, $sameIpCounts, $directCounts, $todayStart);
        })->values()->all();

        return [
            'total' => $total,
            'items' => $enrichedItems,
            'kpis'  => $this->buildListKpis(),
        ];
    }

    private function applyListFilters($query, array $filters): void
    {
        if (!empty($filters['keyword'])) {
            $kw = (string) $filters['keyword'];
            $query->where(function ($q) use ($kw) {
                $q->where('username', 'like', "%{$kw}%")
                    ->orWhere('nickname', 'like', "%{$kw}%")
                    ->orWhere('mobile', 'like', "%{$kw}%")
                    ->orWhere('invite_code', 'like', "%{$kw}%")
                    ->orWhere('real_name', 'like', "%{$kw}%")
                    ->orWhere('last_login_ip', 'like', "%{$kw}%");
            });
        }

        if (!empty($filters['id'])) {
            $query->where('id', (int) $filters['id']);
        }

        if (!empty($filters['ids'])) {
            $ids = is_array($filters['ids'])
                ? array_map('intval', $filters['ids'])
                : array_map('intval', array_filter(explode(',', (string) $filters['ids'])));
            if ($ids !== []) {
                $query->whereIn('id', $ids);
            }
        }

        if (!empty($filters['username'])) {
            $query->where('username', 'like', '%' . $filters['username'] . '%');
        }

        if (!empty($filters['invite_code'])) {
            $query->where('invite_code', 'like', '%' . $filters['invite_code'] . '%');
        }

        if (!empty($filters['real_name'])) {
            $query->where('real_name', 'like', '%' . $filters['real_name'] . '%');
        }

        if (!empty($filters['contact'])) {
            $contact = (string) $filters['contact'];
            $query->where(function ($q) use ($contact) {
                $q->where('mobile', 'like', "%{$contact}%")
                    ->orWhere('email', 'like', "%{$contact}%")
                    ->orWhere('username', 'like', "%{$contact}%");
            });
        }

        if (!empty($filters['parent_invite_code'])) {
            $parentInviteCode = (string) $filters['parent_invite_code'];
            $parentIds = UserModel::query()
                ->where('invite_code', 'like', "%{$parentInviteCode}%")
                ->pluck('id')
                ->map(static fn ($id) => (int) $id)
                ->all();
            if ($parentIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function ($q) use ($parentIds) {
                    $q->whereIn('leader1', $parentIds)
                        ->orWhereIn('leader2', $parentIds)
                        ->orWhereIn('leader3', $parentIds);
                });
            }
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int) $filters['status']);
        }

        if (isset($filters['is_virtual']) && $filters['is_virtual'] !== '') {
            $query->where('is_virtual', (int) $filters['is_virtual']);
        }

        if (isset($filters['is_activated']) && $filters['is_activated'] !== '') {
            $query->where('is_activated', (int) $filters['is_activated']);
        }

        if (isset($filters['kyc_status']) && $filters['kyc_status'] !== '') {
            $query->where('kyc_status', (int) $filters['kyc_status']);
        }

        if (isset($filters['is_proxy']) && $filters['is_proxy'] !== '') {
            $query->where('is_proxy', (int) $filters['is_proxy']);
        }

        if (isset($filters['withdraw_status']) && $filters['withdraw_status'] !== '') {
            $query->where('withdraw_status', (int) $filters['withdraw_status']);
        }

        if (isset($filters['is_invite_code_enabled']) && $filters['is_invite_code_enabled'] !== '') {
            $query->where('is_invite_code_enabled', (int) $filters['is_invite_code_enabled']);
        }

        if (!empty($filters['level_id'])) {
            $query->where('level_id', (int) $filters['level_id']);
        }

        if (!empty($filters['leader_id'])) {
            $lid = (int) $filters['leader_id'];
            $query->where(function ($q) use ($lid) {
                $q->where('leader1', $lid)
                    ->orWhere('leader2', $lid)
                    ->orWhere('leader3', $lid);
            });
        }

        if (!empty($filters['register_start'])) {
            $query->where('registered_at', '>=', strtotime((string) $filters['register_start']));
        }
        if (!empty($filters['register_end'])) {
            $query->where('registered_at', '<=', strtotime((string) $filters['register_end'] . ' 23:59:59'));
        }

        if (!empty($filters['ip'])) {
            $ip = (string) $filters['ip'];
            $query->where(function ($q) use ($ip) {
                $q->where('last_login_ip', 'like', "%{$ip}%")
                    ->orWhere('mobile', 'like', "%{$ip}%")
                    ->orWhere('username', 'like', "%{$ip}%");
            });
        }

        if (!empty($filters['user_type'])) {
            $this->applyUserTypeFilter($query, (string) $filters['user_type']);
        }
    }

    private function applyUserTypeFilter($query, string $userType): void
    {
        switch ($userType) {
            case 'virtual':
                $query->where('is_virtual', 1);
                break;
            case 'real':
                $query->where('is_virtual', 0);
                break;
            case 'withdraw_no_recharge':
                $query->where('total_recharge', '<=', 0)
                    ->where('total_withdraw', '>', 0);
                break;
            case 'never_login':
                $query->where(function ($q) {
                    $q->whereNull('last_login_at')
                        ->orWhere('last_login_at', 0);
                });
                break;
            case 'no_bank_card':
                $query->whereDoesntHave('cards', static function ($q) {
                    $q->where('status', 1);
                });
                break;
            case 'has_bank_card':
                $query->whereHas('cards', static function ($q) {
                    $q->where('status', 1);
                });
                break;
            case 'login_disabled':
                $query->where('status', 0);
                break;
            case 'ad_disabled':
                $query->where('ad_plan_id', 0);
                break;
            case 'ad_frozen':
                $query->where('ad_plan_id', '>', 0)
                    ->where('frozen_balance', '>', 0);
                break;
        }
    }

    private function buildListKpis(): array
    {
        $todayStart = strtotime(date('Y-m-d 00:00:00'));
        $todayEnd = strtotime(date('Y-m-d 23:59:59'));

        $todayRechargeUserIds = UserWaterModel::query()
            ->where('type', 2)
            ->where('direction', 1)
            ->where('created_at', '>=', $todayStart)
            ->where('created_at', '<=', $todayEnd)
            ->distinct()
            ->pluck('user_id');

        $todayWithdrawUserIds = UserWaterModel::query()
            ->where('type', 2)
            ->where('direction', 2)
            ->where('created_at', '>=', $todayStart)
            ->where('created_at', '<=', $todayEnd)
            ->distinct()
            ->pluck('user_id');

        return [
            'total_members'          => UserModel::where('is_virtual', 0)->count(),
            'today_register'         => UserModel::where('registered_at', '>=', $todayStart)->count(),
            'today_active'           => UserModel::where('last_login_at', '>=', $todayStart)->count(),
            'today_recharge_count'   => $todayRechargeUserIds->count(),
            'today_withdraw_count'   => $todayWithdrawUserIds->count(),
            'abnormal_count'         => UserModel::where(function ($q) {
                $q->where('kyc_status', 1)
                    ->orWhere('frozen_balance', '>', 0);
            })->count(),
        ];
    }

    /**
     * @param array<int, int> $parentIds
     * @return array<int, array{team_recharge: float, team_withdraw: float, team_pending_withdraw: float, team_count: int, team_active: int}>
     */
    private function aggregateTeamStats(array $parentIds): array
    {
        if ($parentIds === []) {
            return [];
        }

        $stats = [];
        foreach ($parentIds as $parentId) {
            $stats[$parentId] = [
                'team_recharge' => 0.0,
                'team_withdraw' => 0.0,
                'team_pending_withdraw' => 0.0,
                'team_count' => 0,
                'team_active' => 0,
            ];
        }

        $todayStart = strtotime(date('Y-m-d 00:00:00'));
        foreach ([1, 2, 3] as $level) {
            $rows = UserModel::query()
                ->selectRaw(
                    "leader{$level} as parent_id, " .
                    'COUNT(*) as team_count, ' .
                    'SUM(total_recharge) as team_recharge, ' .
                    'SUM(total_withdraw) as team_withdraw, ' .
                    'SUM(frozen_balance) as team_pending_withdraw, ' .
                    "SUM(CASE WHEN last_login_at >= {$todayStart} THEN 1 ELSE 0 END) as team_active"
                )
                ->whereIn("leader{$level}", $parentIds)
                ->groupBy("leader{$level}")
                ->get();

            foreach ($rows as $row) {
                $parentId = (int) $row->parent_id;
                if ($parentId <= 0 || !isset($stats[$parentId])) {
                    continue;
                }
                $stats[$parentId]['team_recharge'] += (float) $row->team_recharge;
                $stats[$parentId]['team_withdraw'] += (float) $row->team_withdraw;
                $stats[$parentId]['team_pending_withdraw'] += (float) $row->team_pending_withdraw;
                $stats[$parentId]['team_count'] += (int) $row->team_count;
                $stats[$parentId]['team_active'] += (int) $row->team_active;
            }
        }

        return $stats;
    }

    /**
     * @param iterable<UserModel> $items
     * @return array<int, int>
     */
    private function collectLeaderIds(iterable $items): array
    {
        $leaderIds = [];
        foreach ($items as $user) {
            foreach (['leader1', 'leader2', 'leader3', 'leader7'] as $field) {
                $leaderId = (int) ($user->{$field} ?? 0);
                if ($leaderId > 0) {
                    $leaderIds[$leaderId] = $leaderId;
                }
            }
        }

        return array_values($leaderIds);
    }

    /**
     * @param array<int, int> $leaderIds
     * @return array<int, array{id:int,username:string,nickname:string,level_id:int}>
     */
    private function loadLeaderMap(array $leaderIds): array
    {
        if ($leaderIds === []) {
            return [];
        }

        return UserModel::query()
            ->whereIn('id', $leaderIds)
            ->get(['id', 'username', 'nickname', 'remark', 'level_id'])
            ->keyBy('id')
            ->map(static fn (UserModel $leader) => [
                'id' => (int) $leader->id,
                'username' => (string) $leader->username,
                'nickname' => (string) $leader->nickname,
                'remark' => (string) ($leader->remark ?? ''),
                'level_id' => (int) $leader->level_id,
            ])
            ->all();
    }

    /**
     * @param iterable<UserModel> $items
     * @return array<string, int>
     */
    private function aggregateSameIpCounts(iterable $items): array
    {
        $ips = [];
        foreach ($items as $user) {
            $ip = trim((string) ($user->last_login_ip ?? ''));
            if ($ip !== '') {
                $ips[$ip] = $ip;
            }
        }

        if ($ips === []) {
            return [];
        }

        $rows = UserModel::query()
            ->selectRaw('last_login_ip as ip, COUNT(*) as user_count')
            ->whereIn('last_login_ip', array_values($ips))
            ->groupBy('last_login_ip')
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row->ip] = max(0, (int) $row->user_count - 1);
        }

        return $counts;
    }

    /**
     * @param array<int, int> $parentIds
     * @return array<int, int>
     */
    private function aggregateDirectCounts(array $parentIds): array
    {
        if ($parentIds === []) {
            return [];
        }

        $rows = UserModel::query()
            ->selectRaw('leader1 as parent_id, COUNT(*) as direct_count')
            ->whereIn('leader1', $parentIds)
            ->groupBy('leader1')
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row->parent_id] = (int) $row->direct_count;
        }

        return $counts;
    }

    private function formatListItem(
        UserModel $user,
        array $teamStats,
        array $leaderMap,
        array $sameIpCounts,
        array $directCounts,
        int $todayStart
    ): array {
        $data = $user->toArray();
        $userId = (int) $user->id;
        $team = $teamStats[$userId] ?? [
            'team_recharge' => 0.0,
            'team_withdraw' => 0.0,
            'team_pending_withdraw' => 0.0,
            'team_count' => 0,
            'team_active' => 0,
        ];

        $data['wallet'] = [
            'balance' => (string) $user->balance,
            'total_recharge' => (string) $user->total_recharge,
            'total_withdraw' => (string) $user->total_withdraw,
            'pending_withdraw' => (string) $user->frozen_balance,
            'ad_frozen' => (float) $user->ad_plan_id > 0 ? (string) $user->frozen_balance : '0.0000',
            'parent_transfer' => '0.0000',
        ];

        $data['team'] = [
            'team_recharge' => number_format((float) $team['team_recharge'], 2, '.', ''),
            'team_withdraw' => number_format((float) $team['team_withdraw'], 2, '.', ''),
            'team_pending_withdraw' => number_format((float) $team['team_pending_withdraw'], 2, '.', ''),
            'team_count' => (int) $team['team_count'],
            'team_active' => (int) $team['team_active'],
        ];

        $data['promotion'] = [
            'top_agent' => $this->formatLeaderLabel($leaderMap[(int) $user->leader7] ?? null),
            'level1' => $this->formatLeaderLabel($leaderMap[(int) $user->leader1] ?? null),
            'level2' => $this->formatLeaderLabel($leaderMap[(int) $user->leader2] ?? null),
            'primary_agent' => $this->formatLeaderLabel($leaderMap[(int) $user->leader3] ?? null),
            'invite_code' => (string) $user->invite_code,
            'subordinate_count' => $directCounts[$userId] ?? 0,
        ];

        $data['login'] = [
            'registered_at' => (int) $user->registered_at,
            'registered_at_text' => $user->registered_at ? date('Y-m-d H:i:s', (int) $user->registered_at) : '-',
            'last_login_at' => (int) $user->last_login_at,
            'last_login_at_text' => $user->last_login_at ? date('Y-m-d H:i:s', (int) $user->last_login_at) : '-',
            'ip' => (string) $user->last_login_ip,
            'device_uuid' => (string) ($user->login_key ?: '-'),
            'same_ip_count' => $sameIpCounts[(string) $user->last_login_ip] ?? 0,
            'is_online' => (int) $user->last_login_at >= $todayStart,
        ];

        $registeredDays = $user->registered_at
            ? max(0, (int) floor((time() - (int) $user->registered_at) / 86400))
            : 0;

        $data['other'] = [
            'contact' => (string) ($user->mobile ?: $user->email ?: $user->username),
            'level_depth' => $this->calculateLevelDepth($user),
            'account_no' => (string) ($user->username ?: '-'),
            'real_name' => (string) ($user->real_name ?: '-'),
            'remark' => (string) ($user->remark ?: '-'),
        ];

        $data['vip'] = [
            'last_ad_buy_at' => '-',
            'last_ad_end_at' => '-',
            'today_trade_count' => 0,
            'total_trade_days' => 0,
            'register_days' => $registeredDays,
        ];

        $data['quick_toggles'] = [
            'status' => (int) $user->status,
            'withdraw_status' => (int) $user->withdraw_status,
            'is_invite_code_enabled' => (int) $user->is_invite_code_enabled,
            'is_proxy' => (int) $user->is_proxy,
        ];

        return $data;
    }

    private function formatLeaderLabel(?array $leader): string
    {
        if ($leader === null) {
            return '-';
        }

        $name = UserModel::displayLabel($leader, (int) ($leader['id'] ?? 0));

        return sprintf('%d/%s', (int) $leader['id'], $name);
    }

    private function calculateLevelDepth(UserModel $user): int
    {
        $depth = 0;
        foreach (['leader1', 'leader2', 'leader3', 'leader4', 'leader5', 'leader6', 'leader7'] as $field) {
            if ((int) ($user->{$field} ?? 0) > 0) {
                $depth++;
            }
        }

        return $depth;
    }

    // ==================== 详情 ====================

    public function show(int $id): array
    {
        $user = UserModel::find($id);
        if (!$user) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '用户不存在');
        }

        $data = $user->toArray();
        $adPlanId = (int) $user->ad_plan_id;
        $data['ad_plan_name'] = AdPlanModel::getPlanName($adPlanId);
        $data['ad_plan_day'] = $adPlanId > 0 ? AdPlanModel::getDayCount($id, $adPlanId) : 0;

        return $data;
    }

    // ==================== 创建 ====================

    public function create(array $data, int $operatorId = 0): UserModel
    {
        $parentInviteCode = trim((string) ($data['parent_invite_code'] ?? ''));
        unset($data['parent_invite_code']);

        $data = $this->normalizeCreateData($data);

        if (UserModel::where('username', $data['username'])->exists()) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '用户名已存在');
        }

        if (empty($data['password'])) {
            $data['password'] = $this->generateRandomPassword();
        }
        $plainPassword = (string) $data['password'];
        $data['password'] = password_hash($plainPassword, PASSWORD_BCRYPT);
        if (!empty($data['pay_password'])) {
            $data['pay_password'] = password_hash($data['pay_password'], PASSWORD_BCRYPT);
        }
        $data['registered_at'] = time();
        $data['invite_code'] = $this->generateInviteCode();

        $user = UserModel::create($data);

        if ($parentInviteCode !== '') {
            $parent = UserModel::where('invite_code', $parentInviteCode)->first();
            if (!$parent) {
                $user->delete();
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '上级邀请码不存在');
            }
            $this->applyParentLeaders($user, $parent);
        }

        // 记录注册日志
        UserLogModel::create([
            'user_id'    => $user->id,
            'ip'         => $data['ip'] ?? '',
            'area'       => IpLocationResolver::resolveForStorage((string) ($data['ip'] ?? '')),
            'type'       => 1, // 注册
            'device'     => '',
        ]);

        if (!empty($data['ip'])) {
            UserIpModel::firstOrCreate(['ip' => $data['ip'], 'user_id' => $user->id]);
        }

        return $user;
    }

    /**
     * @param array<int, array<string, mixed>> $accounts
     */
    public function batchCreate(array $accounts, array $defaults = [], int $operatorId = 0): array
    {
        $success = 0;
        $failed = [];

        foreach ($accounts as $index => $row) {
            $username = trim((string) ($row['username'] ?? ''));
            if ($username === '') {
                continue;
            }

            try {
                $payload = array_merge($defaults, $row, ['username' => $username]);
                $this->create($payload, $operatorId);
                $success++;
            } catch (\Throwable $e) {
                $failed[] = [
                    'index'    => $index,
                    'username' => $username,
                    'message'  => $e->getMessage(),
                ];
            }
        }

        return [
            'total'   => count($accounts),
            'success' => $success,
            'failed'  => $failed,
        ];
    }

    // ==================== 编辑 ====================

    public function update(int $id, array $data): UserModel
    {
        $user = UserModel::find($id);
        if (!$user) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '用户不存在');
        }

        $loginPassword = trim((string) ($data['password'] ?? $data['login_password'] ?? ''));
        $payPassword = trim((string) ($data['pay_password'] ?? $data['paypwd'] ?? ''));

        // 敏感字段不允许直接改余额类；密码单独处理
        unset(
            $data['password'],
            $data['login_password'],
            $data['pay_password'],
            $data['paypwd'],
            $data['balance'],
            $data['recharge_balance'],
            $data['brokerage_balance'],
            $data['frozen_balance'],
            $data['trial_balance'],
            $data['points'],
            $data['total_recharge'],
            $data['total_withdraw'],
            $data['total_earnings'],
            $data['total_brokerage']
        );

        if (array_key_exists('level_id', $data)) {
            $data['level_id'] = (int) ($data['level_id'] ?: 0);
        }

        if (array_key_exists('ad_plan_id', $data)) {
            $this->assignAdPlan($user, (int) $data['ad_plan_id']);
            unset($data['ad_plan_id']);
        }

        $this->applyDefaultPhoneCode($data, $user);

        if ($loginPassword !== '') {
            if (strlen($loginPassword) < 6) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '登录密码至少 6 位');
            }
            $data['password'] = password_hash($loginPassword, PASSWORD_BCRYPT);
        }

        if ($payPassword !== '') {
            if (strlen($payPassword) < 6) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '安全密码至少 6 位');
            }
            $data['pay_password'] = password_hash($payPassword, PASSWORD_BCRYPT);
            // 后台重置后允许立即提现，不触发 24 小时限制
            $data['pay_password_changed_at'] = 0;
        }

        if ($data !== []) {
            $user->update($data);
        }

        return $user->fresh() ?? $user;
    }

    private function assignAdPlan(UserModel $user, int $adPlanId): void
    {
        $currentPlanId = (int) $user->ad_plan_id;
        if ($adPlanId === $currentPlanId) {
            return;
        }

        if ($adPlanId > 0) {
            $plan = AdPlanModel::find($adPlanId);
            if (!$plan) {
                throw new BusinessException(ErrorCode::NOT_FOUND, '广告购买计划不存在');
            }
        }

        $now = time();
        AdPlanUserModel::create([
            'user_id' => $user->id,
            'ad_plan_id' => $adPlanId,
            'previous_plan_id' => $currentPlanId,

        ]);
        $user->update(['ad_plan_id' => $adPlanId]);
    }

    // ==================== 删除 ====================

    public function delete(int $id): void
    {
        throw new BusinessException(ErrorCode::VALIDATION_FAILED, '会员不允许删除');
    }

    // ==================== 批量操作 ====================

    public function batchUpdateStatus(array $ids, int $status): int
    {
        return UserModel::whereIn('id', $ids)->update(['status' => $status]);
    }

    /**
     * @param array<int, int|string> $ids
     * @param array<string, mixed> $params
     * @return array{success: int, failed: int}
     */
    public function batchExecute(array $ids, string $action, array $params = []): array
    {
        $success = 0;
        $failed = 0;

        foreach ($ids as $id) {
            $userId = (int) $id;
            if ($userId <= 0) {
                $failed++;
                continue;
            }

            try {
                match ($action) {
                    'enable' => $this->toggleStatus($userId, 'status', 1),
                    'disable', 'ban' => $this->toggleStatus($userId, 'status', 0),
                    'unban' => $this->toggleStatus($userId, 'status', 1),
                    'withdraw_on' => $this->toggleStatus($userId, 'withdraw_status', 1),
                    'withdraw_off' => $this->toggleStatus($userId, 'withdraw_status', 0),
                    'invite_on' => $this->toggleStatus($userId, 'is_invite_code_enabled', 1),
                    'invite_off' => $this->toggleStatus($userId, 'is_invite_code_enabled', 0),
                    'proxy_on', 'proxy_set' => $this->toggleStatus($userId, 'is_proxy', 1),
                    'proxy_off', 'proxy_unset' => $this->toggleStatus($userId, 'is_proxy', 0),
                    'level_set' => $this->update($userId, ['level_id' => (int) ($params['level_id'] ?? 0)]),
                    'reset_login_pwd' => $this->resetPassword($userId, (string) ($params['password'] ?? '')),
                    'reset_pay_pwd' => $this->resetPayPassword($userId, (string) ($params['pay_password'] ?? '')),
                    default => throw new BusinessException(ErrorCode::VALIDATION_FAILED, "不支持的操作: {$action}"),
                };
                $success++;
            } catch (\Throwable) {
                $failed++;
            }
        }

        return ['success' => $success, 'failed' => $failed];
    }

    /**
     * @return list<int>
     */
    public function listIds(array $filters = [], int $limit = 5000): array
    {
        $query = UserModel::query();
        $this->applyListFilters($query, $filters);

        return $query->orderByDesc('id')
            ->limit($limit)
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return list<int>
     */
    public function listIdsByAgentLine(string $line, int $limit = 5000): array
    {
        $roots = (new SettingService())->getValue('agent_line_roots', [
            'bai_zhi' => 0,
            'nuo_nuo' => 0,
            'da_shun' => 0,
        ]);
        if (is_string($roots)) {
            $roots = json_decode($roots, true) ?: [];
        }
        $rootId = (int) (is_array($roots) ? ($roots[$line] ?? 0) : 0);
        if ($rootId <= 0) {
            return [];
        }

        return UserModel::query()
            ->where(function ($q) use ($rootId) {
                $q->where('id', $rootId)
                    ->orWhere('tree_id', 'like', "%/{$rootId}/%");
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function exportRows(array $filters = [], int $limit = 5000): array
    {
        $query = UserModel::query();
        $this->applyListFilters($query, $filters);

        return $query->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function (UserModel $user) {
                return [
                    'id' => $user->id,
                    'username' => $user->username,
                    'nickname' => $user->nickname,
                    'mobile' => $user->mobile,
                    'invite_code' => $user->invite_code,
                    'level_id' => (int) $user->level_id,
                    'balance' => (string) $user->balance,
                    'total_recharge' => (string) $user->total_recharge,
                    'total_withdraw' => (string) $user->total_withdraw,
                    'status' => (int) $user->status,
                    'is_virtual' => (int) $user->is_virtual,
                    'is_proxy' => (int) $user->is_proxy,
                    'withdraw_status' => (int) $user->withdraw_status,
                    'registered_at' => (int) $user->registered_at,
                    'registered_at_text' => $user->registered_at ? date('Y-m-d H:i:s', (int) $user->registered_at) : '',
                ];
            })
            ->all();
    }

    public function batchDelete(array $ids): int
    {
        throw new BusinessException(ErrorCode::VALIDATION_FAILED, '会员不允许删除');
    }

    // ==================== 状态切换 ====================

    public function toggleStatus(int $id, string $field, int $value): UserModel
    {
        $allowedFields = [
            'status', 'is_activated', 'is_invite_code_enabled',
            'is_proxy', 'withdraw_status', 'is_credit_score_enabled',
            'is_transfer_enabled',
        ];

        if (!in_array($field, $allowedFields, true)) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, "不允许修改字段: {$field}");
        }

        $user = UserModel::find($id);
        if (!$user) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '用户不存在');
        }

        $user->update([$field => $value]);
        return $user;
    }

    // ==================== 密码操作 ====================

    public function resetPassword(int $id, string $password): void
    {
        $user = UserModel::find($id);
        if (!$user) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '用户不存在');
        }
        $user->update(['password' => password_hash($password, PASSWORD_BCRYPT)]);
    }

    public function resetPayPassword(int $id, string $payPassword): void
    {
        $user = UserModel::find($id);
        if (!$user) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '用户不存在');
        }
        if (strlen($payPassword) < 6) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '安全密码至少 6 位');
        }
        $user->update([
            'pay_password' => password_hash($payPassword, PASSWORD_BCRYPT),
            // 后台重置后允许立即提现
            'pay_password_changed_at' => 0,
        ]);
    }

    // ==================== 钱包调整（事务 + 悲观锁） ====================

    public function adjustWallet(int $id, string $walletType, string $direction, string $amount, string $remark = '', int $adminId = 0): array
    {
        if (!array_key_exists($walletType, UserWaterType::ADMIN_WALLET_KEY_TO_TYPE)) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, "不支持的钱包类型: {$walletType}");
        }

        $typeInfo = self::WALLET_TYPES[$walletType] ?? null;
        if (!$typeInfo) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, "不支持的钱包类型: {$walletType}");
        }

        $field = $typeInfo['field'];
        $amountVal = $direction === 'expense' ? -abs((float) $amount) : abs((float) $amount);

        return DB::transaction(function () use ($id, $field, $amountVal, $remark, $adminId, $walletType, $typeInfo) {
            $user = UserModel::where('id', $id)->lockForUpdate()->first();
            if (!$user) {
                throw new BusinessException(ErrorCode::NOT_FOUND, '用户不存在');
            }

            $before = (float) $user->{$field};
            $after = $before + $amountVal;

            if ($amountVal < 0 && $after < 0) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '余额不足，操作后余额不能为负');
            }

            $user->{$field} = $after;
            $user->save();

            // 记录流水
            $changeLabel = $amountVal >= 0 ? '增加' : '扣除';
            $title = "后台手动{$changeLabel}";
            $remarks = trim("后台手动{$changeLabel}:{$amountVal} " . $remark);

            UserWaterModel::create([
                'user_id'        => $id,
                'admin_id'       => $adminId,
                'title'          => $title,
                'type'           => UserWaterType::waterTypeForWalletKey($walletType),
                'detail_type'    => UserWaterDetailType::ADMIN_ADJUST,
                'direction'      => $amountVal >= 0 ? 1 : 2,
                'amount'         => abs($amountVal),
                'before_balance' => $before,
                'after_balance'  => $after,
                'remark'         => $remarks,
                'status'         => 1,
            ]);

            return [
                'user'    => $user->fresh(),
                'before'  => $before,
                'after'   => $after,
                'changed' => $amountVal,
            ];
        });
    }

    // ==================== 修改上级 ====================

    public function changeParent(int $id, int $parentId): UserModel
    {
        $user = UserModel::find($id);
        if (!$user) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '用户不存在');
        }

        if ($id === $parentId) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '上级不能是自己');
        }

        $parent = UserModel::find($parentId);
        if (!$parent) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '上级用户不存在');
        }

        $ancestors = [$parentId];
        $leaders = [$parent->leader1, $parent->leader2, $parent->leader3,
                     $parent->leader4, $parent->leader5, $parent->leader6, $parent->leader7];
        foreach ($leaders as $lid) {
            if ($lid > 0) {
                $ancestors[] = (int) $lid;
            }
        }
        if (in_array($id, $ancestors, true)) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '不能设置自己的下级为上级');
        }

        return DB::transaction(function () use ($user, $parent) {
            $this->applyParentLeaders($user, $parent);
            $this->rebuildDescendantLeaders((int) $user->id);

            return $user->fresh();
        });
    }

    // ==================== 下级查询 ====================

    public function subordinates(int $id, int $level, int $page, int $limit, array $filters = []): array
    {
        $user = UserModel::find($id);
        if (!$user) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '用户不存在');
        }

        $baseQuery = UserModel::query();
        if ($level >= 1 && $level <= 7) {
            $baseQuery->where("leader{$level}", $id);
        } else {
            $baseQuery->where(function ($q) use ($id) {
                $q->where('leader1', $id)
                    ->orWhere('leader2', $id)
                    ->orWhere('leader3', $id)
                    ->orWhere('leader4', $id)
                    ->orWhere('leader5', $id)
                    ->orWhere('leader6', $id)
                    ->orWhere('leader7', $id);
            });
        }

        if (!empty($filters['uid'])) {
            $baseQuery->where('id', (int) $filters['uid']);
        }
        if (!empty($filters['username'])) {
            $kw = (string) $filters['username'];
            $baseQuery->where('username', 'like', "%{$kw}%");
        }
        if (!empty($filters['invite_code'])) {
            $kw = (string) $filters['invite_code'];
            $baseQuery->where('invite_code', 'like', "%{$kw}%");
        }

        $query = clone $baseQuery;

        $total = $query->count();
        $items = $query->orderBy('id', 'desc')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        $leaderIds = $items->pluck('leader1')->map(static fn ($v) => (int) $v)->filter()->unique()->values()->all();
        $leaderMap = UserModel::query()
            ->whereIn('id', $leaderIds)
            ->get(['id', 'username', 'remark'])
            ->keyBy('id')
            ->map(static fn (UserModel $u) => UserModel::displayLabel($u))
            ->all();

        $levelCounts = [0 => 0];
        for ($i = 1; $i <= 7; $i++) {
            $levelCounts[$i] = UserModel::where("leader{$i}", $id)->count();
            $levelCounts[0] += $levelCounts[$i];
        }

        $teamUserIds = (clone $baseQuery)->pluck('id')->map(static fn ($v) => (int) $v)->all();

        $summary = (new TeamSubordinateStatsService())->buildSummary($teamUserIds);

        $enriched = $items->map(function (UserModel $u) use ($id, $leaderMap) {
            $treeLevel = 0;
            for ($i = 1; $i <= 7; $i++) {
                if ((int) $u->{"leader{$i}"} === $id) {
                    $treeLevel = $i;
                    break;
                }
            }

            $userMoney = (float) $u->balance + (float) $u->brokerage_balance;

            return [
                'id' => (int) $u->id,
                'user_name' => (string) $u->username,
                'username' => (string) $u->username,
                'tree_level' => $treeLevel,
                'invite_code' => (string) $u->invite_code,
                'leader1_user_name' => $leaderMap[(int) $u->leader1] ?? '',
                'recharge_sum' => (string) $u->total_recharge,
                'recharge_sum_all' => (string) $u->total_recharge,
                'frozen_money' => (string) $u->frozen_balance,
                'withdraw' => (string) $u->total_withdraw,
                'product_frozen_money' => '0',
                'user_money' => (string) $userMoney,
                'receive_from_other_user' => '0',
                'team_count' => (int) $u->team_count,
                'team_money' => '0',
                'remark' => (string) ($u->remark ?? ''),
                // 兼容前端/扩展字段
                'raw' => $u->toArray(),
            ];
        })->values()->all();

        return [
            'total' => $total,
            'items' => $enriched,
            'level_counts' => $levelCounts,
            'summary' => $summary,
            'member' => [
                'id' => (int) $user->id,
                'username' => (string) $user->username,
                'invite_code' => (string) $user->invite_code,
            ],
        ];
    }

    public function teamInfo(int $id): array
    {
        $user = UserModel::find($id);
        if (!$user) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '用户不存在');
        }

        $levelCounts = [];
        $totalTeam = 0;
        for ($i = 1; $i <= 7; $i++) {
            $levelCounts[$i] = UserModel::where("leader{$i}", $id)->count();
            $totalTeam += $levelCounts[$i];
        }

        return [
            'id'            => $user->id,
            'username'      => $user->username,
            'team_count'    => $user->team_count,
            'level1_count'  => $levelCounts[1] ?? 0,
            'level2_count'  => $levelCounts[2] ?? 0,
            'level3_count'  => $levelCounts[3] ?? 0,
            'level4_count'  => $levelCounts[4] ?? 0,
            'level5_count'  => $levelCounts[5] ?? 0,
            'level6_count'  => $levelCounts[6] ?? 0,
            'level7_count'  => $levelCounts[7] ?? 0,
            'total_team'    => $totalTeam,
            'leader1'       => $this->findLeaderInfo($user->leader1),
            'leader2'       => $this->findLeaderInfo($user->leader2),
            'leader3'       => $this->findLeaderInfo($user->leader3),
        ];
    }

    private function findLeaderInfo(int $id): ?array
    {
        if ($id <= 0) return null;
        $leader = UserModel::find($id, ['id', 'username', 'nickname', 'remark', 'level_id']);
        return $leader ? $leader->toArray() : null;
    }

    // ==================== 辅助 ====================

    private function normalizeCreateData(array $data): array
    {
        $data['username'] = trim((string) ($data['username'] ?? ''));
        if ($data['username'] === '') {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '用户账号不能为空');
        }

        $accountType = (string) ($data['account_type'] ?? 'mobile');
        unset($data['account_type']);

        if ($accountType === 'email') {
            $data['email'] = $data['username'];
        } else {
            $data['mobile'] = $data['username'];
        }

        $data['level_id'] = (int) ($data['level_id'] ?? 0);
        $data['status'] = (int) ($data['status'] ?? 1);
        $data['is_virtual'] = (int) ($data['is_virtual'] ?? 0);
        $data['is_invite_code_enabled'] = (int) ($data['is_invite_code_enabled'] ?? 1);
        $data['lang'] = (string) ($data['lang'] ?? 'zh-cn');

        foreach (['pay_password', 'nickname', 'real_name', 'phone_code', 'avatar', 'remark', 'ip'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                unset($data[$field]);
            }
        }

        if (!empty($data['mobile']) && empty($data['phone_code'])) {
            $data['phone_code'] = '+86';
        }

        unset($data['invite_code']);

        return $data;
    }

    private function applyDefaultPhoneCode(array &$data, ?UserModel $user = null): void
    {
        $mobile = trim((string) ($data['mobile'] ?? $user?->mobile ?? ''));
        if ($mobile === '') {
            return;
        }

        $phoneCode = trim((string) ($data['phone_code'] ?? $user?->phone_code ?? ''));
        if ($phoneCode === '') {
            $data['phone_code'] = '+86';
        }
    }

    private function applyParentLeaders(UserModel $user, UserModel $parent): void
    {
        $user->leader1 = $parent->id;
        $user->leader2 = (int) ($parent->leader1 ?? 0);
        $user->leader3 = (int) ($parent->leader2 ?? 0);
        $user->leader4 = (int) ($parent->leader3 ?? 0);
        $user->leader5 = (int) ($parent->leader4 ?? 0);
        $user->leader6 = (int) ($parent->leader5 ?? 0);
        $user->leader7 = (int) ($parent->leader6 ?? 0);
        $user->tree_id = $this->buildTreeId((string) $parent->tree_id, (int) $user->id);
        $user->save();
    }

    private function buildTreeId(string $parentTreeId, int $userId): string
    {
        $base = trim($parentTreeId, '/');
        if ($base === '') {
            return '/' . $userId . '/';
        }

        return '/' . $base . '/' . $userId . '/';
    }

    private function rebuildDescendantLeaders(int $rootUserId): void
    {
        $queue = UserModel::query()->where('leader1', $rootUserId)->get();
        while ($queue->isNotEmpty()) {
            /** @var UserModel $child */
            $child = $queue->shift();
            $parent = UserModel::find((int) $child->leader1);
            if ($parent) {
                $this->applyParentLeaders($child, $parent);
            }

            foreach (UserModel::query()->where('leader1', $child->id)->get() as $subChild) {
                $queue->push($subChild);
            }
        }
    }

    private function generateRandomPassword(int $length = 10): string
    {
        $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $password = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $max)];
        }

        return $password;
    }

    private function generateInviteCode(): string
    {
        do {
            $code = strtoupper(substr(md5(uniqid((string) mt_rand(), true)), 0, 8));
        } while (UserModel::where('invite_code', $code)->exists());

        return $code;
    }
}
