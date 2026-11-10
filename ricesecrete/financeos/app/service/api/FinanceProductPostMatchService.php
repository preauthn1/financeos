<?php

declare(strict_types=1);

namespace app\service\api;

use app\model\ad\AdProductOrderModel;
use app\model\activity\PddTurntableCountModel;
use app\model\member\UserBrokeragePoolModel;
use app\model\member\UserModel;
use app\model\member\UserWaterModel;
use app\service\MemberWalletService;

class FinanceProductPostMatchService
{
    public function __construct(
        private ?FinanceProductLevelTeamService $teamService = null,
        private ?FinanceProductLevelConfigService $levelService = null,
        private ?MemberWalletService $walletService = null,
    ) {
        $this->teamService = $teamService ?? new FinanceProductLevelTeamService();
        $this->levelService = $levelService ?? new FinanceProductLevelConfigService();
        $this->walletService = $walletService ?? new MemberWalletService();
    }

    public function onMatchSuccess(array $orderData, UserModel $user, array $level, ?array $taskSession): void
    {
        $orderId = (int) ($orderData['id'] ?? 0);
        $levelId = (int) ($orderData['level_id'] ?? 0);
        $order = AdProductOrderModel::find($orderId);
        if (!$order) {
            return;
        }

        PddTurntableCountModel::addCount((int) $user->id, $order, $taskSession);
        $this->teamService->disableAfterMatch((int) $user->id, $levelId);
        $this->recordTurnoverDeduction($orderData, $user);
        $this->allotBrokerage($user, (float) ($orderData['profit_amount'] ?? 0), $level);
    }

    private function recordTurnoverDeduction(array $orderData, UserModel $user): void
    {
        $amount = (string) ($orderData['money'] ?? '0');
        $balanceBefore = (string) ($orderData['old_user_money'] ?? $user->balance);
        if (bccomp($amount, '0', 4) <= 0) {
            return;
        }

        $deductAmount = $amount;
        if (bccomp($balanceBefore, '0', 4) >= 0 && bccomp($balanceBefore, $amount, 4) < 0) {
            $deductAmount = bcsub($amount, $balanceBefore, 4);
        }

        UserWaterModel::create([
            'user_id' => $user->id,
            'order_id' => (int) ($orderData['id'] ?? 0),
            'title' => '购买广告位减少流水',
            'type' => MemberWalletService::WALLET_BASE,
            'detail_type' => 12,
            'direction' => MemberWalletService::DIRECTION_OUT,
            'amount' => $deductAmount,
            'before_balance' => $balanceBefore,
            'after_balance' => bcsub($balanceBefore, $deductAmount, 4),
            'rate' => 100,
            'remark' => '购买广告位减少流水',
        ]);
    }

    private function allotBrokerage(UserModel $user, float $profitMoney, array $level): void
    {
        if ($profitMoney <= 0) {
            return;
        }

        $rebateRaw = (string) ($level['task_rebate'] ?? '0');
        $rebateRaw = trim($rebateRaw, '[]');
        $rates = array_values(array_filter(array_map('floatval', explode(',', $rebateRaw))));
        if ($rates === []) {
            return;
        }

        $parentIds = [];
        $currentId = (int) $user->id;
        for ($i = 0; $i < count($rates); $i++) {
            $parentId = (int) (UserModel::where('id', $currentId)->value('leader1') ?? 0);
            if ($parentId <= 0) {
                break;
            }
            $parentIds[] = $parentId;
            $currentId = $parentId;
        }

        foreach ($parentIds as $index => $parentId) {
            if (!isset($rates[$index]) || $rates[$index] <= 0) {
                continue;
            }
            $money = bcmul((string) $profitMoney, bcdiv((string) $rates[$index], '100', 4), 4);
            if (bccomp($money, '0', 4) <= 0) {
                continue;
            }

            $pool = UserBrokeragePoolModel::firstOrCreate(
                ['user_id' => $parentId],
                ['receivable' => 0, 'received' => 0]
            );
            $pool->receivable = bcadd((string) $pool->receivable, $money, 4);
            $pool->save();
        }
    }
}
