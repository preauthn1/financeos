<?php

declare(strict_types=1);

namespace app\service;

use app\model\ad\AdProductOrderModel;
use app\model\invest\InvestmentProductOrderModel;
use app\model\member\UserModel;
use app\model\member\UserWaterModel;
use app\model\pay\RechargeOrderModel;
use app\model\pay\WithdrawOrderModel;
use Illuminate\Database\Eloquent\Builder;

/**
 * 团队下级汇总统计（仿 reference team/next_index 顶部统计表）
 */
class TeamSubordinateStatsService
{
    /**
     * @param list<int> $teamUserIds
     * @return array<string, float|int|string>
     */
    public function buildSummary(array $teamUserIds): array
    {
        $teamCount = count($teamUserIds);
        if ($teamUserIds === []) {
            return $this->emptySummary();
        }

        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');
        $todayUnixStart = strtotime($todayStart);
        $todayUnixEnd = strtotime($todayEnd);
        $nowUnix = time();

        $rechargeSuccess = RechargeOrderModel::STATUS_SUCCESS;
        $withdrawSuccess = WithdrawOrderModel::STATUS_SUCCESS;

        $actualRechargeTotal = (float) RechargeOrderModel::query()
            ->whereIn('user_id', $teamUserIds)
            ->where('status', $rechargeSuccess)
            ->where('is_virtual', 0)
            ->sum('money');

        $thirdWithdrawalTotal = (float) WithdrawOrderModel::query()
            ->whereIn('user_id', $teamUserIds)
            ->where('status', $withdrawSuccess)
            ->where('is_virtual', 0)
            ->sum('original_amount');

        $totalTaskRebate = (float) UserWaterModel::query()
            ->whereIn('user_id', $teamUserIds)
            ->where('direction', 1)
            ->whereIn('detail_type', [24, 25])
            ->sum('amount');

        $todayRechargeQuery = RechargeOrderModel::query()
            ->whereIn('user_id', $teamUserIds)
            ->where('status', $rechargeSuccess)
            ->where('is_virtual', 0)
            ->whereBetween('created_at', [$todayStart, $todayEnd]);

        $todayRechargeAmount = (float) (clone $todayRechargeQuery)->sum('money');
        $todayRechargeCount = (int) (clone $todayRechargeQuery)->distinct()->count('user_id');

        $todayRepeatRechargeCount = (int) RechargeOrderModel::query()
            ->whereIn('user_id', $teamUserIds)
            ->where('status', $rechargeSuccess)
            ->where('is_virtual', 0)
            ->where('is_first', 0)
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->distinct()
            ->count('user_id');

        $todayTeamMoney = (float) UserModel::query()
            ->whereIn('id', $teamUserIds)
            ->where('is_virtual', 0)
            ->selectRaw('COALESCE(SUM(balance),0) + COALESCE(SUM(brokerage_balance),0) as total')
            ->value('total');

        $todayFirstRechargeMoney = (float) RechargeOrderModel::query()
            ->whereIn('user_id', $teamUserIds)
            ->where('status', $rechargeSuccess)
            ->where('is_virtual', 0)
            ->where('is_first', 1)
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->sum('money');

        $todayRegisterAndRechargeCount = (int) RechargeOrderModel::query()
            ->whereIn('user_id', $teamUserIds)
            ->where('status', $rechargeSuccess)
            ->where('is_virtual', 0)
            ->where('is_first', 1)
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->whereHas('user', static function (Builder $query) use ($todayUnixStart, $todayUnixEnd, $todayStart, $todayEnd) {
                $query->where(function (Builder $inner) use ($todayUnixStart, $todayUnixEnd, $todayStart, $todayEnd) {
                    $inner->whereBetween('registered_at', [$todayUnixStart, $todayUnixEnd])
                        ->orWhereBetween('created_at', [$todayStart, $todayEnd]);
                });
            })
            ->distinct()
            ->count('user_id');

        $todayWithdrawQuery = WithdrawOrderModel::query()
            ->whereIn('user_id', $teamUserIds)
            ->where('status', $withdrawSuccess)
            ->where('is_virtual', 0)
            ->whereBetween('created_at', [$todayStart, $todayEnd]);

        $todayWithdrawAmount = (float) (clone $todayWithdrawQuery)->sum('original_amount');
        $todayWithdrawCount = (int) (clone $todayWithdrawQuery)->distinct()->count('user_id');

        $todayThirdWithdrawalSuccessMoney = (float) WithdrawOrderModel::query()
            ->whereIn('user_id', $teamUserIds)
            ->where('status', $withdrawSuccess)
            ->where('is_virtual', 0)
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->sum('original_amount');

        $todayRegisterCount = (int) UserModel::query()
            ->whereIn('id', $teamUserIds)
            ->where('is_virtual', 0)
            ->where(function (Builder $query) use ($todayUnixStart, $todayUnixEnd, $todayStart, $todayEnd) {
                $query->whereBetween('registered_at', [$todayUnixStart, $todayUnixEnd])
                    ->orWhereBetween('created_at', [$todayStart, $todayEnd]);
            })
            ->count();

        $activeInvestmentUserIds = InvestmentProductOrderModel::query()
            ->whereIn('user_id', $teamUserIds)
            ->where('status', InvestmentProductOrderModel::STATUS_BOUGHT)
            ->where('ended_at', '>', $nowUnix)
            ->distinct()
            ->pluck('user_id')
            ->all();

        $activeAdUserIds = AdProductOrderModel::query()
            ->whereIn('user_id', $teamUserIds)
            ->whereIn('status', [AdProductOrderModel::STATUS_BOUGHT, AdProductOrderModel::STATUS_SETTLED])
            ->whereBetween('bought_at', [$todayUnixStart, $todayUnixEnd])
            ->distinct()
            ->pluck('user_id')
            ->all();

        $activeUserCount = count(array_unique(array_merge($activeInvestmentUserIds, $activeAdUserIds)));

        $todayTaskUserCount = (int) AdProductOrderModel::query()
            ->whereIn('user_id', $teamUserIds)
            ->whereIn('status', [AdProductOrderModel::STATUS_BOUGHT, AdProductOrderModel::STATUS_SETTLED])
            ->whereBetween('bought_at', [$todayUnixStart, $todayUnixEnd])
            ->distinct()
            ->count('user_id');

        $adFreezeMoney = (float) AdProductOrderModel::query()
            ->whereIn('user_id', $teamUserIds)
            ->where('status', AdProductOrderModel::STATUS_BOUGHT)
            ->sum('price');

        $aiFreezeMoney = (float) InvestmentProductOrderModel::query()
            ->whereIn('user_id', $teamUserIds)
            ->where('status', InvestmentProductOrderModel::STATUS_BOUGHT)
            ->sum('money');

        $freezeMoney = $adFreezeMoney + $aiFreezeMoney;

        $todayTaskRebate = (float) UserWaterModel::query()
            ->whereIn('user_id', $teamUserIds)
            ->where('direction', 1)
            ->whereIn('detail_type', [24, 25])
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->sum('amount');

        $teamAmount = $todayTeamMoney + $freezeMoney;

        return [
            'team_count'                              => $teamCount,
            'actual_recharge_total'                   => $this->money($actualRechargeTotal),
            'third_withdrawal_total'                  => $this->money($thirdWithdrawalTotal),
            'total_task_rebate'                       => $this->money($totalTaskRebate),
            'today_recharge_amount'                   => $this->money($todayRechargeAmount),
            'today_recharge_count'                    => $todayRechargeCount,
            'today_repeat_recharge_count'             => $todayRepeatRechargeCount,
            'today_team_money'                        => $this->money($todayTeamMoney),
            'today_first_recharge_money'              => $this->money($todayFirstRechargeMoney),
            'today_register_and_recharge_count'       => $todayRegisterAndRechargeCount,
            'today_withdraw_amount'                   => $this->money($todayWithdrawAmount),
            'today_withdraw_count'                    => $todayWithdrawCount,
            'today_third_withdrawal_success_money'    => $this->money($todayThirdWithdrawalSuccessMoney),
            'today_register_count'                    => $todayRegisterCount,
            'active_user_count'                       => $activeUserCount,
            'today_task_user_count'                   => $todayTaskUserCount,
            'freeze_money'                            => $this->money($freezeMoney),
            'today_task_rebate'                       => $this->money($todayTaskRebate),
            'team_amount'                             => $this->money($teamAmount),
            // 兼容旧字段
            'team_total_recharge'                     => $this->money($actualRechargeTotal),
            'team_total_withdraw'                     => $this->money($thirdWithdrawalTotal),
            'team_balance'                            => $this->money($todayTeamMoney),
        ];
    }

    /**
     * @return array<string, float|int|string>
     */
    private function emptySummary(): array
    {
        return [
            'team_count'                              => 0,
            'actual_recharge_total'                   => '0.00',
            'third_withdrawal_total'                  => '0.00',
            'total_task_rebate'                       => '0.00',
            'today_recharge_amount'                   => '0.00',
            'today_recharge_count'                    => 0,
            'today_repeat_recharge_count'             => 0,
            'today_team_money'                        => '0.00',
            'today_first_recharge_money'              => '0.00',
            'today_register_and_recharge_count'       => 0,
            'today_withdraw_amount'                   => '0.00',
            'today_withdraw_count'                    => 0,
            'today_third_withdrawal_success_money'    => '0.00',
            'today_register_count'                    => 0,
            'active_user_count'                       => 0,
            'today_task_user_count'                   => 0,
            'freeze_money'                            => '0.00',
            'today_task_rebate'                       => '0.00',
            'team_amount'                             => '0.00',
            'team_total_recharge'                     => '0.00',
            'team_total_withdraw'                     => '0.00',
            'team_balance'                            => '0.00',
        ];
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
