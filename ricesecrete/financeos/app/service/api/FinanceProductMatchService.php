<?php

declare(strict_types=1);

namespace app\service\api;

use app\exception\FinanceProductMatchException;
use app\model\ad\AdProductOrderModel;
use app\model\member\UserAdRatioConfigModel;
use app\model\member\UserModel;
use app\service\MemberWalletService;
use app\service\SettingService;
use app\support\FinanceProductMatchResult;
use app\support\FinanceProductPopupConfig;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

class FinanceProductMatchService
{
    public const MATCH_TYPE_NORMAL = 1;
    public const MATCH_TYPE_OVERDUE = 2;

    public function __construct(
        private ?FinanceProductLevelConfigService $levelService = null,
        private ?SettingService $settingService = null,
        private ?MemberWalletService $walletService = null,
        private ?FinanceProductVipUpgradeService $vipUpgradeService = null,
        private ?FinanceProductLevelTeamService $teamService = null,
        private ?FinanceProductPlanService $planService = null,
        private ?FinanceProductPostMatchService $postMatchService = null,
        private ?FinanceProductInvestRateService $investRateService = null,
        private ?FinanceProductExperienceService $experienceService = null,
    ) {
        $this->levelService = $levelService ?? new FinanceProductLevelConfigService();
        $this->settingService = $settingService ?? new SettingService();
        $this->walletService = $walletService ?? new MemberWalletService();
        $this->vipUpgradeService = $vipUpgradeService ?? new FinanceProductVipUpgradeService();
        $this->teamService = $teamService ?? new FinanceProductLevelTeamService();
        $this->planService = $planService ?? new FinanceProductPlanService();
        $this->postMatchService = $postMatchService ?? new FinanceProductPostMatchService();
        $this->investRateService = $investRateService ?? new FinanceProductInvestRateService();
        $this->experienceService = $experienceService ?? new FinanceProductExperienceService();
    }

    public function execute(int $userId, int $levelId, string $password, int $matchType, int $type = 0): array
    {
        $level = $this->levelService->getLevel($levelId);
        if (!$level) {
            return FinanceProductMatchResult::error(-1, '不存在的广告位');
        }

        try {
            $this->levelService->checkPassword($password, $levelId, $type, -2);
        } catch (FinanceProductMatchException $e) {
            return FinanceProductMatchResult::error($e->matchCode, $e->getMessage());
        }

        $user = UserModel::find($userId);
        if (!$user) {
            return FinanceProductMatchResult::error(-1, '用户不存在');
        }

        [$orders, $taskSession] = $this->getLevelSessionOrders($levelId, $userId);
        $orders = collect($orders);

        if ($orders->isEmpty()) {
            if (!$taskSession) {
                return FinanceProductMatchResult::error(-1, (string) ($level['unlock_time_disabled_tips'] ?? '目前无法购买'));
            }
            if (!$this->levelService->canUnlockAtCurrentTime($level)) {
                return FinanceProductMatchResult::error(-1, (string) ($level['unlock_time_disabled_tips'] ?? '目前无法购买'));
            }
            if (bccomp((string) $user->balance, (string) ($level['price'] ?? 0), 4) < 0) {
                return FinanceProductMatchResult::error(-1, '当前账户余额未满足该交易区最低竞价区间要求');
            }

            if ($matchType === self::MATCH_TYPE_NORMAL) {
                $excessConfig = $this->levelService->getTeamRatioConfig($userId, $levelId);
                if ($excessConfig && $this->hasPositiveRatio($excessConfig)) {
                    return FinanceProductMatchResult::error(-3, '广告库存已售空,执行超额匹配');
                }
            }

            if ((int) ($level['session_count'] ?? 0) > 0) {
                $expCount = $this->experienceService->getSessionsCount($userId);
                if ($expCount >= (int) $level['session_count']) {
                    return FinanceProductMatchResult::error(-1, '暂无可匹配的广告库存');
                }
            }

            [$currentInventory, $totalInventory, $canOverMatch, , $moneyInterval] = $this->checkStock($level, $user, $taskSession);
            if (!$currentInventory && !$totalInventory && $matchType === self::MATCH_TYPE_NORMAL) {
                if ($canOverMatch) {
                    return FinanceProductMatchResult::error(-3, '广告库存已售空,执行超额匹配');
                }
                $msg = match ($moneyInterval) {
                    -1 => '当前账户余额未达到该交易区的竞价区间',
                    1 => '当前账户余额已超出该交易区的竞价区间',
                    default => '广告库存已售空',
                };
                return FinanceProductMatchResult::error(-1, $msg);
            }

            $upgrade = $this->vipUpgradeService->upgradeBeforeMatch($userId, $levelId);
            if (!in_array((int) $upgrade['code'], [1, 13], true)) {
                return FinanceProductMatchResult::error(-1, (string) $upgrade['msg']);
            }
            $user->refresh();
        } else {
            $first = $orders->first();
            if ((int) $first->level_id !== $levelId && (string) $first->session_no !== '') {
                return FinanceProductMatchResult::error(-1, '无法进入当前广告位');
            }
            if (!$this->isNeedToast($orders, $user, $levelId)) {
                return $this->performMatch($user, $level, $password, self::MATCH_TYPE_OVERDUE, $orders, $taskSession);
            }
        }

        return $this->performMatch($user, $level, $password, $matchType, $orders, $taskSession);
    }

