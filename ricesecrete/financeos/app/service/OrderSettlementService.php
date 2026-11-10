<?php

declare(strict_types=1);

namespace app\service;

use app\model\ad\AdProductOrderModel;
use app\model\invest\InvestmentProductOrderModel;
use app\support\UserWaterDetailType;
use app\support\UserWaterType;
use Illuminate\Database\Capsule\Manager as DB;
use support\Log;

class OrderSettlementService
{
    public function __construct(
        private ?MemberWalletService $walletService = null,
        private ?WithdrawService $withdrawService = null,
        private ?BrokeragePoolSettlementService $brokeragePoolService = null,
        private ?PromotionRewardSettlementService $promotionRewardService = null,
        private ?TrialBonusSettlementService $trialBonusService = null,
        private ?TradeDividendService $tradeDividendService = null,
        private ?InviteValidUserSettlementService $inviteValidService = null,
        private ?WithdrawLevelService $withdrawLevelService = null,
    ) {
        $this->walletService = $walletService ?? new MemberWalletService();
        $this->withdrawService = $withdrawService ?? new WithdrawService();
        $this->brokeragePoolService = $brokeragePoolService ?? new BrokeragePoolSettlementService();
        $this->promotionRewardService = $promotionRewardService ?? new PromotionRewardSettlementService();
        $this->trialBonusService = $trialBonusService ?? new TrialBonusSettlementService();
        $this->tradeDividendService = $tradeDividendService ?? new TradeDividendService();
        $this->inviteValidService = $inviteValidService ?? new InviteValidUserSettlementService();
        $this->withdrawLevelService = $withdrawLevelService ?? new WithdrawLevelService();
    }

    /**
     * 执行全部结算任务
     *
     * @return array<string, int>
     */
    public function runAll(): array
    {
        return [
            'ad_settled'          => $this->settleAdProductOrders(),
            'ad_expired'          => $this->expireAdProductOrders(),
            'investment_settled'  => $this->settleInvestmentOrders(),
            'withdraw_moved'      => $this->withdrawService->movePendingToWait(),
            'brokerage_settled'   => $this->brokeragePoolService->settle(),
            'promotion_rewarded'  => $this->promotionRewardService->addRewards(),
            'trial_returned'      => $this->trialBonusService->returnExpired(),
            'trade_dividend_settled' => $this->tradeDividendService->settle(),
            'invite_valid_settled' => $this->inviteValidService->settle(),
            'withdraw_level_updated' => $this->withdrawLevelService->updateLevels(),
            'deposit_returned'    => $this->withdrawLevelService->returnDeposits(),
        ];
    }

