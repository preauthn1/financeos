<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\invest\InvestmentProductOrderModel;
use app\model\member\UserModel;
use app\support\ErrorCode;
use app\support\UserWaterDetailType;
use app\support\UserWaterType;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Builder;

class InvestmentProductOrderService
{
    public function __construct(private ?MemberWalletService $walletService = null)
    {
        $this->walletService = $walletService ?? new MemberWalletService();
    }

    /**
     * @return array{total:int,items:array<int,array>,kpis:array<string,mixed>,meta:array<string,mixed>,stats:array<string,mixed>}
     */
    public function list(int $page, int $limit, array $filters = []): array
    {
        $query = $this->buildFilteredQuery($filters);

        $total = (clone $query)->count();
        $items = (clone $query)
            ->with('user:id,username,nickname,mobile,leader1,is_virtual')
            ->orderByDesc('id')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        $agentIds = $items->map(fn (InvestmentProductOrderModel $o) => (int) ($o->user?->leader1 ?? 0))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $agentMap = $this->loadUsersByIds($agentIds);

        $formatted = $items->map(fn (InvestmentProductOrderModel $row) => $this->formatItem($row, false, $agentMap))->all();
        $kpis = $this->buildKpis($filters);

        return [
            'total' => $total,
            'items' => $formatted,
            'kpis' => $kpis,
            'meta' => $this->filterMeta(),
            // 兼容旧字段
            'stats' => [
                'total_money' => $kpis['today_buy_amount'],
                'total_profit' => $kpis['today_profit_amount'],
                'active_count' => $kpis['today_buy_count'],
            ],
        ];
    }