    public function validateOverdueRequest(int $userId, int $levelId, string $password, int $type, array $signData, string $sign): void
    {
        try {
            $this->levelService->checkPassword($password, $levelId, $type, -2);
        } catch (FinanceProductMatchException $e) {
            throw $e;
        }

        \app\support\ApiSignHelper::verify($signData, $sign);

        $level = $this->levelService->getLevel($levelId);
        if (!$level) {
            throw new FinanceProductMatchException(-1, '不存在的广告位');
        }
        if (!$this->levelService->canUnlockAtCurrentTime($level)) {
            throw new FinanceProductMatchException(-1, (string) ($level['unlock_time_disabled_tips'] ?? '目前无法购买'));
        }

        $user = UserModel::find($userId);
        if (!$user) {
            throw new FinanceProductMatchException(-1, '用户不存在');
        }

        [$orders] = $this->getLevelSessionOrders($levelId, $userId);
        if (collect($orders)->isEmpty()) {
            [, , $canOverMatch, ,] = $this->checkStock($level, $user, $this->levelService->getCurrentTaskSession($levelId));
            if (!$canOverMatch) {
                throw new FinanceProductMatchException(-1, '广告库存已售空');
            }
        }
    }

    private function performMatch(
        UserModel $user,
        array $level,
        string $password,
        int $matchType,
        Collection $orders,
        ?array $taskSession
    ): array {
        if (!$taskSession) {
            return FinanceProductMatchResult::error(-1, '目前无法购买');
        }

        $levelId = (int) $level['level_id'];
        [$level, $levelFirst] = $this->teamService->applyTeamLevel((int) $user->id, $levelId, $level);
        [$plan, $adPlanId] = $this->planService->resolvePlan($user, $levelId, $taskSession);

        $config = $this->buildInvestConfig($level, $user, $levelId, $taskSession, $levelFirst, $plan, $adPlanId);
        $config = $this->planService->applyPlanToConfig($config, $plan, $adPlanId, $levelFirst);

        if ($orders->isEmpty() && $matchType === self::MATCH_TYPE_NORMAL && (float) $config['invest_rate'] > 100) {
            return FinanceProductMatchResult::error(-3, '当前预算内库存已撮合完成，是否启用信用预支参与更高价位库存交易？');
        }

        return DB::transaction(function () use ($user, $level, $matchType, $orders, $taskSession, $config, $levelId, $levelFirst) {
            $sessionNo = $orders->isNotEmpty()
                ? (string) $orders->first()->session_no
                : $this->generateSessionNo((int) $user->id);
            $balance = (string) $user->balance;

            $matchedCount = $this->countMatchedOrders($orders);
            if ($levelFirst && $orders->isNotEmpty()) {
                $pendingOrders = $orders->filter(fn ($item) => (int) $item->status === AdProductOrderModel::STATUS_PENDING);
                $hasTeamLevel = $pendingOrders->contains(fn ($item) => (int) $item->is_team_level === 1);
                if ($pendingOrders->isNotEmpty() && !$hasTeamLevel) {
                    $sessionNo = (string) $pendingOrders->first()->session_no;
                    $balance = (string) $pendingOrders->first()->balance;
                    foreach ($pendingOrders as $pendingOrder) {
                        $pendingOrder->delete();
                    }
                    $matchedCount = $this->countMatchedOrders(
                        $orders->filter(fn ($item) => (int) $item->status !== AdProductOrderModel::STATUS_PENDING)
                    );
                    $orders = collect();
                }
            }

            if ($orders->isEmpty()) {
                $matchedCount = $this->createPendingOrders(
                    $user, $level, $config, $sessionNo, $matchType, $balance, $taskSession, $levelFirst
                );
            }

            $pending = $this->resolvePendingOrder($levelId, (int) $user->id, $sessionNo, $matchedCount, $orders);
            if (!$pending) {
                return FinanceProductMatchResult::error(-1, '暂无可匹配的广告库存');
            }

            $orderData = $this->finalizeOrder($pending, $user);
            $this->activateSessionOrdersIfReady($sessionNo, $user, $pending);
            $this->postMatchService->onMatchSuccess($orderData, $user, $level, $taskSession);

            return FinanceProductMatchResult::successOrder($orderData);
        });
    }

