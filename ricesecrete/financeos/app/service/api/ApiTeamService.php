<?php

declare(strict_types=1);

namespace app\service\api;

use app\exception\BusinessException;
use app\model\ad\AdProductOrderModel;
use app\model\member\UserModel;
use app\model\member\UserVirtualModel;
use app\model\member\UserWaterModel;
use app\service\SettingService;
use app\support\DateTimeFormat;
use app\support\ErrorCode;
use Illuminate\Database\Eloquent\Builder;

class ApiTeamService
{
    private const DEFAULT_START_DATE = '2024-10-10';

    public function __construct(private ?SettingService $settingService = null)
    {
        $this->settingService = $settingService ?? new SettingService();
    }

    /**
     * 团队页 /upgrade 所需数据结构（兼容旧版 team_info）
     *
     * @param array{start_date?: string, end_date?: string} $params
     */
    public function teamInfo(int $userId, array $params = []): array
    {
        $user = UserModel::find($userId);
        if (!$user) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '用户不存在');
        }

        if ((int) $user->is_virtual === 1) {
            $virtual = UserVirtualModel::where('user_id', $userId)->first();
            if ($virtual) {
                return $this->buildVirtualTeamInfo($virtual);
            }
        }

        $timeRange = $this->resolveTimeRange($user, $params);
        $validRecharge = (float) $this->settingService->getValue('recharge_num_valid_user', 0);

        $teamAllIds = $this->getTeamAllMemberIds($userId);

        $teamOneIds = $this->getTeamLevelIds($userId, 1, $timeRange);
        $teamTwoIds = $this->getTeamLevelIds($userId, 2, $timeRange);
        $teamThreeIds = $this->getTeamLevelIds($userId, 3, $timeRange);

        $teamTwoOnlyIds = array_values(array_diff($teamTwoIds, $teamOneIds));
        $teamThreeOnlyIds = array_values(array_diff($teamThreeIds, $teamTwoIds));

        $positiveBalanceFilter = static function (Builder $query): Builder {
            return $query->where('balance', '>', 0);
        };

        $teamOneRechargeSum = $this->sumUserField($teamOneIds, 'total_recharge', $positiveBalanceFilter);
        $teamOneBalanceSum = $this->sumUserField($teamOneIds, 'balance', $positiveBalanceFilter);
        $teamTwoRechargeSum = $this->sumUserField($teamTwoOnlyIds, 'total_recharge', $positiveBalanceFilter);
        $teamTwoBalanceSum = $this->sumUserField($teamTwoOnlyIds, 'balance', $positiveBalanceFilter);
        $teamThreeRechargeSum = $this->sumUserField($teamThreeOnlyIds, 'total_recharge', $positiveBalanceFilter);
        $teamThreeBalanceSum = $this->sumUserField($teamThreeOnlyIds, 'balance', $positiveBalanceFilter);

        $teamOneTodayBrokerage = $this->sumWaterAmount([$userId], [9], true);
        $teamTwoTodayBrokerage = $this->sumWaterAmount([$userId], [10], true);
        $teamThreeTodayBrokerage = $this->sumWaterAmount([$userId], [11], true);

        $teamOneTotalBrokerage = $this->sumWaterAmount([$userId], [9], false, $timeRange);
        $teamTwoTotalBrokerage = $this->sumWaterAmount([$userId], [10], false, $timeRange);
        $teamThreeTotalBrokerage = $this->sumWaterAmount([$userId], [11], false, $timeRange);

        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');

        $todayNewRechargeQuery = UserModel::query()
            ->whereIn('id', $teamAllIds ?: [0])
            ->where('total_recharge', '>=', $validRecharge)
            ->where(function (Builder $query) use ($todayStart, $todayEnd) {
                $todayUnixStart = strtotime($todayStart);
                $todayUnixEnd = strtotime($todayEnd);
                $query->whereBetween('registered_at', [$todayUnixStart, $todayUnixEnd])
                    ->orWhereBetween('created_at', [$todayStart, $todayEnd]);
            });

        $todayUserRechargeCount = (clone $todayNewRechargeQuery)->count();
        $todayUserRechargeSum = (float) (clone $todayNewRechargeQuery)->sum('total_recharge');

        $teamIncomeDetailTypes = [3, 4, 5, 6, 9, 10, 11, 14, 15, 16];

        return [
            'team' => [
                'one' => [
                    'count'           => count($teamOneIds),
                    'active_count'    => $this->countTodayFinanceUsers($teamOneIds),
                    'total_recharge'  => $this->formatCurrency($teamOneRechargeSum),
                    'total_brokerage' => $this->formatCurrency($teamOneTotalBrokerage),
                    'today_brokerage' => $this->formatCurrency($teamOneTodayBrokerage),
                    'total_balance'   => $this->formatCurrency($teamOneBalanceSum),
                ],
                'two' => [
                    'count'           => count($teamTwoOnlyIds),
                    'active_count'    => $this->countTodayFinanceUsers($teamTwoOnlyIds),
                    'total_recharge'  => $this->formatCurrency($teamTwoRechargeSum),
                    'total_brokerage' => $this->formatCurrency($teamTwoTotalBrokerage),
                    'today_brokerage' => $this->formatCurrency($teamTwoTodayBrokerage),
                    'total_balance'   => $this->formatCurrency($teamTwoBalanceSum),
                ],
                'three' => [
                    'count'           => count($teamThreeOnlyIds),
                    'active_count'    => $this->countTodayFinanceUsers($teamThreeOnlyIds),
                    'total_recharge'  => $this->formatCurrency($teamThreeRechargeSum),
                    'total_brokerage' => $this->formatCurrency($teamThreeTotalBrokerage),
                    'today_brokerage' => $this->formatCurrency($teamThreeTodayBrokerage),
                    'total_balance'   => $this->formatCurrency($teamThreeBalanceSum),
                ],
                'summary' => [
                    'total_count'               => count($teamAllIds),
                    'today_user_recharge_sum'   => $this->formatCurrency($todayUserRechargeSum),
                    'today_user_recharge_count' => $todayUserRechargeCount,
                ],
            ],
            'team_statistics' => [
                'total_invest_income'    => $this->formatAmount(
                    $this->sumWaterAmount([$userId], [16], false, $timeRange)
                ),
                'total_ai_income'        => $this->formatAmount(
                    $this->sumWaterAmount([$userId], [14], false, $timeRange)
                ),
                'total_brokerage_income' => $this->formatAmount(
                    $this->sumWaterAmount([$userId], [4, 9, 10, 11], false, $timeRange)
                ),
                'total_invite_income'    => $this->formatAmount(
                    $this->sumWaterAmount([$userId], [5, 6, 8, 15, 19], false, $timeRange)
                ),
            ],
            'user' => [
                'today_income' => $this->formatCurrency(
                    $teamAllIds
                        ? $this->sumWaterAmount($teamAllIds, $teamIncomeDetailTypes, true)
                        : 0
                ),
                'total_income' => $this->formatCurrency(
                    $teamAllIds
                        ? $this->sumWaterAmount($teamAllIds, $teamIncomeDetailTypes, false, $timeRange)
                        : 0
                ),
            ],
        ];
    }

    public function detail(int $userId, int $level): array
    {
        $level = max(1, min(7, $level));
        $field = 'leader' . $level;

        $users = UserModel::query()
            ->where($field, $userId)
            ->orderByDesc('id')
            ->limit(200)
            ->get(['id', 'username', 'mobile', 'avatar', 'created_at', 'registered_at']);

        if ($users->isEmpty()) {
            return [];
        }

        $ids = $users->pluck('id')->map(static fn ($id) => (int) $id)->all();
        $oneCounts = UserModel::query()
            ->whereIn('leader1', $ids)
            ->selectRaw('leader1, COUNT(*) as cnt')
            ->groupBy('leader1')
            ->pluck('cnt', 'leader1')
            ->all();

        $list = [];
        foreach ($users as $user) {
            $name = (string) ($user->username ?: $user->mobile);
            $createAt = (int) ($user->registered_at ?: 0) > 0
                ? $user->registered_at
                : $user->created_at;

            $list[] = [
                'id' => (int) $user->id,
                'user_name' => $this->maskUserName($name),
                'avatar' => (string) ($user->avatar ?? ''),
                'one_count' => (int) ($oneCounts[(int) $user->id] ?? 0),
                'create_time' => DateTimeFormat::datetime($createAt, 'Y/m/d H:i:s'),
            ];
        }

        return $list;
    }

    private function maskUserName(string $name): string
    {
        $name = trim($name);
        $len = mb_strlen($name);
        if ($len <= 2) {
            return $name;
        }
        if ($len <= 4) {
            return mb_substr($name, 0, 1) . str_repeat('*', $len - 1);
        }

        return mb_substr($name, 0, 3) . '****' . mb_substr($name, -2);
    }

    /**
     * 获取全部下级成员 ID（优先 tree_id，异常时回退 leader 链）
     *
     * @return list<int>
     */
    private function getTeamAllMemberIds(int $userId): array
    {
        $treeIds = UserModel::query()
            ->where('tree_id', 'like', "%/{$userId}/%")
            ->where('tree_id', 'not like', '%/' . $userId . '/')
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        if ($treeIds !== []) {
            return $treeIds;
        }

        return UserModel::query()
            ->where(function (Builder $query) use ($userId) {
                for ($i = 1; $i <= 7; $i++) {
                    $query->orWhere("leader{$i}", $userId);
                }
            })
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param array{start_time: int, end_time: int}|null $timeRange
     * @return list<int>
     */
    private function getTeamLevelIds(int $userId, int $level, ?array $timeRange): array
    {
        $ids = [];
        for ($i = 1; $i <= $level; $i++) {
            $query = UserModel::query()->where("leader{$i}", $userId);
            if ($timeRange !== null) {
                $start = date('Y-m-d H:i:s', $timeRange['start_time']);
                $end = date('Y-m-d H:i:s', $timeRange['end_time']);
                $query->where(function (Builder $inner) use ($start, $end, $timeRange) {
                    $inner->whereBetween('created_at', [$start, $end])
                        ->orWhereBetween('registered_at', [$timeRange['start_time'], $timeRange['end_time']]);
                });
            }
            $ids = array_merge($ids, $query->pluck('id')->map(static fn ($id) => (int) $id)->all());
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param list<int> $userIds
     */
    private function countTodayFinanceUsers(array $userIds): int
    {
        if ($userIds === []) {
            return 0;
        }

        $todayUnixStart = strtotime(date('Y-m-d 00:00:00'));
        $todayUnixEnd = strtotime(date('Y-m-d 23:59:59'));

        return (int) AdProductOrderModel::query()
            ->whereIn('user_id', $userIds)
            ->whereIn('status', [AdProductOrderModel::STATUS_BOUGHT, AdProductOrderModel::STATUS_SETTLED])
            ->where('level_id', '>', 0)
            ->whereBetween('bought_at', [$todayUnixStart, $todayUnixEnd])
            ->distinct()
            ->count('user_id');
    }

    /**
     * @param list<int> $userIds
     * @param list<int> $detailTypes
     * @param array{start_time: int, end_time: int}|null $timeRange
     */
    private function sumWaterAmount(
        array $userIds,
        array $detailTypes,
        bool $todayOnly = false,
        ?array $timeRange = null
    ): float {
        if ($userIds === [] || $detailTypes === []) {
            return 0.0;
        }

        $query = UserWaterModel::query()
            ->whereIn('user_id', $userIds)
            ->where('status', 1)
            ->where('direction', 1)
            ->whereIn('detail_type', $detailTypes);

        if ($todayOnly) {
            $query->whereBetween('created_at', [date('Y-m-d 00:00:00'), date('Y-m-d 23:59:59')]);
        } elseif ($timeRange !== null) {
            $query->whereBetween('created_at', [
                date('Y-m-d H:i:s', $timeRange['start_time']),
                date('Y-m-d H:i:s', $timeRange['end_time']),
            ]);
        }

        return (float) $query->sum('amount');
    }

    /**
     * @param list<int> $userIds
     * @param callable(Builder): Builder|null $filter
     */
    private function sumUserField(array $userIds, string $field, ?callable $filter = null): float
    {
        if ($userIds === []) {
            return 0.0;
        }

        $query = UserModel::query()->whereIn('id', $userIds);
        if ($filter !== null) {
            $query = $filter($query);
        }

        return (float) $query->sum($field);
    }

    /**
     * @param array{start_date?: string, end_date?: string} $params
     * @return array{start_time: int, end_time: int}|null
     */
    private function resolveTimeRange(UserModel $user, array $params): ?array
    {
        $startDate = trim((string) ($params['start_date'] ?? ''));
        $endDate = trim((string) ($params['end_date'] ?? ''));

        if ($startDate !== '' && $endDate !== '') {
            return [
                'start_time' => strtotime($startDate . ' 00:00:00') ?: 0,
                'end_time'   => strtotime($endDate . ' 23:59:59') ?: time(),
            ];
        }

        if ((int) $user->team_monthly_reset === 1) {
            return [
                'start_time' => (int) strtotime(date('Y-m-01 00:00:00')),
                'end_time'   => (int) strtotime(date('Y-m-t 23:59:59')),
            ];
        }

        return [
            'start_time' => (int) strtotime(self::DEFAULT_START_DATE . ' 00:00:00'),
            'end_time'   => time(),
        ];
    }

    private function buildVirtualTeamInfo(UserVirtualModel $virtual): array
    {
        return [
            'team' => [
                'one' => [
                    'count'           => (int) $virtual->level1_count,
                    'active_count'    => (int) $virtual->level1_active,
                    'total_recharge'  => $this->formatCurrency((float) $virtual->level1_recharge),
                    'total_brokerage' => $this->formatCurrency((float) $virtual->level1_brokerage),
                    'today_brokerage' => $this->formatCurrency((float) $virtual->level1_brokerage),
                    'total_balance'   => $this->formatCurrency((float) $virtual->level1_recharge),
                ],
                'two' => [
                    'count'           => (int) $virtual->level2_count,
                    'active_count'    => (int) $virtual->level2_active,
                    'total_recharge'  => $this->formatCurrency((float) $virtual->level2_recharge),
                    'total_brokerage' => $this->formatCurrency((float) $virtual->level2_brokerage),
                    'today_brokerage' => $this->formatCurrency((float) $virtual->level2_brokerage),
                    'total_balance'   => $this->formatCurrency((float) $virtual->level2_recharge),
                ],
                'three' => [
                    'count'           => (int) $virtual->level3_count,
                    'active_count'    => (int) $virtual->level3_active,
                    'total_recharge'  => $this->formatCurrency((float) $virtual->level3_recharge),
                    'total_brokerage' => $this->formatCurrency((float) $virtual->level3_brokerage),
                    'today_brokerage' => $this->formatCurrency((float) $virtual->level3_brokerage),
                    'total_balance'   => $this->formatCurrency((float) $virtual->level3_recharge),
                ],
                'summary' => [
                    'total_count'               => (int) $virtual->team_total,
                    'today_user_recharge_sum'   => $this->formatCurrency((float) $virtual->team_total_recharge),
                    'today_user_recharge_count' => (int) $virtual->team_today_new,
                ],
            ],
            'team_statistics' => [
                'total_invest_income'    => $this->formatAmount((float) $virtual->total_income),
                'total_ai_income'        => $this->formatAmount((float) $virtual->total_income),
                'total_brokerage_income' => $this->formatAmount((float) $virtual->total_income),
                'total_invite_income'    => $this->formatAmount((float) $virtual->total_income),
            ],
            'user' => [
                'today_income' => $this->formatCurrency((float) $virtual->today_income),
                'total_income' => $this->formatCurrency((float) $virtual->total_income),
            ],
        ];
    }

    private function formatCurrency(float $amount): string
    {
        $site = $this->settingService->getValue('site_config', []);
        $symbol = is_array($site) ? (string) ($site['fiat_currency_symbol'] ?? '$') : '$';
        if ($symbol === '') {
            $symbol = '$';
        }

        return $symbol . number_format($amount, 2, '.', ',');
    }

    private function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
