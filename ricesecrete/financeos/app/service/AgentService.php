<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\ad\AdPlanModel;
use app\model\ad\AdProductOrderModel;
use app\model\member\UserModel;
use app\model\member\UserWaterModel;
use app\model\pay\RechargeOrderModel;
use app\model\pay\WithdrawOrderModel;
use app\support\DateTimeFormat;
use app\support\ErrorCode;
use app\support\UserWaterDetailType;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Builder;

class AgentService
{
    public function __construct(
        private ?MemberService $memberService = null
    ) {
        $this->memberService = $memberService ?? new MemberService();
    }

    public function list(int $page, int $limit, array $filters = []): array
    {
        $query = UserModel::query()->where('is_proxy', 1);
        $this->applyFilters($query, $filters);

        $total = (clone $query)->count();
        $agents = $query->orderByDesc('team_count')
            ->orderByDesc('id')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        $agentIds = $agents->pluck('id')->map(static fn ($id) => (int) $id)->all();
        $treeStats = $this->aggregateTreeStats($agentIds);

        $items = $agents->map(function (UserModel $agent) use ($treeStats) {
            return $this->formatAgentItem($agent, $treeStats[$agent->id] ?? $this->emptyTreeStats());
        })->values()->all();

        return [
            'total' => $total,
            'items' => $items,
            'kpis'  => [
                'total_agents'  => UserModel::where('is_proxy', 1)->count(),
                'active_agents' => UserModel::where('is_proxy', 1)->where('status', 1)->count(),
                'total_team'    => (int) UserModel::where('leader1', '>', 0)->count(),
            ],
        ];
    }

    public function create(array $data, int $operatorId = 0): UserModel
    {
        $data['is_proxy'] = 1;
        $data['is_virtual'] = (int) ($data['is_virtual'] ?? 0);

        return $this->memberService->create($data, $operatorId);
    }

    public function batchUpdateStatus(array $ids, int $status): int
    {
        return $this->memberService->batchUpdateStatus($ids, $status);
    }