    private function buildInvestConfig(
        array $level,
        UserModel $user,
        int $levelId,
        ?array $taskSession,
        bool $levelFirst = false,
        mixed $plan = null,
        int $adPlanId = 0
    ): array {
        $teamConfig = $this->levelService->getTeamRatioConfig((int) $user->id, $levelId);
        $userConfig = $this->levelService->getUserRatioConfig((int) $user->id, $levelId);

        $investNum = (int) ($level['invest_num'] ?? 1);
        $investRate = (float) ($level['invest_rate'] ?? 30);
        $cycle = (float) ($level['invest_day'] ?? 1);
        $periodType = (string) ($level['invest_period_type'] ?? 'day');
        $profit = (float) ($level['invest_profit'] ?? 1.5);
        $ratios = null;
        $stockRange = ['min' => 0, 'max' => 0];

        if ($teamConfig && $this->hasPositiveRatio($teamConfig)) {
            $ratios = array_values(array_filter(array_map('floatval', (array) $teamConfig->ratio)));
            $investNum = max($investNum, count($ratios));
        } elseif ($userConfig && $this->hasPositiveRatio($userConfig)) {
            $ratios = array_values(array_filter(array_map('floatval', (array) $userConfig->ratio)));
            $investNum = max($investNum, count($ratios));
            $profitRates = (array) ($userConfig->profit_rate ?? []);
            $adCycles = (array) ($userConfig->ad_cycle ?? []);
        } elseif ($levelFirst) {
            $investRate = $this->investRateService->resolveRate($level, (int) $user->id);
        } else {
            [, , , $stockRange] = $this->checkStock($level, $user, $taskSession);
            if (!empty($stockRange['rate'])) {
                $investRate = (float) $stockRange['rate'];
            } else {
                $investRate = $this->investRateService->resolveRate($level, (int) $user->id);
            }
            if (!empty($stockRange['profit'])) {
                $profit = (float) $stockRange['profit'];
            }
            if (!empty($stockRange['period'])) {
                $cycle = (float) $stockRange['period'];
            }
            if (!empty($stockRange['period_type'])) {
                $periodType = (string) $stockRange['period_type'];
            }
        }

        return [
            'invest_num' => max(1, $investNum),
            'invest_rate' => $investRate,
            'cycle' => $cycle,
            'invest_period_type' => $periodType,
            'invest_profit' => $profit,
            'ratios' => $ratios,
            'profit_rates' => $profitRates ?? null,
            'ad_cycles' => $adCycles ?? null,
            'fee_rate' => (float) ($level['fee_rate'] ?? 0),
            'user_excess_config' => $teamConfig,
            'stock_range' => $stockRange,
            'ad_plan_days_config_id' => (int) ($plan?->id ?? 0),
            'is_plan' => 0,
        ];
    }