    /**
     * 广告位订单到期结算（profit_type=2 本息到期还）
     */
    public function settleAdProductOrders(): int
    {
        $this->backfillAdOrderSettleFields();

        $now = time();
        $orders = AdProductOrderModel::query()
            ->where('status', AdProductOrderModel::STATUS_BOUGHT)
            ->whereIn('profit_type', [0, 2])
            ->where('ended_at', '>', 0)
            ->where('ended_at', '<', $now)
            ->limit(200)
            ->get();

        $count = 0;
        foreach ($orders as $order) {
            try {
                $settled = false;
                DB::transaction(function () use ($order, $now, &$settled) {
                    $locked = AdProductOrderModel::where('id', $order->id)
                        ->where('status', AdProductOrderModel::STATUS_BOUGHT)
                        ->lockForUpdate()
                        ->first();

                    if (!$locked || (int) $locked->ended_at <= 0 || (int) $locked->ended_at >= $now) {
                        return;
                    }

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
                            '广告订单到期还本'
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
                            '广告订单到期收益'
                        );
                    }

                    $locked->status = AdProductOrderModel::STATUS_SETTLED;
                    $locked->settled_at = $now;
                    $locked->settlement_times = (int) $locked->settlement_times + 1;
                    if ((int) $locked->profit_type === 0) {
                        $locked->profit_type = 2;
                    }
                    $locked->save();
                    $settled = true;
                });
                if ($settled) {
                    $count++;
                }
            } catch (\Throwable $e) {
                Log::error('ad order settle failed: ' . $e->getMessage(), ['order_id' => $order->id]);
            }
        }

        return $count;
    }

    /**
     * 回填历史已购买但未写入结算字段的广告订单（ended_at/profit_type）
     */
    private function backfillAdOrderSettleFields(): void
    {
        $orders = AdProductOrderModel::query()
            ->where('status', AdProductOrderModel::STATUS_BOUGHT)
            ->where(function ($query) {
                $query->where('ended_at', 0)->orWhereNull('ended_at');
            })
            ->where('bought_at', '>', 0)
            ->limit(500)
            ->get();

        foreach ($orders as $order) {
            $start = (int) $order->bought_at;
            if ($start <= 0) {
                $start = (int) $order->matched_at;
            }
            if ($start <= 0) {
                continue;
            }

            $cycle = (float) $order->cycle;
            $endedAt = match ((string) $order->invest_period_type) {
                'hour' => $start + (int) round($cycle * 3600),
                'minute' => $start + (int) round($cycle * 60),
                default => $start + (int) round($cycle * 86400),
            };

            $order->started_at = (int) $order->started_at > 0 ? (int) $order->started_at : $start;
            $order->ended_at = $endedAt;
            if ((int) $order->profit_type === 0) {
                $order->profit_type = 2;
            }
            $order->save();
        }
    }

    /**
     * 待购买订单超时过期（24小时）
     */
    public function expireAdProductOrders(): int
    {
        $deadline = time() - 86400;
        return AdProductOrderModel::query()
            ->where('status', AdProductOrderModel::STATUS_WAIT_BUY)
            ->where('matched_at', '>', 0)
            ->where('matched_at', '<', $deadline)
            ->update(['status' => AdProductOrderModel::STATUS_EXPIRED]);
    }

    /**
     * AI 托管订单到期结算
     */
    public function settleInvestmentOrders(): int
    {
        $now = time();
        $orders = InvestmentProductOrderModel::query()
            ->where('status', InvestmentProductOrderModel::STATUS_BOUGHT)
            ->where('ended_at', '>', 0)
            ->where('ended_at', '<', $now)
            ->limit(200)
            ->get();

        $count = 0;
        foreach ($orders as $order) {
            try {
                $settled = false;
                DB::transaction(function () use ($order, $now, &$settled) {
                    $locked = InvestmentProductOrderModel::where('id', $order->id)
                        ->where('status', InvestmentProductOrderModel::STATUS_BOUGHT)
                        ->lockForUpdate()
                        ->first();

                    if (!$locked || (int) $locked->ended_at >= $now) {
                        return;
                    }

                    $userId = (int) $locked->user_id;
                    $principal = (string) $locked->money;
                    $profit = (string) $locked->profit_amount;

                    if (bccomp($principal, '0', 4) > 0) {
                        $this->walletService->changeBalance(
                            $userId,
                            $principal,
                            UserWaterType::TYPE_USER,
                            MemberWalletService::DIRECTION_IN,
                            'AI托管本金',
                            UserWaterDetailType::AI_TRUST,
                            (int) $locked->id,
                            'AI托管到期还本'
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
                            'AI托管到期收益'
                        );
                    }

                    $locked->status = InvestmentProductOrderModel::STATUS_SETTLED;
                    $locked->settled_at = $now;
                    $locked->settlement_times = (int) $locked->settlement_times + 1;
                    $locked->save();
                    $settled = true;
                });
                if ($settled) {
                    $count++;
                }
            } catch (\Throwable $e) {
                Log::error('investment order settle failed: ' . $e->getMessage(), ['order_id' => $order->id]);
            }
        }

        return $count;
    }
}