    public function subordinates(int $agentId, int $page, int $limit, array $filters = []): array
    {
        $agent = UserModel::where('id', $agentId)->where('is_proxy', 1)->first();
        if (!$agent) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '代理不存在');
        }

        $level = (int) ($filters['level'] ?? 99);
        unset($filters['level']);

        return $this->memberService->subordinates($agentId, $level, $page, $limit, $filters);
    }

    /**
     * @param Builder<UserModel> $query
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['id']) || !empty($filters['uid'])) {
            $query->where('id', (int) ($filters['id'] ?? $filters['uid']));
        }

        if (!empty($filters['username']) || !empty($filters['user_name'])) {
            $username = (string) ($filters['username'] ?? $filters['user_name']);
            $query->where('username', 'like', '%' . $username . '%');
        }

        if (!empty($filters['invite_code'])) {
            $query->where('invite_code', 'like', '%' . (string) $filters['invite_code'] . '%');
        }

        if (!empty($filters['keyword'])) {
            $kw = (string) $filters['keyword'];
            $query->where(function ($q) use ($kw) {
                $q->where('username', 'like', "%{$kw}%")
                    ->orWhere('nickname', 'like', "%{$kw}%")
                    ->orWhere('invite_code', 'like', "%{$kw}%");
            });
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int) $filters['status']);
        }

        if (!empty($filters['level_id'])) {
            $query->where('level_id', (int) $filters['level_id']);
        }

        if (!empty($filters['ip'])) {
            $ip = (string) $filters['ip'];
            $query->where(function ($q) use ($ip) {
                $q->where('last_login_ip', $ip)
                    ->orWhereExists(function ($sub) use ($ip) {
                        $sub->select(DB::raw(1))
                            ->from('me_user_ip')
                            ->whereColumn('me_user_ip.user_id', 'me_user.id')
                            ->where('me_user_ip.ip', $ip);
                    });
            });
        }

        $userType = (string) ($filters['user_type'] ?? $filters['is_xuni'] ?? '');
        if ($userType !== '' && $userType !== '-1') {
            $this->applyUserTypeFilter($query, $userType);
        }

        $start = (string) ($filters['register_start'] ?? $filters['start_time'] ?? $filters['startTime'] ?? '');
        $end = (string) ($filters['register_end'] ?? $filters['end_time'] ?? $filters['endTime'] ?? '');
        $timeType = (int) ($filters['time_type'] ?? 0);
        if ($start !== '' && $end !== '') {
            $startTs = strtotime($start . ' 00:00:00');
            $endTs = strtotime($end . ' 23:59:59');
            if ($timeType === 1) {
                $query->whereBetween('last_login_at', [$startTs, $endTs]);
            } else {
                $query->whereBetween('registered_at', [$startTs, $endTs]);
            }
        }
    }

    /**
     * @param Builder<UserModel> $query
     */
    private function applyUserTypeFilter(Builder $query, string $userType): void
    {
        switch ($userType) {
            case 'virtual':
            case '1':
                $query->where('is_virtual', 1);
                break;
            case 'real':
            case '0':
                $query->where('is_virtual', 0);
                break;
            case 'never_login':
            case '2':
                $query->where('login_count', 0);
                break;
            case 'no_bank_card':
            case '3':
                $query->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('me_user_card')
                        ->whereColumn('me_user_card.user_id', 'me_user.id');
                });
                break;
            case 'has_bank_card':
            case '4':
                $query->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('me_user_card')
                        ->whereColumn('me_user_card.user_id', 'me_user.id');
                });
                break;
        }
    }

    /**
     * @param array<int, int> $agentIds
     * @return array<int, array<string, float|int|string>>
     */
    private function aggregateTreeStats(array $agentIds): array
    {
        $result = [];
        foreach ($agentIds as $agentId) {
            $teamIds = $this->getDescendantIds($agentId);
            $result[$agentId] = $this->computeStatsForTeam($agentId, $teamIds);
        }

        return $result;
    }

    /**
     * @return list<int>
     */
    private function getDescendantIds(int $agentId): array
    {
        return UserModel::query()
            ->where('tree_id', 'like', "%/{$agentId}/%")
            ->where('tree_id', 'not like', "%/{$agentId}/")
            ->where('is_virtual', 0)
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param list<int> $teamIds
     * @return array<string, float|int|string>
     */
    private function computeStatsForTeam(int $agentId, array $teamIds): array
    {
        $stats = $this->emptyTreeStats();
        $stats['team_count'] = count($teamIds);

        if ($teamIds === []) {
            return $stats;
        }

        $success = RechargeOrderModel::STATUS_SUCCESS;
        $stats['recharge_sum_third'] = (string) RechargeOrderModel::query()
            ->whereIn('user_id', $teamIds)
            ->where('status', $success)
            ->where('order_source', RechargeOrderModel::FROM_FRONT)
            ->whereIn('type', [RechargeOrderModel::TYPE_THIRD, RechargeOrderModel::TYPE_OFFLINE])
            ->sum('money');

        $stats['recharge_sum_contract'] = (string) RechargeOrderModel::query()
            ->whereIn('user_id', $teamIds)
            ->where('status', $success)
            ->where('type', RechargeOrderModel::TYPE_CONTRACT)
            ->sum('money');

        $stats['recharge_sum_bank'] = (string) RechargeOrderModel::query()
            ->whereIn('user_id', $teamIds)
            ->where('status', $success)
            ->where('type', RechargeOrderModel::TYPE_OFFLINE)
            ->where('order_source', RechargeOrderModel::FROM_ADMIN)
            ->sum('money');

        $stats['withdrawal_sum'] = (string) WithdrawOrderModel::query()
            ->whereIn('user_id', $teamIds)
            ->where('status', WithdrawOrderModel::STATUS_SUCCESS)
            ->sum('original_amount');

        $stats['withdrawal_no_pay'] = (string) WithdrawOrderModel::query()
            ->whereIn('user_id', $teamIds)
            ->where('status', WithdrawOrderModel::STATUS_WAIT)
            ->sum('original_amount');

        $balanceAgg = UserModel::query()
            ->whereIn('id', $teamIds)
            ->selectRaw('
                COALESCE(SUM(balance),0) + COALESCE(SUM(brokerage_balance),0) as team_money,
                COALESCE(SUM(frozen_balance),0) as frozen_money
            ')
            ->first();

        $stats['team_money'] = (string) (($balanceAgg->team_money ?? 0) + ($balanceAgg->frozen_money ?? 0));
        $stats['sup_to_sub_money'] = $this->sumSupToSubMoney($agentId, $teamIds);

        $threeDaysAgo = time() - 3 * 86400;
        $stats['last_ten_days'] = (int) AdProductOrderModel::query()
            ->whereIn('user_id', $teamIds)
            ->where('created_at', '>', $threeDaysAgo)
            ->distinct()
            ->count('user_id');

        return $stats;
    }

    /**
     * @param array<string, float|int|string> $stats
     */
    private function formatAgentItem(UserModel $agent, array $stats): array
    {
        $online = (int) $agent->last_login_at > 0
            && (time() - (int) $agent->last_login_at) < 300;

        return [
            'id'                    => (int) $agent->id,
            'username'              => (string) $agent->username,
            'user_name'             => (string) $agent->username,
            'nickname'              => (string) $agent->nickname,
            'invite_code'           => (string) $agent->invite_code,
            'level_id'              => (int) $agent->level_id,
            'credit_score'          => (int) $agent->credit_score,
            'lang'                  => (string) ($agent->lang ?: '--'),
            'status'                => (int) $agent->status,
            'withdraw_status'       => (int) $agent->withdraw_status,
            'is_virtual'            => (int) $agent->is_virtual,
            'is_xuni'               => (int) $agent->is_virtual,
            'is_invite_code_enabled'=> (int) $agent->is_invite_code_enabled,
            'is_proxy'              => (int) $agent->is_proxy,
            'team_monthly_reset'    => (int) $agent->team_monthly_reset,
            'remark'                => (string) ($agent->remark ?? ''),
            'display_name'          => UserModel::displayLabel($agent),
            'online_status'         => $online,
            'ad_plan_name'          => AdPlanModel::getPlanName((int) $agent->ad_plan_id),
            'team_count'            => (int) ($stats['team_count'] ?? $agent->team_count),
            'recharge_sum_third'    => $this->formatMoney($stats['recharge_sum_third'] ?? 0),
            'recharge_sum_contract' => $this->formatMoney($stats['recharge_sum_contract'] ?? 0),
            'recharge_sum_bank'     => $this->formatMoney($stats['recharge_sum_bank'] ?? 0),
            'withdrawal_sum'        => $this->formatMoney($stats['withdrawal_sum'] ?? 0),
            'withdrawal_no_pay'     => $this->formatMoney($stats['withdrawal_no_pay'] ?? 0),
            'sup_to_sub_money'      => $this->formatMoney($stats['sup_to_sub_money'] ?? 0),
            'team_money'            => $this->formatMoney($stats['team_money'] ?? 0),
            'last_ten_days'         => (int) ($stats['last_ten_days'] ?? 0),
            'balance'               => (string) $agent->balance,
            'total_recharge'        => (string) $agent->total_recharge,
            'total_withdraw'        => (string) $agent->total_withdraw,
            'created_at'            => DateTimeFormat::datetime($agent->created_at),
            'registered_at_text'    => $agent->registered_at
                ? date('Y-m-d H:i:s', (int) $agent->registered_at)
                : DateTimeFormat::datetime($agent->created_at),
        ];
    }

    private function formatMoney(float|int|string $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    /**
     * 上级转下级金额：下级收到来自该代理的转账（order_no 存来源用户ID）
     */
    private function sumSupToSubMoney(int $agentId, array $teamIds): string
    {
        if ($teamIds === [] || $agentId <= 0) {
            return '0';
        }

        return (string) UserWaterModel::query()
            ->whereIn('user_id', $teamIds)
            ->where('detail_type', UserWaterDetailType::TRANSFER_IN)
            ->where('direction', 1)
            ->where('order_no', $agentId)
            ->sum('amount');
    }

    /**
     * @return array<string, float|int|string>
     */
    private function emptyTreeStats(): array
    {
        return [
            'team_count'            => 0,
            'recharge_sum_third'    => '0',
            'recharge_sum_contract' => '0',
            'recharge_sum_bank'     => '0',
            'withdrawal_sum'        => '0',
            'withdrawal_no_pay'     => '0',
            'sup_to_sub_money'      => '0',
            'team_money'            => '0',
            'last_ten_days'         => 0,
        ];
    }
}