    private function createPendingOrders(
        UserModel $user,
        array $level,
        array $config,
        string $sessionNo,
        int $matchType,
        string $balance,
        ?array $taskSession,
        bool $levelFirst = false
    ): int {
        $levelId = (int) $level['level_id'];
        $amounts = $this->calculateInvestAmounts($user, $level, $config, $matchType, $balance, $taskSession);
        if ($amounts === []) {
            throw new FinanceProductMatchException(-1, '当前账户余额未满足该交易区最低竞价区间要求');
        }

        if (!empty($config['stock_range']['settle_type']) && (int) $config['stock_range']['settle_type'] === 2) {
            $balance = (string) ($config['stock_range']['min'] ?? $balance);
        }

        $now = time();
        $overdueMoney = $this->calcOverdueMoney((float) $balance, (int) $user->credit_score);
        $stockRange = $config['stock_range'] ?? ['min' => 0, 'max' => 0];
        $popupRaw = $this->settingService->getValue('finance_product_data', []);
        $popupParsed = FinanceProductPopupConfig::parseStored($popupRaw);
        $otherInfo = FinanceProductPopupConfig::buildOtherInfo($popupParsed['items']);
        $otherInfoJson = json_encode($otherInfo, JSON_UNESCAPED_UNICODE);
        foreach ($amounts as $index => $item) {
            AdProductOrderModel::create([
                'user_id' => $user->id,
                'level_id' => $levelId,
                'order_no' => 'AD' . date('YmdHis') . random_int(1000, 9999),
                'cycle' => $item['cycle'],
                'invest_period_type' => $item['invest_period_type'],
                'balance' => $balance,
                'price' => $item['amount'],
                'invest_amount' => $item['amount'],
                'product_name' => (string) ($level['name'] ?? '广告位'),
                'profit' => $item['profit'],
                'profit_amount' => bcmul((string) $item['amount'], bcdiv((string) $item['profit'], '100', 4), 4),
                'profit_type' => 2,
                'fee_rate' => $config['fee_rate'],
                'fee' => bcmul((string) $item['amount'], bcdiv((string) $config['fee_rate'], '100', 4), 4),
                'sort' => $index + 1,
                'status' => AdProductOrderModel::STATUS_PENDING,
                'session_no' => $sessionNo,
                'match_type' => $matchType,
                'matched_at' => $now,
                'stock_range_min' => $stockRange['min'] ?? 0,
                'stock_range_max' => $stockRange['max'] ?? 0,
                'overdue_amount' => $overdueMoney,
                'is_team_level' => $levelFirst ? 1 : 0,
                'invest_rate' => $item['invest_rate'],
                'ad_plan_days_config_id' => (int) ($config['ad_plan_days_config_id'] ?? 0),
                'other_info' => $otherInfoJson,
            ]);
        }

        return count($amounts);
    }

