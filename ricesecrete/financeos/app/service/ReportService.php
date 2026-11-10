<?php

declare(strict_types=1);

namespace app\service;

use app\model\invest\InvestmentProductOrderModel;
use app\model\member\UserModel;
use app\model\member\UserWaterModel;
use app\model\pay\RechargeOrderModel;
use app\model\pay\WithdrawOrderModel;
use Illuminate\Database\Capsule\Manager as DB;

class ReportService
{
    public function daily(int $page, int $limit, array $filters = []): array
    {
        $startDate = $filters['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $filters['end_date'] ?? date('Y-m-d');

        $dates = $this->dateRange($startDate, $endDate);
        $total = count($dates);
        $pageDates = array_slice($dates, ($page - 1) * $limit, $limit);

        $items = [];
        foreach ($pageDates as $date) {
            $items[] = $this->buildDailyRow($date, $filters);
        }

        $summary = $this->buildSummary($startDate, $endDate, $filters);

        return ['total' => $total, 'items' => $items, 'summary' => $summary];
    }

    public function registerReport(int $page, int $limit, array $filters = []): array
    {
        $startDate = $filters['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $filters['end_date'] ?? date('Y-m-d');
        $dates = $this->dateRange($startDate, $endDate);
        $total = count($dates);
        $pageDates = array_slice($dates, ($page - 1) * $limit, $limit);

        $items = [];
        foreach ($pageDates as $date) {
            $items[] = $this->buildRegisterDailyRow($date, $filters);
        }

        return [
            'total'   => $total,
            'items'   => $items,
            'summary' => $this->buildRegisterSummary($startDate, $endDate, $filters),
        ];
    }

    public function registerReportUsers(int $page, int $limit, array $filters = []): array
    {
        $date = (string) ($filters['stat_date'] ?? '');
        if ($date === '') {
            return ['total' => 0, 'items' => []];
        }

        $start = $date . ' 00:00:00';
        $end = $date . ' 23:59:59';
        $query = UserModel::query()->whereBetween('created_at', [$start, $end]);

        if (isset($filters['is_virtual']) && $filters['is_virtual'] !== '') {
            $query->where('is_virtual', (int) $filters['is_virtual']);
        } else {
            $query->where('is_virtual', 0);
        }

        if (!empty($filters['agent_id'])) {
            $query->where('leader1', (int) $filters['agent_id']);
        }

        if (!empty($filters['keyword'])) {
            $kw = (string) $filters['keyword'];
            $query->where(function ($q) use ($kw) {
                if (ctype_digit($kw)) {
                    $q->where('id', (int) $kw);
                } else {
                    $q->where('username', 'like', "%{$kw}%")
                        ->orWhere('nickname', 'like', "%{$kw}%");
                }
            });
        }

        if (isset($filters['has_recharge']) && $filters['has_recharge'] !== '') {
            $userIds = (clone $query)->pluck('id');
            $rechargedIds = RechargeOrderModel::query()
                ->where('status', RechargeOrderModel::STATUS_SUCCESS)
                ->whereIn('user_id', $userIds->isEmpty() ? [0] : $userIds)
                ->whereBetween('created_at', [$start, $end])
                ->distinct()
                ->pluck('user_id');
            if ((int) $filters['has_recharge'] === 1) {
                $query->whereIn('id', $rechargedIds->isEmpty() ? [0] : $rechargedIds);
            } else {
                $query->whereNotIn('id', $rechargedIds->isEmpty() ? [0] : $rechargedIds);
            }
        }

        $total = (clone $query)->count();
        $items = (clone $query)->orderByDesc('id')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get(['id', 'username', 'nickname', 'is_virtual', 'leader1', 'invite_code', 'created_at'])
            ->map(function (UserModel $user) use ($start, $end) {
                $rechargeAmount = (string) RechargeOrderModel::query()
                    ->where('user_id', $user->id)
                    ->where('status', RechargeOrderModel::STATUS_SUCCESS)
                    ->whereBetween('created_at', [$start, $end])
                    ->sum('money');

                return [
                    'id' => (int) $user->id,
                    'username' => (string) $user->username,
                    'nickname' => (string) ($user->nickname ?? ''),
                    'invite_code' => (string) ($user->invite_code ?? ''),
                    'leader1' => (int) ($user->leader1 ?? 0),
                    'is_virtual' => (int) ($user->is_virtual ?? 0),
                    'created_at' => (string) $user->created_at,
                    'recharge_amount' => $rechargeAmount,
                    'has_recharge' => bccomp($rechargeAmount, '0', 2) > 0 ? 1 : 0,
                ];
            });

        return ['total' => $total, 'items' => $items->all()];
    }

    public function financeDaily(int $page, int $limit, array $filters = []): array
    {
        $startDate = $filters['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $filters['end_date'] ?? date('Y-m-d');
        $dates = $this->dateRange($startDate, $endDate);
        $total = count($dates);
        $pageDates = array_slice($dates, ($page - 1) * $limit, $limit);

        $items = [];
        foreach ($pageDates as $date) {
            $start = $date . ' 00:00:00';
            $end = $date . ' 23:59:59';

            $rechargeAmount = (string) RechargeOrderModel::where('status', RechargeOrderModel::STATUS_SUCCESS)
                ->whereBetween('created_at', [$start, $end])->sum('money');
            $withdrawAmount = (string) WithdrawOrderModel::where('status', WithdrawOrderModel::STATUS_SUCCESS)
                ->whereBetween('created_at', [$start, $end])->sum('original_amount');
            $investAmount = (string) InvestmentProductOrderModel::where('status', InvestmentProductOrderModel::STATUS_BOUGHT)
                ->whereBetween('created_at', [strtotime($start), strtotime($end)])->sum('money');
            $profitAmount = (string) InvestmentProductOrderModel::whereBetween('created_at', [strtotime($start), strtotime($end)])
                ->sum('profit_amount');

            $items[] = [
                'stat_date'       => $date,
                'recharge_amount' => $rechargeAmount,
                'withdraw_amount' => $withdrawAmount,
                'rw_diff'         => bcsub($rechargeAmount, $withdrawAmount, 2),
                'invest_amount'   => $investAmount,
                'profit_amount'   => $profitAmount,
                'water_income'    => (string) UserWaterModel::where('direction', 1)
                    ->whereBetween('created_at', [strtotime($start), strtotime($end)])->sum('amount'),
                'water_expense'   => (string) UserWaterModel::where('direction', 2)
                    ->whereBetween('created_at', [strtotime($start), strtotime($end)])->sum('amount'),
            ];
        }

        return ['total' => $total, 'items' => $items, 'summary' => $this->buildFinanceSummary($startDate, $endDate)];
    }

    public function comprehensive(array $filters = []): array
    {
        $startDate = $filters['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
        $endDate = $filters['end_date'] ?? date('Y-m-d');
        $start = $startDate . ' 00:00:00';
        $end = $endDate . ' 23:59:59';
        $startTs = strtotime($start);
        $endTs = strtotime($end);

        $rechargeQuery = RechargeOrderModel::where('status', RechargeOrderModel::STATUS_SUCCESS)
            ->whereBetween('created_at', [$start, $end]);
        $withdrawQuery = WithdrawOrderModel::where('status', WithdrawOrderModel::STATUS_SUCCESS)
            ->whereBetween('created_at', [$start, $end]);
        $registerQuery = UserModel::whereBetween('created_at', [$start, $end]);

        $rechargeAmount = (string) (clone $rechargeQuery)->sum('money');
        $withdrawAmount = (string) (clone $withdrawQuery)->sum('original_amount');

        return [
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'member' => [
                'register_count' => (clone $registerQuery)->count(),
                'virtual_count'  => (clone $registerQuery)->where('is_virtual', 1)->count(),
                'real_count'     => (clone $registerQuery)->where('is_virtual', 0)->count(),
                'total_users'    => UserModel::count(),
            ],
            'finance' => [
                'recharge_amount'    => $rechargeAmount,
                'withdraw_amount'    => $withdrawAmount,
                'rw_diff'            => bcsub($rechargeAmount, $withdrawAmount, 2),
                'recharge_user'      => (clone $rechargeQuery)->distinct('user_id')->count('user_id'),
                'withdraw_user'      => (clone $withdrawQuery)->distinct('user_id')->count('user_id'),
                'first_recharge'     => RechargeOrderModel::where('status', RechargeOrderModel::STATUS_SUCCESS)
                    ->where('is_first', 1)->whereBetween('created_at', [$start, $end])->count(),
            ],
            'invest' => [
                'order_count'  => InvestmentProductOrderModel::whereBetween('created_at', [$startTs, $endTs])->count(),
                'active_count' => InvestmentProductOrderModel::where('status', InvestmentProductOrderModel::STATUS_BOUGHT)->count(),
                'total_money'  => (string) InvestmentProductOrderModel::whereBetween('created_at', [$startTs, $endTs])->sum('money'),
                'total_profit' => (string) InvestmentProductOrderModel::whereBetween('created_at', [$startTs, $endTs])->sum('profit_amount'),
            ],
            'water' => [
                'total_income'  => (string) UserWaterModel::where('direction', 1)
                    ->whereBetween('created_at', [$startTs, $endTs])->sum('amount'),
                'total_expense' => (string) UserWaterModel::where('direction', 2)
                    ->whereBetween('created_at', [$startTs, $endTs])->sum('amount'),
            ],
        ];
    }

    private function buildDailyRow(string $date, array $filters): array
    {
        $start = $date . ' 00:00:00';
        $end = $date . ' 23:59:59';

        $rechargeQuery = RechargeOrderModel::where('status', RechargeOrderModel::STATUS_SUCCESS)
            ->whereBetween('created_at', [$start, $end]);
        $withdrawQuery = WithdrawOrderModel::where('status', WithdrawOrderModel::STATUS_SUCCESS)
            ->whereBetween('created_at', [$start, $end]);
        $registerQuery = UserModel::whereBetween('created_at', [$start, $end]);

        $this->applyAgentFilter($rechargeQuery, $withdrawQuery, $registerQuery, $filters);

        $rechargeAmount = (string) (clone $rechargeQuery)->sum('money');
        $withdrawAmount = (string) (clone $withdrawQuery)->sum('original_amount');

        return [
            'stat_date'          => $date,
            'register_count'     => (clone $registerQuery)->count(),
            'recharge_amount'    => $rechargeAmount,
            'withdraw_amount'    => $withdrawAmount,
            'recharge_user'      => (clone $rechargeQuery)->distinct('user_id')->count('user_id'),
            'withdraw_user'      => (clone $withdrawQuery)->distinct('user_id')->count('user_id'),
            'first_recharge_user'=> RechargeOrderModel::where('status', RechargeOrderModel::STATUS_SUCCESS)
                ->where('is_first', 1)
                ->whereBetween('created_at', [$start, $end])
                ->count(),
            'rw_diff'            => bcsub($rechargeAmount, $withdrawAmount, 2),
        ];
    }

    private function buildSummary(string $startDate, string $endDate, array $filters): array
    {
        $start = $startDate . ' 00:00:00';
        $end = $endDate . ' 23:59:59';

        $rechargeQuery = RechargeOrderModel::where('status', RechargeOrderModel::STATUS_SUCCESS)
            ->whereBetween('created_at', [$start, $end]);
        $withdrawQuery = WithdrawOrderModel::where('status', WithdrawOrderModel::STATUS_SUCCESS)
            ->whereBetween('created_at', [$start, $end]);

        $this->applyAgentFilter($rechargeQuery, $withdrawQuery, null, $filters);

        $rechargeAmount = (string) (clone $rechargeQuery)->sum('money');
        $withdrawAmount = (string) (clone $withdrawQuery)->sum('original_amount');

        return [
            'recharge_amount' => $rechargeAmount,
            'withdraw_amount' => $withdrawAmount,
            'rw_diff'         => bcsub($rechargeAmount, $withdrawAmount, 2),
            'register_count'  => UserModel::where('is_virtual', 0)
                ->whereBetween('created_at', [$start, $end])->count(),
            'recharge_user'   => (clone $rechargeQuery)->distinct('user_id')->count('user_id'),
            'withdraw_user'   => (clone $withdrawQuery)->distinct('user_id')->count('user_id'),
            'first_recharge_user' => RechargeOrderModel::where('status', RechargeOrderModel::STATUS_SUCCESS)
                ->where('is_first', 1)
                ->whereBetween('created_at', [$start, $end])
                ->count(),
        ];
    }

    private function buildRegisterDailyRow(string $date, array $filters): array
    {
        $start = $date . ' 00:00:00';
        $end = $date . ' 23:59:59';

        $registerQuery = UserModel::query()
            ->where('is_virtual', 0)
            ->whereBetween('created_at', [$start, $end]);

        if (!empty($filters['agent_id'])) {
            $registerQuery->where('leader1', (int) $filters['agent_id']);
        }

        $userIds = (clone $registerQuery)->pluck('id');
        $registerCount = $userIds->count();

        if ($registerCount === 0) {
            return [
                'stat_date' => $date,
                'register_count' => 0,
                'recharge_user' => 0,
                'no_recharge_user' => 0,
                'first_recharge_user' => 0,
                'repeat_recharge_user' => 0,
                'register_recharge_amount' => '0.00',
            ];
        }

        $rechargeUser = RechargeOrderModel::query()
            ->where('status', RechargeOrderModel::STATUS_SUCCESS)
            ->whereIn('user_id', $userIds)
            ->whereBetween('created_at', [$start, $end])
            ->distinct('user_id')
            ->count('user_id');

        $firstRechargeUser = RechargeOrderModel::query()
            ->where('status', RechargeOrderModel::STATUS_SUCCESS)
            ->where('is_first', 1)
            ->whereIn('user_id', $userIds)
            ->whereBetween('created_at', [$start, $end])
            ->distinct('user_id')
            ->count('user_id');

        $registerRechargeAmount = (string) RechargeOrderModel::query()
            ->where('status', RechargeOrderModel::STATUS_SUCCESS)
            ->whereIn('user_id', $userIds)
            ->whereBetween('created_at', [$start, $end])
            ->sum('money');

        return [
            'stat_date' => $date,
            'register_count' => $registerCount,
            'recharge_user' => $rechargeUser,
            'no_recharge_user' => max(0, $registerCount - $rechargeUser),
            'first_recharge_user' => $firstRechargeUser,
            'repeat_recharge_user' => max(0, $rechargeUser - $firstRechargeUser),
            'register_recharge_amount' => $registerRechargeAmount,
        ];
    }

    private function buildRegisterSummary(string $startDate, string $endDate, array $filters): array
    {
        $start = $startDate . ' 00:00:00';
        $end = $endDate . ' 23:59:59';

        $registerQuery = UserModel::query()
            ->where('is_virtual', 0)
            ->whereBetween('created_at', [$start, $end]);

        if (!empty($filters['agent_id'])) {
            $registerQuery->where('leader1', (int) $filters['agent_id']);
        }

        $userIds = (clone $registerQuery)->pluck('id');
        $registerCount = $userIds->count();
        $rechargeUser = $registerCount === 0 ? 0 : RechargeOrderModel::query()
            ->where('status', RechargeOrderModel::STATUS_SUCCESS)
            ->whereIn('user_id', $userIds)
            ->whereBetween('created_at', [$start, $end])
            ->distinct('user_id')
            ->count('user_id');

        return [
            'register_count' => $registerCount,
            'recharge_user' => $rechargeUser,
            'no_recharge_user' => max(0, $registerCount - $rechargeUser),
            'current_date' => $endDate,
        ];
    }

    private function buildFinanceSummary(string $startDate, string $endDate): array
    {
        $summary = $this->buildSummary($startDate, $endDate, []);
        $start = $startDate . ' 00:00:00';
        $end = $endDate . ' 23:59:59';
        $startTs = strtotime($start);
        $endTs = strtotime($end);

        return array_merge($summary, [
            'invest_amount' => (string) InvestmentProductOrderModel::where('status', InvestmentProductOrderModel::STATUS_BOUGHT)
                ->whereBetween('created_at', [$startTs, $endTs])->sum('money'),
            'profit_amount' => (string) InvestmentProductOrderModel::whereBetween('created_at', [$startTs, $endTs])
                ->sum('profit_amount'),
            'water_income' => (string) UserWaterModel::where('direction', 1)
                ->whereBetween('created_at', [$startTs, $endTs])->sum('amount'),
            'water_expense' => (string) UserWaterModel::where('direction', 2)
                ->whereBetween('created_at', [$startTs, $endTs])->sum('amount'),
        ]);
    }

    private function applyAgentFilter($rechargeQuery, $withdrawQuery, $registerQuery, array $filters): void
    {
        if (empty($filters['agent_id'])) {
            return;
        }
        $agentId = (int) $filters['agent_id'];
        $memberIds = UserModel::where('leader1', $agentId)->pluck('id')->all();
        if ($memberIds === []) {
            $rechargeQuery->whereRaw('1=0');
            $withdrawQuery->whereRaw('1=0');
            $registerQuery?->whereRaw('1=0');
            return;
        }
        $rechargeQuery->whereIn('user_id', $memberIds);
        $withdrawQuery->whereIn('user_id', $memberIds);
        $registerQuery?->whereIn('id', $memberIds);
    }

    /** @return string[] */
    private function dateRange(string $start, string $end): array
    {
        $dates = [];
        $current = strtotime($start);
        $endTs = strtotime($end);
        while ($current <= $endTs) {
            $dates[] = date('Y-m-d', $current);
            $current = strtotime('+1 day', $current);
        }
        return array_reverse($dates);
    }
}
