<?php

declare(strict_types=1);

namespace app\service\api;

use app\exception\BusinessException;
use app\model\ad\AdProductOrderModel;
use app\model\invest\InvestmentProductModel;
use app\model\invest\InvestmentProductOrderModel;
use app\model\invest\InvestmentSpeedCodeModel;
use app\model\member\UserBrokeragePoolModel;
use app\model\member\UserModel;
use app\model\member\UserWaterModel;
use app\service\InvestmentProductService;
use app\service\InvestmentSettlementTimeService;
use app\service\InvestmentProductSessionService;
use app\service\MemberWalletService;
use app\support\DateTimeFormat;
use app\support\ErrorCode;
use app\support\UserWaterDetailType;
use app\support\UserWaterType;
use Illuminate\Database\Capsule\Manager as DB;
use support\Redis;

class ApiInvestmentService
{
    public function products(): array
    {
        $isOpen = (new InvestmentProductSessionService())->isOpenNow();

        return InvestmentProductModel::where('status', 1)
            ->where('sales_status', 1)
            ->orderBy('sort')->orderByDesc('id')->get()
            ->map(fn ($p) => array_merge($p->toArray(), [
                'used_quota' => $this->usedQuota((int) $p->id),
                'quota_full' => $this->isQuotaFull($p),
                'effective_quota' => $this->effectiveQuota($p),
                'is_open' => $isOpen,
                // 长周期展示全局共用次数；短周期仍用产品字段
                'order_limit' => (int) $p->profit_type === 1
                    ? (new InvestmentProductService())->getLongOrderLimit()
                    : (int) ($p->order_limit ?: 5),
            ]))->all();
    }