    /** @return array<int, array{amount: string, cycle: float, invest_period_type: string, profit: float, invest_rate: float}> */
    private function calculateInvestAmounts(UserModel $user, array $level, array $config, int $matchType, string $balance, ?array $taskSession): array
    {
        if (!empty($config['ratios'])) {
            $items = [];
            $profitRates = $config['profit_rates'] ?? [];
            $adCycles = $config['ad_cycles'] ?? [];
            foreach ($config['ratios'] as $index => $ratio) {
                if ($ratio <= 0) {
                    continue;
                }
                $amount = bcmul($balance, bcdiv((string) $ratio, '100', 4), 4);
                if (bccomp($amount, '1', 4) < 0) {
                    throw new FinanceProductMatchException(-1, '当前账户余额未满足该交易区最低竞价区间要求');
                }
                $cycle = $adCycles[$index]['value'] ?? $adCycles[$index] ?? $config['cycle'];
                $periodType = $adCycles[$index]['unit'] ?? $config['invest_period_type'];
                $profit = $profitRates[$index] ?? $config['invest_profit'];
                $items[] = [
                    'amount' => $amount,
                    'cycle' => (float) $cycle,
                    'invest_period_type' => (string) $periodType,
                    'profit' => (float) $profit,
                    'invest_rate' => (float) $ratio,
                ];
            }
            return $items;
        }

        [, , $canOverMatch, $stockRange] = $this->checkStock($level, $user, $taskSession);
        if ($matchType === self::MATCH_TYPE_OVERDUE && $canOverMatch && !empty($stockRange['amount_range_excess_invest_rate'])) {
            $items = [];
            foreach ($stockRange['amount_range_excess_invest_rate'] as $rateItem) {
                $rate = (float) ($rateItem['rate'] ?? 0);
                if ($rate <= 0) {
                    continue;
                }
                $items[] = [
                    'amount' => bcmul($balance, bcdiv((string) $rate, '100', 4), 4),
                    'cycle' => (float) ($rateItem['period'] ?? $config['cycle']),
                    'invest_period_type' => (string) ($rateItem['period_type'] ?? $config['invest_period_type']),
                    'profit' => (float) ($rateItem['profit'] ?? $config['invest_profit']),
                    'invest_rate' => $rate,
                ];
            }
            if ($items !== []) {
                return $items;
            }

            $excessRates = array_filter(array_map('floatval', (array) ($level['excess_invest_rate'] ?? [])));
            foreach ($excessRates as $rate) {
                $items[] = [
                    'amount' => bcmul($balance, bcdiv((string) $rate, '100', 4), 4),
                    'cycle' => (float) $config['cycle'],
                    'invest_period_type' => (string) $config['invest_period_type'],
                    'profit' => (float) $config['invest_profit'],
                    'invest_rate' => $rate,
                ];
            }
            return $items;
        }

        $investRate = (float) $config['invest_rate'];
        $investNum = (int) $config['invest_num'];
        $total = bcmul($balance, bcdiv((string) $investRate, '100', 4), 4);
        if (bccomp(bcdiv($total, (string) max(1, $investNum), 4), '1', 4) < 0) {
            throw new FinanceProductMatchException(-1, '当前账户余额未满足该交易区最低竞价区间要求');
        }

        $parts = $this->distributeNumber((float) $total, $investNum);
        $items = [];
        foreach ($parts as $amount) {
            $items[] = [
                'amount' => (string) $amount,
                'cycle' => (float) ($stockRange['period'] ?? $config['cycle']),
                'invest_period_type' => (string) ($stockRange['period_type'] ?? $config['invest_period_type']),
                'profit' => (float) ($stockRange['profit'] ?? $config['invest_profit']),
                'invest_rate' => $investRate,
            ];
        }
        return $items;
    }

    private function finalizeOrder(AdProductOrderModel $order, UserModel $user): array
    {
        $user = UserModel::lockForUpdate()->find($user->id);
        $fee = (string) ($order->fee ?? '0');
        $amount = (string) $order->invest_amount;
        $overdueMoney = (string) ($order->overdue_amount ?? $this->calcOverdueMoney((float) $user->balance, (int) $user->credit_score));
        $balance = (string) $user->balance;

        if (bccomp($balance, $amount, 4) >= 0 || bccomp(bcsub($balance, $amount, 4), $overdueMoney, 4) >= 0) {
            $status = AdProductOrderModel::STATUS_PURCHASED;
        } elseif (bccomp($balance, $overdueMoney, 4) < 0) {
            throw new FinanceProductMatchException(-1, '当前存在未完成订单，请完成后再继续交易');
        } else {
            $status = AdProductOrderModel::STATUS_WAIT_BUY;
        }

        $now = time();
        $order->update([
            'status' => $status,
            'matched_at' => $now,
            'bought_at' => $status === AdProductOrderModel::STATUS_PURCHASED ? $now : 0,
        ]);

        if ($status === AdProductOrderModel::STATUS_PURCHASED) {
            $this->walletService->changeBalance((int) $user->id, $amount, MemberWalletService::WALLET_BASE, MemberWalletService::DIRECTION_OUT, '库存买入', 12, (int) $order->id);
            if (bccomp($fee, '0', 4) > 0) {
                $this->walletService->changeBalance((int) $user->id, $fee, MemberWalletService::WALLET_BASE, MemberWalletService::DIRECTION_OUT, '交易费率', 30, (int) $order->id);
            }
            if ((int) $order->profit_type <= 0) {
                $order->update(['profit_type' => 2]);
            }
        }

        $user->refresh();
        return FinanceProductPopupConfig::formatOrderForH5(array_merge($order->fresh()->toArray(), [
            'id' => $order->id,
            'order_id' => $order->id,
            'user_money' => (string) bcsub(bcsub((string) $user->balance, $amount, 4), $fee, 4),
            'old_user_money' => (string) $user->balance,
            'overdue_amount' => $overdueMoney,
        ]));
    }

