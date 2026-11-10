<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\ad\AdConfigModel;
use app\model\ad\AdProductOrderModel;
use app\model\member\UserModel;
use app\support\ErrorCode;
use app\support\UserWaterDetailType;
use app\support\UserWaterType;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AdProductOrderService
{
    public function __construct(private ?MemberWalletService $walletService = null)
    {
        $this->walletService = $walletService ?? new MemberWalletService();
    }

    /**
     * 按用户+批次聚合的购买记录列表
     *
     * @return array{total:int,items:array<int,array>,kpis:array<string,mixed>,meta:array<string,mixed>}
     */
    public function list(int $page, int $limit, array $filters = []): array
    {
        $query = $this->buildFilteredQuery($filters);
        $kpis = $this->buildKpis(clone $query, $filters);

        $groupSub = (clone $query)
            ->select([
                'user_id',
                'session_no',
            ])
            ->selectRaw('MAX(id) as max_id')
            ->groupBy('user_id', 'session_no');

        $groupBase = DB::query()->fromSub($groupSub->toBase(), 'g');
        $total = (int) (clone $groupBase)->count();
        $groupRows = (clone $groupBase)
            ->orderByDesc('max_id')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get(['user_id', 'session_no', 'max_id']);

        $items = [];
        $agentIds = [];
        foreach ($groupRows as $group) {
            $sessionOrders = AdProductOrderModel::query()
                ->with('user:id,username,nickname,mobile,leader1,is_virtual')
                ->where('user_id', (int) $group->user_id)
                ->where('session_no', (string) $group->session_no)
                ->orderBy('sort')
                ->orderBy('id')
                ->get();
            if ($sessionOrders->isEmpty()) {
                continue;
            }
            $agentIds[] = (int) ($sessionOrders->first()->user?->leader1 ?? 0);
            $items[] = $sessionOrders;
        }

        $agentMap = $this->loadUsersByIds(array_values(array_unique(array_filter($agentIds))));
        $formatted = [];
        foreach ($items as $sessionOrders) {
            $formatted[] = $this->formatGroup($sessionOrders, $agentMap);
        }

        return [
            'total' => $total,
            'items' => $formatted,
            'kpis' => $kpis,
            'meta' => $this->filterMeta(),
        ];
    }

    /**
     * 批次详情（同批全部订单）
     *
     * @return array<string, mixed>
     */
    public function sessionDetail(int $userId, string $sessionNo): array
    {
        $orders = AdProductOrderModel::query()
            ->with('user:id,username,nickname,mobile,leader1,is_virtual')
            ->where('user_id', $userId)
            ->where('session_no', $sessionNo)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        if ($orders->isEmpty()) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '批次不存在');
        }

        $group = $this->formatGroup($orders);
        $group['orders'] = $orders->map(fn (AdProductOrderModel $row) => $this->formatItem($row, true))->all();

        return $group;
    }

    public function show(int $id): array
    {
        $order = AdProductOrderModel::with('user')->find($id);
        if (!$order) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '订单不存在');
        }

        return $this->formatItem($order, true);
    }

    /**
     * 指定用户的广告位购买明细（订单级）
     *
     * @return array{user_id:int,username:string,mobile:string,summary:array<string,mixed>,items:array<int,array>}
     */
    public function userRecords(int $userId, array $filters = []): array
    {
        if ($userId <= 0) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '用户ID无效');
        }

        $filters['user_id'] = $userId;
        unset($filters['agent_id'], $filters['keyword']);

        $user = UserModel::query()->find($userId, ['id', 'username', 'nickname', 'mobile', 'leader1']);
        $orders = $this->buildFilteredQuery($filters)
            ->with('user:id,username,nickname,mobile,leader1,is_virtual')
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        $summary = [
            'order_count' => $orders->count(),
            'invest_amount' => '0',
            'profit_amount' => '0',
            'fee_amount' => '0',
        ];
        foreach ($orders as $order) {
            if (in_array((int) $order->status, [AdProductOrderModel::STATUS_BOUGHT, AdProductOrderModel::STATUS_SETTLED], true)) {
                $summary['invest_amount'] = bcadd($summary['invest_amount'], (string) $order->invest_amount, 4);
                $summary['profit_amount'] = bcadd($summary['profit_amount'], (string) $order->profit_amount, 4);
                $summary['fee_amount'] = bcadd($summary['fee_amount'], (string) $order->fee, 4);
            }
        }
        $summary['invest_amount'] = number_format((float) $summary['invest_amount'], 2, '.', '');
        $summary['profit_amount'] = number_format((float) $summary['profit_amount'], 2, '.', '');
        $summary['fee_amount'] = number_format((float) $summary['fee_amount'], 2, '.', '');

        return [
            'user_id' => $userId,
            'username' => (string) ($user?->username ?? ''),
            'nickname' => (string) ($user?->nickname ?? ''),
            'mobile' => (string) ($user?->mobile ?? ''),
            'summary' => $summary,
            'items' => $orders->map(fn (AdProductOrderModel $row) => $this->formatItem($row))->all(),
        ];
    }

    /**
     * 指定代理线下用户的广告位购买汇总
     *
     * @return array{agent_id:int,agent_name:string,summary:array<string,mixed>,items:array<int,array>}
     */
    public function agentRecords(int $agentId, array $filters = []): array
    {
        if ($agentId <= 0) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '代理ID无效');
        }

        $filters['agent_id'] = $agentId;
        unset($filters['user_id']);

        $agent = UserModel::query()->find($agentId, ['id', 'username', 'nickname', 'remark']);
        $orders = $this->buildFilteredQuery($filters)
            ->with('user:id,username,nickname,mobile,leader1,is_virtual')
            ->orderByDesc('id')
            ->limit(2000)
            ->get();

        $summary = [
            'buy_user_count' => 0,
            'order_count' => $orders->count(),
            'invest_amount' => '0',
            'profit_amount' => '0',
            'fee_amount' => '0',
        ];
        $buyUsers = [];
        foreach ($orders as $order) {
            if (in_array((int) $order->status, [AdProductOrderModel::STATUS_BOUGHT, AdProductOrderModel::STATUS_SETTLED], true)) {
                $summary['invest_amount'] = bcadd($summary['invest_amount'], (string) $order->invest_amount, 4);
                $summary['profit_amount'] = bcadd($summary['profit_amount'], (string) $order->profit_amount, 4);
                $summary['fee_amount'] = bcadd($summary['fee_amount'], (string) $order->fee, 4);
                if ((int) ($order->user?->is_virtual ?? 0) !== 1) {
                    $buyUsers[(int) $order->user_id] = true;
                }
            }
        }

        $summary['buy_user_count'] = count($buyUsers);
        $summary['invest_amount'] = number_format((float) $summary['invest_amount'], 2, '.', '');
        $summary['profit_amount'] = number_format((float) $summary['profit_amount'], 2, '.', '');
        $summary['fee_amount'] = number_format((float) $summary['fee_amount'], 2, '.', '');

        return [
            'agent_id' => $agentId,
            'agent_name' => UserModel::displayLabel($agent, $agentId),
            'summary' => $summary,
            'items' => $orders->map(fn (AdProductOrderModel $row) => $this->formatItem($row))->all(),
            'meta' => $this->filterMeta(),
        ];
    }

    /**
     * 代理汇总
     *
     * @return array{items:array<int,array>}
     */
    public function agentStats(array $filters = []): array
    {
        $query = $this->buildFilteredQuery($filters);
        $orders = $query->with('user:id,username,nickname,mobile,leader1,is_virtual')->get();

        $agentIds = $orders->map(fn (AdProductOrderModel $o) => (int) ($o->user?->leader1 ?? 0))
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
                    'fee_amount' => '0',
                ];
            }
            $g = &$grouped[$agentId];
            $g['order_count']++;
            if (in_array((int) $order->status, [AdProductOrderModel::STATUS_BOUGHT, AdProductOrderModel::STATUS_SETTLED], true)) {
                $g['buy_amount'] = bcadd($g['buy_amount'], (string) $order->invest_amount, 4);
                $g['profit_amount'] = bcadd($g['profit_amount'], (string) $order->profit_amount, 4);
                $g['fee_amount'] = bcadd($g['fee_amount'], (string) $order->fee, 4);
                if ((int) ($order->user?->is_virtual ?? 0) !== 1) {
                    $g['buy_users'][(int) $order->user_id] = true;
                }
            }
            unset($g);
        }

        $items = array_values(array_map(static function (array $row) {
            $row['buy_user_count'] = count($row['buy_users']);
            unset($row['buy_users']);
            $row['buy_amount'] = number_format((float) $row['buy_amount'], 2, '.', '');
            $row['profit_amount'] = number_format((float) $row['profit_amount'], 2, '.', '');
            $row['fee_amount'] = number_format((float) $row['fee_amount'], 2, '.', '');

            return $row;
        }, $grouped));

        usort($items, static fn ($a, $b) => (float) $b['buy_amount'] <=> (float) $a['buy_amount']);

        return ['items' => $items];
    }

    /**
     * 强制结束批次内已购买订单（立即结算本息）
     *
     * @return array{settled:int}
     */
    public function endSession(int $userId, string $sessionNo): array
    {
        $orders = AdProductOrderModel::query()
            ->where('user_id', $userId)
            ->where('session_no', $sessionNo)
            ->where('status', AdProductOrderModel::STATUS_BOUGHT)
            ->get();

        if ($orders->isEmpty()) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '没有可结束的已购买订单');
        }

        $settled = 0;
        foreach ($orders as $order) {
            $this->forceSettleOrder($order);
            $settled++;
        }

        return ['settled' => $settled];
    }

    private function forceSettleOrder(AdProductOrderModel $order): void
    {
        DB::transaction(function () use ($order) {
            $locked = AdProductOrderModel::where('id', $order->id)
                ->where('status', AdProductOrderModel::STATUS_BOUGHT)
                ->lockForUpdate()
                ->first();
            if (!$locked) {
                return;
            }

            $now = time();
            $userId = (int) $locked->user_id;
            $principal = (string) $locked->price;
            $profit = (string) $locked->profit_amount;

            if (bccomp($principal, '0', 4) > 0) {
                $this->walletService->changeBalance(
                    $userId,
                    $principal,
                    UserWaterType::TYPE_BROKERAGE_POOL,
                    MemberWalletService::DIRECTION_IN,
                    '广告库存本金',
                    UserWaterDetailType::STOCK_BUY,
                    (int) $locked->id,
                    '后台结束订单还本'
                );
            }
            if (bccomp($profit, '0', 4) > 0) {
                $this->walletService->changeBalance(
                    $userId,
                    $profit,
                    UserWaterType::TYPE_BROKERAGE_POOL,
                    MemberWalletService::DIRECTION_IN,
                    '广告库存收益',
                    UserWaterDetailType::SETTLE_PROFIT,
                    (int) $locked->id,
                    '后台结束订单收益'
                );
            }

            $locked->status = AdProductOrderModel::STATUS_SETTLED;
            $locked->settled_at = $now;
            $locked->ended_at = (int) $locked->ended_at > 0 ? (int) $locked->ended_at : $now;
            $locked->settlement_times = (int) $locked->settlement_times + 1;
            if ((int) $locked->profit_type === 0) {
                $locked->profit_type = 2;
            }
            $locked->save();
        });
    }

    private function buildFilteredQuery(array $filters): Builder
    {
        $query = AdProductOrderModel::query();

        // 默认不看纯待匹配草稿
        if (!isset($filters['include_pending']) || (int) $filters['include_pending'] !== 1) {
            if (!isset($filters['status']) || $filters['status'] === '') {
                $query->where('status', '<>', AdProductOrderModel::STATUS_PENDING);
            }
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }
        if (!empty($filters['order_no'])) {
            $query->where('order_no', 'like', '%' . $filters['order_no'] . '%');
        }
        if (!empty($filters['keyword'])) {
            $kw = (string) $filters['keyword'];
            $query->where(function ($q) use ($kw) {
                $q->where('order_no', 'like', "%{$kw}%")
                    ->orWhere('product_name', 'like', "%{$kw}%")
                    ->orWhere('session_no', 'like', "%{$kw}%")
                    ->orWhere('user_id', $kw)
                    ->orWhereHas('user', function ($uq) use ($kw) {
                        $uq->where('username', 'like', "%{$kw}%")
                            ->orWhere('mobile', 'like', "%{$kw}%")
                            ->orWhere('nickname', 'like', "%{$kw}%");
                    });
            });
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int) $filters['status']);
        }
        if (isset($filters['level_id']) && $filters['level_id'] !== '') {
            $query->where('level_id', (int) $filters['level_id']);
        }
        if (!empty($filters['session_no'])) {
            $query->where('session_no', $filters['session_no']);
        }
        if (!empty($filters['match_type'])) {
            $query->where('match_type', (int) $filters['match_type']);
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

    private function buildKpis(Builder $query, array $filters): array
    {
        $todayStart = strtotime(date('Y-m-d 00:00:00'));
        $todayQuery = (clone $query)->where('created_at', '>=', $todayStart);

        $buyStatuses = [AdProductOrderModel::STATUS_BOUGHT, AdProductOrderModel::STATUS_SETTLED];
        $todayBuy = (clone $todayQuery)->whereIn('status', $buyStatuses);

        $buyUserCount = (int) (clone $todayBuy)
            ->whereIn('user_id', function ($sub) {
                $sub->select('id')->from('me_user')->where('is_virtual', 0);
            })
            ->distinct()
            ->count('ad_product_order.user_id');

        return [
            'today_buy_amount' => number_format((float) (clone $todayBuy)->sum('invest_amount'), 2, '.', ''),
            'today_buy_count' => (int) (clone $todayBuy)->count(),
            'today_profit_amount' => number_format((float) (clone $todayBuy)->sum('profit_amount'), 2, '.', ''),
            'today_fee_amount' => number_format((float) (clone $todayBuy)->sum('fee'), 2, '.', ''),
            'today_match_balance' => number_format((float) (clone $todayBuy)->sum('balance'), 2, '.', ''),
            'today_buy_users' => $buyUserCount,
            // 兼容旧字段
            'total_count' => (int) (clone $query)->count(),
            'pending_count' => (int) (clone $query)->where('status', AdProductOrderModel::STATUS_PENDING)->count(),
            'bought_count' => (int) (clone $query)->where('status', AdProductOrderModel::STATUS_BOUGHT)->count(),
            'settled_count' => (int) (clone $query)->where('status', AdProductOrderModel::STATUS_SETTLED)->count(),
            'total_money' => number_format((float) (clone $query)->whereIn('status', $buyStatuses)->sum('invest_amount'), 2, '.', ''),
        ];
    }

    /**
     * @param Collection<int, AdProductOrderModel> $orders
     * @param array<int, UserModel> $agentMap
     * @return array<string, mixed>
     */
    private function formatGroup(Collection $orders, array $agentMap = []): array
    {
        /** @var AdProductOrderModel $first */
        $first = $orders->first();
        $user = $first->user;
        $agentId = (int) ($user?->leader1 ?? 0);
        $agent = $agentMap[$agentId] ?? ($agentId > 0 ? UserModel::query()->find($agentId, ['id', 'username', 'nickname', 'remark']) : null);

        $bought = $orders->filter(
            fn (AdProductOrderModel $o) => in_array((int) $o->status, [
                AdProductOrderModel::STATUS_BOUGHT,
                AdProductOrderModel::STATUS_SETTLED,
            ], true)
        );

        $taskTotal = max($orders->count(), 1);
        $taskDone = $bought->count();
        $investAmount = '0';
        $profitAmount = '0';
        $feeAmount = '0';
        $matchBalance = '0';
        $profitRateSum = 0.0;
        $profitRateCount = 0;
        $chips = [];
        $cycle = (float) $first->cycle;
        $periodType = (string) $first->invest_period_type;
        $boughtAt = 0;
        $endedAt = 0;
        $status = (int) $first->status;
        $matchType = (int) $first->match_type;

        foreach ($orders as $order) {
            if (in_array((int) $order->status, [AdProductOrderModel::STATUS_BOUGHT, AdProductOrderModel::STATUS_SETTLED], true)) {
                $investAmount = bcadd($investAmount, (string) $order->invest_amount, 4);
                $profitAmount = bcadd($profitAmount, (string) $order->profit_amount, 4);
                $feeAmount = bcadd($feeAmount, (string) $order->fee, 4);
                $matchBalance = (string) $order->balance;
                $profitRateSum += (float) $order->profit;
                $profitRateCount++;
                $chips[] = [
                    'sort' => (int) $order->sort,
                    'invest_amount' => number_format((float) $order->invest_amount, 2, '.', ''),
                    'profit_amount' => number_format((float) $order->profit_amount, 2, '.', ''),
                    'profit' => (string) $order->profit,
                    'order_no' => (string) $order->order_no,
                    'status' => (int) $order->status,
                    'status_text' => AdProductOrderModel::$statusMap[$order->status] ?? '',
                ];
                $boughtAt = max($boughtAt, (int) $order->bought_at);
                $endedAt = max($endedAt, (int) $order->ended_at);
                $cycle = (float) $order->cycle;
                $periodType = (string) $order->invest_period_type;
                $matchType = (int) $order->match_type;
            }
            // 组状态：优先展示已购买/已结算，否则取最新状态
            if ((int) $order->status === AdProductOrderModel::STATUS_SETTLED) {
                $status = AdProductOrderModel::STATUS_SETTLED;
            } elseif ((int) $order->status === AdProductOrderModel::STATUS_BOUGHT && $status !== AdProductOrderModel::STATUS_SETTLED) {
                $status = AdProductOrderModel::STATUS_BOUGHT;
            } elseif ($status === (int) $first->status) {
                $status = (int) $order->status;
            }
        }

        $avgProfit = $profitRateCount > 0 ? round($profitRateSum / $profitRateCount, 2) : 0;
        $mergedMatch = bcmul($matchBalance, (string) max($taskDone, 1), 4);
        $progress = $taskTotal > 0 ? (int) round($taskDone / $taskTotal * 100) : 0;

        return [
            'group_key' => (int) $first->user_id . '|' . (string) $first->session_no,
            'user_id' => (int) $first->user_id,
            'username' => (string) ($user?->username ?? ''),
            'nickname' => (string) ($user?->nickname ?? ''),
            'mobile' => (string) ($user?->mobile ?? ''),
            'is_virtual' => (int) ($user?->is_virtual ?? 0),
            'agent_id' => $agentId,
            'agent_name' => UserModel::displayLabel($agent, $agentId),
            'level_id' => (int) $first->level_id,
            'product_name' => (string) $first->product_name,
            'session_no' => (string) $first->session_no,
            'first_order_no' => (string) $first->order_no,
            'first_order_id' => (int) $first->id,
            'order_count' => $orders->count(),
            'task_total' => $taskTotal,
            'task_done' => $taskDone,
            'stock_range_min' => number_format((float) $first->stock_range_min, 2, '.', ''),
            'stock_range_max' => number_format((float) $first->stock_range_max, 2, '.', ''),
            'match_balance' => number_format((float) $matchBalance, 2, '.', ''),
            'merged_match_amount' => number_format((float) $mergedMatch, 2, '.', ''),
            'invest_amount' => number_format((float) $investAmount, 2, '.', ''),
            'profit_amount' => number_format((float) $profitAmount, 2, '.', ''),
            'fee_amount' => number_format((float) $feeAmount, 2, '.', ''),
            'profit_rate' => number_format($avgProfit, 2, '.', ''),
            'progress' => $progress,
            'cycle' => $cycle,
            'invest_period_type' => $periodType,
            'cycle_text' => $this->formatCycle($cycle, $periodType),
            'bought_at' => $boughtAt,
            'ended_at' => $endedAt,
            'status' => $status,
            'status_text' => AdProductOrderModel::$statusMap[$status] ?? '未知',
            'match_type' => $matchType,
            'match_type_text' => $matchType === 2 ? '超额匹配' : '正常匹配',
            'chips' => $chips,
            'can_end' => $bought->contains(fn (AdProductOrderModel $o) => (int) $o->status === AdProductOrderModel::STATUS_BOUGHT),
            // 老项目列表编辑按钮仅待匹配；已购买可在明细里改周期
            'can_edit' => $orders->contains(fn (AdProductOrderModel $o) => $this->canEditOrder((int) $o->status)),
        ];
    }

    /**
     * 按老项目 FinanceProductOrder::edit：
     * - 投资金额/收益率：仅待匹配
     * - 周期：待匹配或已购买（已购买且已开始则重算 ended_at）
     * - 广告位比例 invest_rate：不可改
     *
     * @param array{invest_amount?:mixed,profit?:mixed,cycle?:mixed,invest_period_type?:mixed} $data
     * @return array<string, mixed>
     */
    public function update(int $id, array $data): array
    {
        $order = AdProductOrderModel::query()->find($id);
        if (!$order) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '订单不存在');
        }

        $status = (int) $order->status;
        if (!$this->canEditOrder($status)) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '仅待匹配、已购买状态可编辑');
        }

        $investAmount = array_key_exists('invest_amount', $data)
            ? (string) $data['invest_amount']
            : (string) $order->invest_amount;
        $profit = array_key_exists('profit', $data)
            ? (string) $data['profit']
            : (string) $order->profit;
        $cycle = array_key_exists('cycle', $data)
            ? (float) $data['cycle']
            : (float) $order->cycle;
        $periodType = array_key_exists('invest_period_type', $data)
            ? (string) $data['invest_period_type']
            : (string) $order->invest_period_type;

        if (!in_array($periodType, ['day', 'hour', 'minute'], true)) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '周期类型无效');
        }
        if (bccomp($investAmount, '0', 4) < 0 || bccomp($profit, '0', 4) < 0 || $cycle < 0) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '金额/收益率/周期不能为负数');
        }

        $moneyChanged = bccomp($investAmount, (string) $order->invest_amount, 4) !== 0
            || bccomp($profit, (string) $order->profit, 4) !== 0;
        $cycleChanged = abs($cycle - (float) $order->cycle) > 0.00001
            || $periodType !== (string) $order->invest_period_type;

        if ($moneyChanged) {
            if ($status !== AdProductOrderModel::STATUS_PENDING) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '待匹配状态才可以修改投资金额和收益率');
            }
            $order->invest_amount = $investAmount;
            $order->profit = $profit;
            $order->profit_amount = bcmul($investAmount, bcdiv($profit, '100', 8), 4);
            $order->price = $investAmount;
        }

        if ($cycleChanged) {
            if (
                $status !== AdProductOrderModel::STATUS_PENDING
                && $status !== AdProductOrderModel::STATUS_BOUGHT
            ) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '待匹配和已购买状态才可以修改广告位周期');
            }
            $order->cycle = $cycle;
            $order->invest_period_type = $periodType;
            if ($status === AdProductOrderModel::STATUS_BOUGHT && (int) $order->started_at > 0) {
                $order->ended_at = $this->calculateEndTimestamp($order, (int) $order->started_at);
            }
        }

        if (!$moneyChanged && !$cycleChanged) {
            return $this->formatItem($order);
        }

        $order->save();

        return $this->formatItem($order->fresh(['user']));
    }

    private function canEditOrder(int $status): bool
    {
        return in_array($status, [
            AdProductOrderModel::STATUS_PENDING,
            AdProductOrderModel::STATUS_BOUGHT,
        ], true);
    }

    private function calculateEndTimestamp(AdProductOrderModel $order, int $startTime): int
    {
        $start = $startTime > 0 ? $startTime : time();
        $cycle = (float) $order->cycle;
        $type = (string) $order->invest_period_type;

        return match ($type) {
            'hour' => (int) ($start + (int) floor($cycle) * 3600 + (int) round(($cycle - floor($cycle)) * 60) * 60),
            'minute' => (int) ($start + (int) round($cycle) * 60),
            default => (int) ($start + (int) floor($cycle) * 86400 + (int) round(($cycle - floor($cycle)) * 24 * 60) * 60),
        };
    }

    private function formatItem(AdProductOrderModel $row, bool $detail = false): array
    {
        $data = $row->toArray();
        $status = (int) $row->status;
        $data['status_text'] = AdProductOrderModel::$statusMap[$row->status] ?? '未知';
        $data['match_type_text'] = (int) $row->match_type === 2 ? '超额匹配' : '正常匹配';
        $data['cycle_text'] = $this->formatCycle((float) $row->cycle, (string) $row->invest_period_type);
        $data['can_edit'] = $this->canEditOrder($status);
        $data['can_edit_money'] = $status === AdProductOrderModel::STATUS_PENDING;
        $data['can_edit_cycle'] = in_array($status, [
            AdProductOrderModel::STATUS_PENDING,
            AdProductOrderModel::STATUS_BOUGHT,
        ], true);
        if ($row->user) {
            $data['username'] = $row->user->username;
            $data['nickname'] = $row->user->nickname;
            $data['mobile'] = $row->user->mobile;
            $data['invite_code'] = $row->user->invite_code ?? '';
            $data['agent_id'] = (int) $row->user->leader1;
        }
        if ($detail) {
            $data['user'] = $row->user?->toArray();
        }

        return $data;
    }

    private function formatCycle(float $cycle, string $periodType): string
    {
        $label = match ($periodType) {
            'hour' => '小时',
            'minute' => '分钟',
            default => '天',
        };

        return rtrim(rtrim(number_format($cycle, 2, '.', ''), '0'), '.') . ' ' . $label;
    }

    /** @return array<string, mixed> */
    private function filterMeta(): array
    {
        $levels = AdConfigModel::query()
            ->orderBy('level_id')
            ->get(['level_id', 'level_name'])
            ->map(fn (AdConfigModel $row) => [
                'level_id' => (int) $row->level_id,
                'level_name' => (string) $row->level_name,
            ])
            ->all();

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
            'levels' => $levels,
            'agents' => $agents,
            'status_map' => AdProductOrderModel::$statusMap,
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