    public function profitSummary(int $userId): array
    {
        $yesterdayStart = strtotime('yesterday');
        $yesterdayEnd = strtotime('today') - 1;
        return [
            'yesterday_profit' => (string) InvestmentProductOrderModel::where('user_id', $userId)
                ->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])->sum('profit_amount'),
            'total_profit' => (string) InvestmentProductOrderModel::where('user_id', $userId)->sum('profit_amount'),
        ];
    }

    /** AI 智能托管收益流水（detail_type=14） */
    public function profitLog(int $userId, int $page, int $limit, string $startTime = '', string $endTime = ''): array
    {
        $start = $startTime !== '' ? strtotime($startTime) : strtotime('-7 days');
        $end = $endTime !== '' ? strtotime($endTime . ' 23:59:59') : time();

        $query = UserWaterModel::where('user_id', $userId)
            ->where('type', MemberWalletService::WALLET_BASE)
            ->where('direction', MemberWalletService::DIRECTION_IN)
            ->where('detail_type', UserWaterDetailType::AI_TRUST_PROFIT)
            ->whereBetween('created_at', [$start, $end]);

        $totalMoney = (string) (clone $query)->sum('amount');
        $items = $query->orderByDesc('id')->offset(($page - 1) * $limit)->limit($limit)->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'money' => round((float) $row->amount, 2),
                'create_time' => DateTimeFormat::datetime($row->created_at),
                'day' => DateTimeFormat::datetime($row->created_at, 'm-d'),
            ]);

        return [
            'day' => ['started_at' => date('Y-m-d', $start), 'ended_at' => date('Y-m-d', $end)],
            'total_money' => round((float) $totalMoney, 2),
            'list' => $items,
        ];
    }

    public function buy(int $userId, int $productId, string $amount): array
    {
        (new InvestmentProductSessionService())->assertOpenForBuy();

        $todayMatched = AdProductOrderModel::where('user_id', $userId)
            ->whereIn('status', [AdProductOrderModel::STATUS_BOUGHT, AdProductOrderModel::STATUS_WAIT_BUY])
            ->where('created_at', '>=', strtotime(date('Y-m-d')))->exists();
        if (!$todayMatched) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '必须参与一次广告撮合才能进行AI托管');
        }

        $product = InvestmentProductModel::where('id', $productId)->where('status', 1)->first();
        if (!$product) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '产品不存在');
        }

        if ($this->isQuotaFull($product)) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '托管额度已满');
        }

        if ((float) $product->min_balance > 0 && bccomp($amount, (string) $product->min_balance, 2) < 0) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '托管金额不在范围内');
        }
        if ((float) $product->max_balance > 0 && bccomp($amount, (string) $product->max_balance, 2) > 0) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '托管金额不在范围内');
        }

        $profitType = (int) $product->profit_type;
        if ($profitType === 1) {
            // 长周期：所有长周期产品共用全局进行中次数
            $limit = (new InvestmentProductService())->getLongOrderLimit();
            if ($limit > 0) {
                $activeCount = InvestmentProductOrderModel::where('user_id', $userId)
                    ->where('profit_type', 1)
                    ->where('status', InvestmentProductOrderModel::STATUS_BOUGHT)
                    ->count();
                if ($activeCount >= $limit) {
                    throw new BusinessException(ErrorCode::VALIDATION_FAILED, '暂无可用的托管次数');
                }
            }
        } else {
            $activeCount = InvestmentProductOrderModel::where('user_id', $userId)
                ->where('investment_product_id', $productId)
                ->where('status', InvestmentProductOrderModel::STATUS_BOUGHT)
                ->count();
            $limit = (int) ($product->order_limit ?: 5);
            if ($activeCount >= $limit) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '暂无可用的托管次数');
            }
        }

        return DB::transaction(function () use ($userId, $product, $amount, $profitType) {
            $user = UserModel::where('id', $userId)->lockForUpdate()->first();
            if (!$user || bccomp((string) $user->balance, $amount, 8) < 0) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '托管金额不足');
            }

            $now = time();
            if ($profitType === 1) {
                $cycleDays = (float) $product->cycle;
                if ($cycleDays <= 0) {
                    throw new BusinessException(ErrorCode::VALIDATION_FAILED, '长期托管周期未配置');
                }
                $endedAt = (int) ($now + $cycleDays * 86400);
                $cycle = $cycleDays;
                $settlementTimes = 1;
                $profitAmount = bcmul($amount, bcdiv((string) $product->profit, '100', 8), 8);
            } else {
                $short = (new InvestmentSettlementTimeService())->buildShortTermBuyParams(
                    $amount,
                    (string) $product->profit
                );
                $cycle = (float) $short['cycle'];
                $endedAt = (int) $short['ended_at'];
                $settlementTimes = (int) $short['settlement_times'];
                $profitAmount = (string) $short['profit_amount'];
                $profitType = 2;
            }

            (new MemberWalletService())->changeBalance(
                $userId,
                $amount,
                MemberWalletService::WALLET_BASE,
                MemberWalletService::DIRECTION_OUT,
                'AI智能托管',
                UserWaterDetailType::AI_TRUST,
                0,
                '买入AI托管'
            );

            $order = InvestmentProductOrderModel::create([
                'user_id' => $userId,
                'level_id' => (int) $user->level_id,
                'order_no' => 'IV' . date('YmdHis') . random_int(1000, 9999),
                'cycle' => $cycle,
                'ended_at' => $endedAt,
                'money' => $amount,
                'investment_product_id' => $product->id,
                'investment_product_name' => $product->product_name,
                'profit' => $product->profit,
                'profit_amount' => $profitAmount,
                'profit_type' => $profitType > 0 ? $profitType : 2,
                'settled_at' => 0,
                'settlement_times' => $settlementTimes,
                'started_at' => $now,
                'status' => InvestmentProductOrderModel::STATUS_BOUGHT,
            ]);

            UserWaterModel::query()
                ->where('user_id', $userId)
                ->where('detail_type', UserWaterDetailType::AI_TRUST)
                ->where('order_no', 0)
                ->orderByDesc('id')
                ->limit(1)
                ->update(['order_no' => (int) $order->id]);

            $this->distributeInvestCommission($userId, (string) $order->profit_amount, (int) $product->id, (int) $order->id);

            return $order->toArray();
        });
    }

    public function speedSettle(int $userId, int $orderId, string $code): array
    {
        $code = trim($code);
        if ($orderId <= 0) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '订单不存在');
        }
        if ($code === '') {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '请输入加速编码');
        }

        return DB::transaction(function () use ($userId, $orderId, $code) {
            $order = InvestmentProductOrderModel::where('id', $orderId)
                ->where('user_id', $userId)
                ->where('status', InvestmentProductOrderModel::STATUS_BOUGHT)
                ->lockForUpdate()
                ->first();
            if (!$order) {
                throw new BusinessException(ErrorCode::NOT_FOUND, '托管订单不存在');
            }
            if ((int) $order->profit_settled_early === 1) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '利润已提前结算');
            }

            $speed = InvestmentSpeedCodeModel::where('code', $code)
                ->where('status', 1)
                ->where(function ($q) use ($order) {
                    $q->where('product_id', 0)->orWhere('product_id', $order->investment_product_id);
                })
                ->lockForUpdate()
                ->first();
            if (!$speed) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '加速编码无效');
            }
            if ((int) $speed->max_uses > 0 && (int) $speed->used_count >= (int) $speed->max_uses) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '加速编码已用完');
            }

            $profit = (string) $order->profit_amount;
            if (bccomp($profit, '0', 8) <= 0) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '无可结算收益');
            }

            (new MemberWalletService())->changeBalance(
                $userId, $profit, MemberWalletService::WALLET_BASE, MemberWalletService::DIRECTION_IN,
                'AI托管加速收益', UserWaterDetailType::AI_TRUST_PROFIT, (int) $order->id
            );
            $order->update(['profit_settled_early' => 1]);
            $speed->increment('used_count');

            return [
                'profit' => $profit,
                'principal_return_at' => (int) $order->ended_at,
                'profit_settled_early' => 1,
            ];
        });
    }

    public function orderList(int $userId, int $page, int $limit, ?int $status = null): array
    {
        $query = InvestmentProductOrderModel::with('product:id,description')
            ->where('user_id', $userId);
        if ($status !== null) {
            $query->where('status', $status);
        }
        $total = (clone $query)->count();
        $items = $query->orderByDesc('id')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get()
            ->map(fn ($row) => $this->formatOrderForH5($row))
            ->all();

        // H5 EscrowRecords 使用 res.data；后台等场景兼容 items
        return [
            'total' => $total,
            'data' => $items,
            'items' => $items,
        ];
    }

    /** @return array<string, mixed> */
    private function formatOrderForH5(InvestmentProductOrderModel $row): array
    {
        $started = (int) $row->started_at;
        $ended = (int) $row->ended_at;
        $settled = (int) $row->settled_at;

        return [
            'id' => (int) $row->id,
            'user_id' => (int) $row->user_id,
            'level_id' => (int) $row->level_id,
            'order_no' => (string) $row->order_no,
            'cycle' => $row->cycle,
            'end_time' => $ended > 0 ? date('Y/m/d H:i:s', $ended) : '',
            'count_down' => $ended > 0 ? $ended - time() : 0,
            'money' => number_format((float) $row->money, 4, '.', ''),
            'investment_product_name' => (string) $row->investment_product_name,
            'profit' => $row->profit,
            'profit_money' => number_format((float) $row->profit_amount, 4, '.', ''),
            'profit_amount' => $row->profit_amount,
            'profit_type' => (int) $row->profit_type,
            'settlement_time' => $settled > 0 ? date('Y/m/d H:i:s', $settled) : '',
            'start_time' => $started > 0 ? date('Y/m/d H:i:s', $started) : '',
            'status' => (int) $row->status,
            'status_text' => InvestmentProductOrderModel::$statusMap[$row->status] ?? '',
            'description' => (string) ($row->product?->description ?? ''),
            'profit_settled_early' => (int) $row->profit_settled_early,
        ];
    }

    public function orderStats(int $userId): array
    {
        return [
            'total_money' => (string) InvestmentProductOrderModel::where('user_id', $userId)->sum('money'),
            'total_profit' => (string) InvestmentProductOrderModel::where('user_id', $userId)->sum('profit_amount'),
            'active_count' => InvestmentProductOrderModel::where('user_id', $userId)
                ->where('status', InvestmentProductOrderModel::STATUS_BOUGHT)->count(),
        ];
    }

    private function usedQuota(int $productId): string
    {
        $sum = (string) InvestmentProductOrderModel::where('investment_product_id', $productId)
            ->whereIn('status', [InvestmentProductOrderModel::STATUS_BOUGHT, InvestmentProductOrderModel::STATUS_SETTLED])
            ->sum('money');

        return bcadd($sum, '0', 2);
    }

    private function isQuotaFull(InvestmentProductModel $product): bool
    {
        if ((float) $product->total_quota <= 0) {
            return false;
        }

        return bccomp($this->usedQuota((int) $product->id), (string) $product->total_quota, 2) >= 0;
    }

    private function effectiveQuota(InvestmentProductModel $product): string
    {
        return (string) $product->total_quota;
    }

    private function distributeInvestCommission(int $fromUserId, string $profitAmount, int $productId, int $orderId): void
    {
        if (bccomp($profitAmount, '0', 4) <= 0) {
            return;
        }

        $product = InvestmentProductModel::find($productId);
        $rates = is_array($product?->commission_rates) ? $product->commission_rates : [];
        $user = UserModel::find($fromUserId);
        if (!$user) {
            return;
        }

        $wallet = new MemberWalletService();
        foreach ([1 => (int) $user->leader1, 2 => (int) $user->leader2, 3 => (int) $user->leader3] as $lv => $leaderId) {
            if ($leaderId <= 0) {
                continue;
            }
            $rate = (float) ($rates['level' . $lv] ?? 0);
            if ($rate <= 0) {
                continue;
            }
            $commission = bcmul($profitAmount, bcdiv((string) $rate, '100', 8), 4);
            if (bccomp($commission, '0', 4) <= 0) {
                continue;
            }

            $leader = UserModel::find($leaderId);
            if (!$leader) {
                continue;
            }

            $before = (string) $leader->brokerage_balance;
            $leader->brokerage_balance = bcadd($before, $commission, 4);
            $leader->save();

            \app\model\member\UserWaterModel::create([
                'user_id' => $leaderId,
                'order_no' => $orderId,
                'title' => 'AI托管下级返佣',
                'type' => UserWaterType::TYPE_BROKERAGE_POOL,
                'detail_type' => UserWaterDetailType::INVEST_COMMISSION,
                'direction' => 1,
                'amount' => $commission,
                'before_balance' => $before,
                'after_balance' => (string) $leader->brokerage_balance,
            ]);

            $pool = UserBrokeragePoolModel::firstOrCreate(
                ['user_id' => $leaderId],
                ['receivable' => 0, 'received' => 0]
            );
            $pool->receivable = bcadd((string) $pool->receivable, $commission, 4);
            $pool->save();
        }
    }
}