    /**
     * 时段内最后一笔撮合完成且余额满足逾期门槛时，为整场订单写入结算起止时间
     */
    private function activateSessionOrdersIfReady(string $sessionNo, UserModel $user, AdProductOrderModel $currentOrder): void
    {
        $orders = AdProductOrderModel::where('user_id', $user->id)
            ->where('session_no', $sessionNo)
            ->orderBy('sort')
            ->get();
        if ($orders->isEmpty() || (int) $currentOrder->sort !== $orders->count()) {
            return;
        }

        $user->refresh();
        $overdue = (string) ($currentOrder->overdue_amount ?? '0');
        if (bccomp((string) $user->balance, $overdue, 4) < 0) {
            return;
        }

        $now = time();
        $endedAt = $this->calculateEndedAt($currentOrder, $now);
        foreach ($orders as $item) {
            if ((int) $item->status === AdProductOrderModel::STATUS_SETTLED) {
                continue;
            }
            // 已有结束时间的不重复改写（避免结算窗口被拉长）
            if ((int) $item->ended_at > 0) {
                continue;
            }
            $payload = [
                'started_at' => $now,
                'ended_at' => $endedAt,
                'profit_type' => (int) $item->profit_type > 0 ? (int) $item->profit_type : 2,
            ];
            // 仅给已扣款购买的订单写入结算时间；待购买不自动转已购买
            if ((int) $item->status !== AdProductOrderModel::STATUS_BOUGHT) {
                continue;
            }
            $item->update($payload);
        }
    }

    private function calculateEndedAt(AdProductOrderModel $order, int $startTime): int
    {
        $cycle = (float) $order->cycle;
        return match ((string) $order->invest_period_type) {
            'hour' => $startTime + (int) round($cycle * 3600),
            'minute' => $startTime + (int) round($cycle * 60),
            default => $startTime + (int) round($cycle * 86400),
        };
    }

    /**
     * 当前时段会话订单（供 index 统计 product_count / 撮合共用）
     *
     * @return array{0: array<int, AdProductOrderModel>, 1: ?array}
     */
    public function getLevelSessionOrders(int $levelId, int $userId): array
    {
        $taskSession = $this->levelService->getCurrentTaskSession($levelId);
        $pending = AdProductOrderModel::where('user_id', $userId)
            ->where('status', AdProductOrderModel::STATUS_PENDING)
            ->orderByDesc('id')->first();

        if ($pending && $pending->session_no) {
            $orders = AdProductOrderModel::where('user_id', $userId)
                ->where('level_id', $pending->level_id)
                ->where('session_no', $pending->session_no)
                ->orderBy('sort')->get()->all();
            return [$orders, $taskSession];
        }

        if (!$taskSession) {
            return [[], null];
        }

        [$start, $end] = $this->sessionTimestampRange($taskSession);
        $existing = AdProductOrderModel::where('user_id', $userId)
            ->where('level_id', $levelId)
            ->whereBetween('matched_at', [$start, $end])
            ->orderByDesc('id')->first();

        if ($existing && $existing->session_no) {
            $orders = AdProductOrderModel::where('user_id', $userId)
                ->where('level_id', $levelId)
                ->where('session_no', $existing->session_no)
                ->orderBy('sort')->get()->all();
            return [$orders, $taskSession];
        }

        $orders = AdProductOrderModel::where('user_id', $userId)
            ->where('level_id', $levelId)
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('sort')->get()->all();

        return [$orders, $taskSession];
    }