    public function show(int $id): array
    {
        $order = InvestmentProductOrderModel::with('user:id,username,nickname,mobile,leader1,is_virtual')->find($id);
        if (!$order) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '订单不存在');
        }
        $agentId = (int) ($order->user?->leader1 ?? 0);
        $agentMap = $this->loadUsersByIds($agentId > 0 ? [$agentId] : []);

        return $this->formatItem($order, true, $agentMap);
    }

    /**
     * @return array{items:array<int,array>}
     */
    public function agentStats(array $filters = []): array
    {
        $orders = $this->buildFilteredQuery($filters)
            ->with('user:id,username,nickname,mobile,leader1,is_virtual')
            ->get();

        $agentIds = $orders->map(fn (InvestmentProductOrderModel $o) => (int) ($o->user?->leader1 ?? 0))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $agentMap = $this->loadUsersByIds($agentIds);

        $grouped = [];
        foreach ($orders as $order) {
            $agentId = (int) ($order->user?->leader1 ?? 0);
            if ($agentId <= 0) {
                continue;
            }
            if (!isset($grouped[$agentId])) {
                $agent = $agentMap[$agentId] ?? null;
                $grouped[$agentId] = [
                    'agent_id' => $agentId,
                    'agent_name' => UserModel::displayLabel($agent, $agentId),
                    'buy_users' => [],
                    'order_count' => 0,
                    'buy_amount' => '0',
                    'profit_amount' => '0',
                    'bought_count' => 0,
                    'settled_count' => 0,
                ];
            }
            $g = &$grouped[$agentId];
            $g['order_count']++;
            $status = (int) $order->status;
            if (in_array($status, [InvestmentProductOrderModel::STATUS_BOUGHT, InvestmentProductOrderModel::STATUS_SETTLED], true)) {
                $g['buy_amount'] = bcadd($g['buy_amount'], (string) $order->money, 4);
                $g['profit_amount'] = bcadd($g['profit_amount'], (string) $order->profit_amount, 4);
                if ((int) ($order->user?->is_virtual ?? 0) !== 1) {
                    $g['buy_users'][(int) $order->user_id] = true;
                }
            }
            if ($status === InvestmentProductOrderModel::STATUS_BOUGHT) {
                $g['bought_count']++;
            }
            if ($status === InvestmentProductOrderModel::STATUS_SETTLED) {
                $g['settled_count']++;
            }
            unset($g);
        }

        $items = array_values(array_map(static function (array $row) {
            $row['buy_user_count'] = count($row['buy_users']);
            unset($row['buy_users']);
            $row['buy_amount'] = number_format((float) $row['buy_amount'], 2, '.', '');
            $row['profit_amount'] = number_format((float) $row['profit_amount'], 2, '.', '');

            return $row;
        }, $grouped));

        usort($items, static fn (array $a, array $b) => (float) $b['buy_amount'] <=> (float) $a['buy_amount']);

        return ['items' => $items];
    }

    /**
     * @return array{user_id:int,username:string,mobile:string,summary:array<string,mixed>,items:array<int,array>}
     */
    public function userRecords(int $userId, array $filters = []): array
    {
        if ($userId <= 0) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '用户ID无效');
        }

        $filters['user_id'] = $userId;
        unset($filters['agent_id'], $filters['username'], $filters['keyword']);

        $user = UserModel::query()->find($userId, ['id', 'username', 'nickname', 'mobile', 'leader1']);
        $orders = $this->buildFilteredQuery($filters)
            ->with('user:id,username,nickname,mobile,leader1,is_virtual')
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        $agentId = (int) ($user?->leader1 ?? 0);
        $agentMap = $this->loadUsersByIds($agentId > 0 ? [$agentId] : []);

        $summary = [
            'order_count' => $orders->count(),
            'invest_amount' => '0',
            'profit_amount' => '0',
        ];
        foreach ($orders as $order) {
            if (in_array((int) $order->status, [InvestmentProductOrderModel::STATUS_BOUGHT, InvestmentProductOrderModel::STATUS_SETTLED], true)) {
                $summary['invest_amount'] = bcadd($summary['invest_amount'], (string) $order->money, 4);
                $summary['profit_amount'] = bcadd($summary['profit_amount'], (string) $order->profit_amount, 4);
            }
        }
        $summary['invest_amount'] = number_format((float) $summary['invest_amount'], 2, '.', '');
        $summary['profit_amount'] = number_format((float) $summary['profit_amount'], 2, '.', '');

        return [
            'user_id' => $userId,
            'username' => (string) ($user?->username ?? ''),
            'nickname' => (string) ($user?->nickname ?? ''),
            'mobile' => (string) ($user?->mobile ?? ''),
            'summary' => $summary,
            'items' => $orders->map(fn (InvestmentProductOrderModel $row) => $this->formatItem($row, false, $agentMap))->all(),
        ];
    }

    /**
     * 订单操作：settle|end
     */
    public function action(int $id, string $action, string $remark = ''): array
    {
        $order = InvestmentProductOrderModel::query()->find($id);
        if (!$order) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '订单不存在');
        }

        return match ($action) {
            'settle', 'end' => $this->forceSettle($order, $remark),
            default => throw new BusinessException(ErrorCode::VALIDATION_FAILED, '不支持的操作类型'),
        };
    }

    private function forceSettle(InvestmentProductOrderModel $order, string $remark = ''): array
    {
        if ((int) $order->status !== InvestmentProductOrderModel::STATUS_BOUGHT) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '仅已购买订单可结算');
        }

        DB::transaction(function () use ($order, $remark) {
            $locked = InvestmentProductOrderModel::where('id', $order->id)
                ->where('status', InvestmentProductOrderModel::STATUS_BOUGHT)
                ->lockForUpdate()
                ->first();
            if (!$locked) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '订单状态已变更');
            }

            $now = time();
            $userId = (int) $locked->user_id;
            $principal = (string) $locked->money;
            $profit = (string) $locked->profit_amount;
            $note = $remark !== '' ? $remark : '后台手动结算';

            if (bccomp($principal, '0', 4) > 0) {
                $this->walletService->changeBalance(
                    $userId,
                    $principal,
                    UserWaterType::TYPE_USER,
                    MemberWalletService::DIRECTION_IN,
                    'AI托管本金',
                    UserWaterDetailType::AI_TRUST,
                    (int) $locked->id,
                    $note
                );
            }

            if (bccomp($profit, '0', 4) > 0 && (int) $locked->profit_settled_early !== 1) {
                $this->walletService->changeBalance(
                    $userId,
                    $profit,
                    UserWaterType::TYPE_USER,
                    MemberWalletService::DIRECTION_IN,
                    'AI托管收益',
                    UserWaterDetailType::AI_TRUST_PROFIT,
                    (int) $locked->id,
                    $note
                );
            }

            $locked->status = InvestmentProductOrderModel::STATUS_SETTLED;
            $locked->settled_at = $now;
            $locked->settlement_times = (int) $locked->settlement_times + 1;
            $locked->save();
        });

        return $this->show($id = (int) $order->id);
    }

    private function buildFilteredQuery(array $filters): Builder
    {
        $query = InvestmentProductOrderModel::query();

        if (!empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }
        if (!empty($filters['order_no'])) {
            $query->where('order_no', 'like', '%' . $filters['order_no'] . '%');
        }
        if (!empty($filters['username'])) {
            $kw = (string) $filters['username'];
            $query->whereHas('user', function ($uq) use ($kw) {
                $uq->where('username', 'like', "%{$kw}%")
                    ->orWhere('mobile', 'like', "%{$kw}%")
                    ->orWhere('nickname', 'like', "%{$kw}%");
            });
        }
        if (!empty($filters['keyword'])) {
            $kw = (string) $filters['keyword'];
            $query->where(function ($q) use ($kw) {
                $q->where('order_no', 'like', "%{$kw}%")
                    ->orWhere('investment_product_name', 'like', "%{$kw}%")
                    ->orWhere('user_id', $kw)
                    ->orWhereHas('user', function ($uq) use ($kw) {
                        $uq->where('username', 'like', "%{$kw}%")
                            ->orWhere('mobile', 'like', "%{$kw}%");
                    });
            });
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int) $filters['status']);
        }
        if (!empty($filters['agent_id'])) {
            $agentId = (int) $filters['agent_id'];
            $query->whereHas('user', fn ($uq) => $uq->where('leader1', $agentId));
        }
        if (isset($filters['is_virtual']) && $filters['is_virtual'] !== '') {
            $isVirtual = (int) $filters['is_virtual'];
            $query->whereHas('user', fn ($uq) => $uq->where('is_virtual', $isVirtual));
        }
        if (!empty($filters['start_time'])) {
            $query->where('created_at', '>=', strtotime((string) $filters['start_time']));
        } elseif (!empty($filters['start_date'])) {
            $query->where('created_at', '>=', strtotime((string) $filters['start_date']));
        }
        if (!empty($filters['end_time'])) {
            $query->where('created_at', '<=', strtotime((string) $filters['end_time']));
        } elseif (!empty($filters['end_date'])) {
            $query->where('created_at', '<=', strtotime($filters['end_date'] . ' 23:59:59'));
        }

        return $query;
    }

    /** @return array<string, mixed> */
    private function buildKpis(array $filters): array
    {
        $baseFilters = $filters;
        unset($baseFilters['status']);
        $todayStart = strtotime(date('Y-m-d 00:00:00'));
        $todayQuery = $this->buildFilteredQuery($baseFilters)->where('created_at', '>=', $todayStart);
        $buyStatuses = [InvestmentProductOrderModel::STATUS_BOUGHT, InvestmentProductOrderModel::STATUS_SETTLED];
        $todayBuy = (clone $todayQuery)->whereIn('status', $buyStatuses);

        $todayBuyUsers = (int) (clone $todayBuy)
            ->whereHas('user', fn ($uq) => $uq->where('is_virtual', 0))
            ->distinct()
            ->count('iv_product_order.user_id');

        return [
            'today_buy_amount' => number_format((float) (clone $todayBuy)->sum('money'), 2, '.', ''),
            'today_buy_count' => (int) (clone $todayBuy)->count(),
            'today_profit_amount' => number_format((float) (clone $todayBuy)->sum('profit_amount'), 2, '.', ''),
            'today_fee_amount' => '0.00',
            'today_match_balance' => number_format((float) (clone $todayBuy)->sum('money'), 2, '.', ''),
            'today_buy_users' => $todayBuyUsers,
        ];
    }

    /**
     * @param array<int, UserModel> $agentMap
     * @return array<string, mixed>
     */
    private function formatItem(InvestmentProductOrderModel $row, bool $detail = false, array $agentMap = []): array
    {
        $user = $row->user;
        $agentId = (int) ($user?->leader1 ?? 0);
        $agent = $agentMap[$agentId] ?? null;
        if ($agentId > 0 && $agent === null) {
            $agent = UserModel::query()->find($agentId, ['id', 'username', 'nickname', 'remark']);
        }

        $statusText = InvestmentProductOrderModel::$statusMap[$row->status] ?? '未知';

        $data = [
            'id' => (int) $row->id,
            'order_no' => (string) $row->order_no,
            'user_id' => (int) $row->user_id,
            'username' => (string) ($user?->username ?? ''),
            'nickname' => (string) ($user?->nickname ?? ''),
            'mobile' => (string) ($user?->mobile ?? ''),
            'is_virtual' => (int) ($user?->is_virtual ?? 0),
            'is_virtual_text' => ((int) ($user?->is_virtual ?? 0) === 1) ? '是' : '否',
            'agent_id' => $agentId,
            'agent_name' => UserModel::displayLabel($agent, $agentId),
            'investment_product_id' => (int) $row->investment_product_id,
            'investment_product_name' => (string) $row->investment_product_name,
            'order_source' => 'AI短周期托管',
            'money' => number_format((float) $row->money, 2, '.', ''),
            'profit' => number_format((float) $row->profit, 2, '.', ''),
            'profit_amount' => number_format((float) $row->profit_amount, 2, '.', ''),
            'cycle' => (float) $row->cycle,
            'cycle_text' => $this->formatCycleText($row),
            'started_at' => (int) $row->started_at,
            'ended_at' => (int) $row->ended_at,
            'settled_at' => (int) $row->settled_at,
            'created_at' => $row->created_at,
            'status' => (int) $row->status,
            'status_text' => $statusText,
            'can_settle' => (int) $row->status === InvestmentProductOrderModel::STATUS_BOUGHT,
        ];

        if ($detail) {
            $data['user'] = $user?->toArray();
            $data['settlement_times'] = (int) $row->settlement_times;
            $data['profit_settled_early'] = (int) $row->profit_settled_early;
        }

        return $data;
    }

    private function formatCycleText(InvestmentProductOrderModel $row): string
    {
        $started = (int) $row->started_at;
        $ended = (int) $row->ended_at;
        $cycle = (float) $row->cycle;
        $num = static function (float $value): string {
            return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') ?: '0';
        };

        if ($started > 0 && $ended > $started) {
            $seconds = $ended - $started;
            $hours = $seconds / 3600;
            $days = $seconds / 86400;

            // 接近整天 → 天；否则按小时（短周期托管）
            if ($days >= 1 && abs($days - round($days)) < 0.05) {
                return $num((float) round($days)) . ' 天';
            }
            if ($hours > 0 && $hours < 48) {
                return $num(round($hours, 2)) . ' 小时';
            }

            return $num(round($days, 2)) . ' 天';
        }

        // 无起止时间时：短期订单 cycle 字段存小时
        if ($cycle <= 0) {
            return '-';
        }
        if ((int) $row->profit_type === 2) {
            return $num($cycle) . ' 小时';
        }
        if ($cycle >= 1 || abs($cycle - round($cycle)) < 0.001) {
            return $num($cycle) . ' 天';
        }

        return $num(round($cycle * 24, 2)) . ' 小时';
    }

    /** @return array<string, mixed> */
    private function filterMeta(): array
    {
        $agents = UserModel::query()
            ->where('is_proxy', 1)
            ->orderByDesc('id')
            ->limit(200)
            ->get(['id', 'username', 'nickname', 'remark'])
            ->map(fn (UserModel $u) => [
                'id' => (int) $u->id,
                'name' => UserModel::displayLabel($u),
            ])
            ->all();

        return [
            'agents' => $agents,
            'status_map' => InvestmentProductOrderModel::$statusMap,
        ];
    }

    /**
     * @param list<int> $ids
     * @return array<int, UserModel>
     */
    private function loadUsersByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return UserModel::query()
            ->whereIn('id', $ids)
            ->get(['id', 'username', 'nickname', 'remark'])
            ->keyBy('id')
            ->all();
    }
}