    private function checkStock(array $level, UserModel $user, ?array $taskSession): array
    {
        $currentInventory = false;
        $totalInventory = false;
        $canOverMatch = false;
        $moneyInterval = 0;
        $stockRange = ['min' => 0, 'max' => 0, 'amount_range_excess_invest_rate' => []];

        if (!$taskSession) {
            return [$currentInventory, $totalInventory, $canOverMatch, $stockRange, $moneyInterval];
        }

        $ranges = $level['amount_range_inventory'] ?? [];
        if (is_array($ranges) && $ranges !== []) {
            $balance = (float) $user->balance;
            $matchedRanges = [];
            foreach ($ranges as $range) {
                $min = (float) ($range['min'] ?? 0);
                $max = (float) ($range['max'] ?? 0);
                if ($balance >= $min && $balance <= $max) {
                    $moneyInterval = 0;
                    $matchedRanges[] = $range;
                    $count = $this->countSessionBuyers($level, $range, $taskSession, (int) $user->id);
                    if ((int) ($range['stock'] ?? 0) > $count) {
                        $currentInventory = true;
                        $stockRange = [
                            'min' => $min,
                            'max' => $max,
                            'rate' => (float) ($range['rate'] ?? 0),
                            'profit' => (float) ($range['profit'] ?? 0),
                            'period' => (float) ($range['period'] ?? 1),
                            'period_type' => (string) ($range['period_type'] ?? 'day'),
                            'amount_range_excess_invest_rate' => $range['amount_range_excess_invest_rate'] ?? [],
                        ];
                        break;
                    }
                } elseif ($balance < $min) {
                    $moneyInterval = -1;
                } elseif ($balance > $max) {
                    $moneyInterval = 1;
                }
            }

            if (!$currentInventory) {
                foreach ($matchedRanges as $range) {
                    if (!empty($range['amount_range_excess_invest_rate'])) {
                        $excess = is_array($range['amount_range_excess_invest_rate'])
                            ? $range['amount_range_excess_invest_rate']
                            : json_decode((string) $range['amount_range_excess_invest_rate'], true);
                        if (is_array($excess) && array_sum(array_column($excess, 'rate')) > 0) {
                            $canOverMatch = true;
                            $stockRange = [
                                'min' => 0,
                                'max' => 0,
                                'rate' => (float) ($range['rate'] ?? 0),
                                'profit' => (float) ($range['profit'] ?? 0),
                                'period' => (float) ($range['period'] ?? 1),
                                'period_type' => (string) ($range['period_type'] ?? 'day'),
                                'amount_range_excess_invest_rate' => $excess,
                            ];
                            break;
                        }
                    }
                }
            }

            if (!$currentInventory) {
                $limit = (int) ($level['limit_unlock_num'] ?? 0);
                if ($limit === 0) {
                    $totalInventory = true;
                } else {
                    $rangeStock = array_sum(array_map(fn ($r) => (int) ($r['stock'] ?? 0), $ranges));
                    $otherStock = $limit - $rangeStock;
                    $count = $this->countSessionBuyers($level, ['min' => 0, 'max' => 0], $taskSession, (int) $user->id);
                    $totalInventory = $otherStock > $count;
                }
            }
        } else {
            $limit = (int) ($level['limit_unlock_num'] ?? 0);
            if ($limit === 0) {
                $totalInventory = true;
            } else {
                $count = $this->countSessionBuyers($level, ['min' => 0, 'max' => 0], $taskSession, (int) $user->id);
                $totalInventory = $limit > $count;
            }
        }

        $excessRates = array_filter(array_map('floatval', (array) ($level['excess_invest_rate'] ?? [])));
        if ($excessRates !== []) {
            $canOverMatch = true;
        }

        $teamConfig = $this->levelService->getTeamRatioConfig((int) $user->id, (int) $level['level_id']);
        if ($teamConfig && $this->hasPositiveRatio($teamConfig)) {
            $canOverMatch = true;
        }

        return [$currentInventory, $totalInventory, $canOverMatch, $stockRange, $moneyInterval];
    }

    private function countSessionBuyers(array $level, array $range, array $taskSession, int $excludeUserId, int $matchType = 1): int
    {
        [$start, $end] = $this->sessionTimestampRange($taskSession);
        return AdProductOrderModel::where('level_id', (int) $level['level_id'])
            ->where('user_id', '<>', $excludeUserId)
            ->where('stock_range_min', (float) ($range['min'] ?? 0))
            ->where('stock_range_max', (float) ($range['max'] ?? 0))
            ->where('match_type', $matchType)
            ->whereBetween('matched_at', [$start, $end])
            ->count();
    }

    private function sessionTimestampRange(array $taskSession): array
    {
        $date = date('Y-m-d');
        return [
            strtotime($date . ' ' . ($taskSession['started_at'] ?? '00:00:00')),
            strtotime($date . ' ' . ($taskSession['ended_at'] ?? '23:59:59')),
        ];
    }

    private function countMatchedOrders(Collection $orders): int
    {
        return $orders->filter(fn ($item) => in_array((int) $item->status, [
            AdProductOrderModel::STATUS_WAIT_BUY,
            AdProductOrderModel::STATUS_PURCHASED,
            AdProductOrderModel::STATUS_SETTLED,
        ], true))->count();
    }

    private function resolvePendingOrder(int $levelId, int $userId, string $sessionNo, int $matchedCount, Collection $orders): ?AdProductOrderModel
    {
        $pending = AdProductOrderModel::where('user_id', $userId)
            ->where('level_id', $levelId)
            ->where('session_no', $sessionNo)
            ->where('status', AdProductOrderModel::STATUS_PENDING)
            ->orderBy('sort')
            ->first();

        if ($pending) {
            return $pending;
        }

        return AdProductOrderModel::where('user_id', $userId)
            ->where('level_id', $levelId)
            ->where('session_no', $sessionNo)
            ->where('sort', $matchedCount + 1)
            ->first();
    }

    private function isNeedToast(Collection $orders, UserModel $user, int $levelId): bool
    {
        $pending = $orders->firstWhere('status', AdProductOrderModel::STATUS_PENDING);
        if (!$pending) {
            return true;
        }
        if (bccomp((string) $user->balance, (string) $pending->invest_amount, 4) >= 0) {
            return true;
        }
        $overdue = AdProductOrderModel::where('user_id', $user->id)
            ->where('level_id', $levelId)
            ->where('session_no', $pending->session_no)
            ->where('match_type', self::MATCH_TYPE_OVERDUE)
            ->exists();
        return !$overdue;
    }

    private function upgradeUserLevel(UserModel $user, int $levelId): void
    {
        $this->vipUpgradeService->upgradeBeforeMatch((int) $user->id, $levelId);
    }

    private function calcOverdueMoney(float $balance, int $creditScore): string
    {
        $config = (string) $this->settingService->getValue('credit_score_times', '1:0-100');
        $times = 1.0;
        foreach (explode('_', $config) as $item) {
            [$multiplier, $range] = array_pad(explode(':', $item), 2, '');
            [$min, $max] = array_pad(explode('-', $range), 2, 0);
            if ($creditScore >= (int) $min && $creditScore <= (int) $max) {
                $times += (float) $multiplier;
                break;
            }
        }
        return (string) ($balance - $times * $balance);
    }

    /** @return array<int, float> */
    private function distributeNumber(float $total, int $parts): array
    {
        if ($parts <= 1) {
            return [round($total, 4)];
        }
        $base = $total / $parts;
        $values = [];
        $sum = 0.0;
        for ($i = 0; $i < $parts; $i++) {
            $part = $base * (mt_rand(60, 140) / 100);
            $values[] = $part;
            $sum += $part;
        }
        $factor = $total / max($sum, 0.0001);
        return array_map(fn ($v) => round($v * $factor, 4), $values);
    }

    private function hasPositiveRatio(UserAdRatioConfigModel $config): bool
    {
        return array_filter(array_map('floatval', (array) $config->ratio), fn ($v) => $v > 0) !== [];
    }

    private function generateSessionNo(int $userId): string
    {
        return date('ymdHis') . $userId . random_int(100, 999);
    }
}
