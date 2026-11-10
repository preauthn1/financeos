<?php

declare(strict_types=1);

namespace app\service\api;

use app\exception\BusinessException;
use app\model\member\TaskSummaryV2Model;
use app\model\member\TeamActiveRewardV2Model;
use app\model\member\UserModel;
use app\model\ops\TeamOperationCenterIssueModel;
use app\model\ops\TeamOperationCenterModel;
use app\service\MemberWalletService;
use app\service\SettingService;
use app\support\ErrorCode;
use Illuminate\Database\Capsule\Manager as DB;

class ApiTeamActiveRewardService
{
    public function __construct(
        private ?SettingService $settingService = null,
        private ?MemberWalletService $walletService = null,
    ) {
        $this->settingService = $settingService ?? new SettingService();
        $this->walletService = $walletService ?? new MemberWalletService();
    }

    public function indexV2(int $userId): array
    {
        $rawConfig1 = $this->settingService->getValue('team_reward_config_v1', []);
        $rawConfig2 = $this->settingService->getValue('team_reward_config_v2', []);
        $config1 = $this->resolveConfig1($rawConfig1);
        $config2Groups = $this->normalizeConfig2Groups($rawConfig2);

        $receiveCount = TeamActiveRewardV2Model::where('user_id', $userId)->where('status', 1)
            ->where('reward_level', 1)->count();

        $summary = TaskSummaryV2Model::where('user_id', $userId)->where('date', date('Ymd'))->first();
        $activeNum = $summary ? (int) $summary->task_level1 + (int) $summary->task_level2 : 0;
        $directNum = UserModel::where('leader1', $userId)->where('total_recharge', '>=', $config1['level1_recharge_money'] ?? 0)->count();

        $lastReward = TeamActiveRewardV2Model::where('user_id', $userId)->where('reward_level', 1)
            ->orderByDesc('id')->first();

        $pendingReward = TeamActiveRewardV2Model::where('user_id', $userId)
            ->where('reward_level', 1)
            ->where('status', 0)
            ->orderBy('id')
            ->first();
        if (!$pendingReward) {
            $pendingReward = $lastReward;
        }

        $phase = (int) ($pendingReward?->phase ?? 1);
        $amount = $this->resolveRewardAmount($config1, $phase, $rawConfig1);
        $config = [
            'phase'               => $phase,
            'direct_referral_num' => (int) ($pendingReward?->trigger_level1_num ?? $config1['base_level1_user'] ?? 3),
            'active_num'          => (int) ($pendingReward?->trigger_active_num ?? $config1['base_active_user'] ?? 5),
            'amount'              => $amount,
            'recharge_money'      => $config1['level1_recharge_money'] ?? 100,
        ];

        $initialTeam = [
            'is_achieve'          => $pendingReward && (int) $pendingReward->status === 0,
            'active_num'          => $activeNum,
            'direct_referral_num' => $directNum,
            'team_active_reward'  => $pendingReward && (int) $pendingReward->status === 0 ? $pendingReward->toArray() : [],
            'status'              => $pendingReward ? (int) $pendingReward->status : -1,
            'config'              => $config,
            'receive_count'       => $receiveCount,
            'total_phase'         => (int) ($config1['phase_count'] ?? 10),
        ];

        $growthTeam = $this->buildGrowthTeam($userId, $config1, $config2Groups, $activeNum, $directNum);
        $configList = $this->buildConfigList($userId, $config2Groups, $growthTeam);

        $issue = date('Ym');

        return [
            'initialTeam'            => $initialTeam,
            'growthTeam'             => $growthTeam,
            'configList'             => $configList,
            'config'                 => $this->settingService->getValue('activity_start_time', ''),
            'proxyOperationCenter'   => $this->formatOperationCenter($userId, TeamOperationCenterModel::where('user_id', $userId)->where('type', 1)->first(), $issue),
            'groupOperationCenter'   => $this->formatOperationCenter($userId, TeamOperationCenterModel::where('user_id', $userId)->where('type', 2)->first(), $issue),
            'cityOperationCenter'    => $this->formatOperationCenter($userId, TeamOperationCenterModel::where('user_id', $userId)->where('type', 3)->first(), $issue),
            // 兼容旧字段
            'type_one'               => $initialTeam,
            'type_two'               => $growthTeam,
            'proxy_operation_center' => [],
            'group_operation_center' => [],
            'city_operation_center'  => [],
        ];
    }

    /**
     * @param mixed $raw
     * @return array<string, mixed>
     */
    private function resolveConfig1(mixed $raw): array
    {
        $defaults = [
            'phase_count'          => 10,
            'reward_amount'        => 100,
            'reward_amount_step'   => '10',
            'base_level1_user'     => 3,
            'base_active_user'     => 5,
            'level1_user_up_step'  => 1,
            'active_user_up_step'  => 2,
            'level1_recharge_money'=> 100,
        ];

        if (!is_array($raw) || $raw === []) {
            return $defaults;
        }

        if (isset($raw['phase_count']) || isset($raw['base_level1_user']) || isset($raw['reward_amount'])) {
            return array_merge($defaults, $raw);
        }

        $first = is_array($raw[0] ?? null) ? $raw[0] : [];
        return array_merge($defaults, [
            'phase_count'           => count($raw),
            'base_level1_user'      => (int) ($first['direct_referral_num'] ?? 3),
            'base_active_user'      => (int) ($first['active_num'] ?? 5),
            'level1_recharge_money' => (float) ($first['recharge_money'] ?? 100),
            'reward_amount'         => (float) ($first['reward'] ?? $first['amount'] ?? 100),
            'reward_amount_step'    => '0',
        ]);
    }

    /**
     * 兼容后台嵌套配置与旧库 flat 导入格式
     *
     * @return list<array{name: string, step_length: int, step: list<array<string, mixed>>}>
     */
    private function normalizeConfig2Groups(mixed $raw): array
    {
        if (!is_array($raw) || $raw === []) {
            return [];
        }

        if (isset($raw[0]['step']) && is_array($raw[0]['step'])) {
            $groups = [];
            foreach ($raw as $key => $item) {
                if (!is_array($item)) {
                    continue;
                }
                $steps = [];
                foreach ($item['step'] as $stepIndex => $step) {
                    if (is_array($step)) {
                        $steps[] = $this->normalizeStep($step, (int) ($item['index'] ?? $key), (int) $stepIndex);
                    }
                }
                $groups[] = [
                    'name'        => (string) ($item['name'] ?? ('成长团队活跃奖' . ($key + 1))),
                    'step_length' => (int) ($item['step_length'] ?? count($steps) ?: 1),
                    'step'        => $steps,
                ];
            }

            return $groups;
        }

        $groups = [];
        foreach ($raw as $key => $row) {
            if (!is_array($row)) {
                continue;
            }
            $groups[] = [
                'name'        => (string) ($row['name'] ?? ('成长团队活跃奖' . ($key + 1))),
                'step_length' => 1,
                'step'        => [$this->normalizeStep($row, $key)],
            ];
        }

        return $groups;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeStep(array $row, int $groupIndex, int $stepIndex = 0): array
    {
        return [
            'phase'               => (int) ($row['phase'] ?? ($stepIndex + 1)),
            'direct_referral_num' => (int) ($row['direct_referral_num'] ?? 0),
            'active_num'          => (int) ($row['active_num'] ?? 0),
            'amount'              => (float) ($row['amount'] ?? $row['reward'] ?? 0),
            'recharge_money'      => (float) ($row['recharge_money'] ?? 0),
            'index'               => (int) ($row['index'] ?? $groupIndex),
        ];
    }

    /**
     * @param array<string, mixed> $config1
     * @param list<array{name: string, step_length: int, step: list<array<string, mixed>>}> $config2Groups
     * @return array<string, mixed>
     */
    private function buildGrowthTeam(int $userId, array $config1, array $config2Groups, int $activeNum, int $directNum): array
    {
        $type1Done = TeamActiveRewardV2Model::where('user_id', $userId)
            ->where('reward_level', 1)
            ->where('status', 1)
            ->count() >= (int) ($config1['phase_count'] ?? 10);

        $pendingGrowth = TeamActiveRewardV2Model::where('user_id', $userId)
            ->where('reward_level', 2)
            ->where('status', 0)
            ->orderBy('id')
            ->first();

        $currentIndex = $pendingGrowth
            ? (int) ($pendingGrowth->index ?? 0)
            : (int) TeamActiveRewardV2Model::where('user_id', $userId)
                ->where('reward_level', 2)
                ->where('status', 1)
                ->count();

        if ($config2Groups !== [] && $currentIndex >= count($config2Groups)) {
            $currentIndex = max(0, count($config2Groups) - 1);
        }

        $growthConfig = $this->normalizeStep([], 0);
        if ($config2Groups !== []) {
            $group = $config2Groups[$currentIndex] ?? $config2Groups[0];
            $step = $group['step'][0] ?? $this->normalizeStep([], $currentIndex);
            $growthConfig = array_merge($step, ['index' => $currentIndex]);
        }

        return [
            'active_num'          => $type1Done ? $activeNum : 0,
            'direct_referral_num' => $type1Done ? $directNum : 0,
            'is_achieve'          => $pendingGrowth !== null,
            'status'              => $pendingGrowth ? (int) $pendingGrowth->status : -1,
            'config'              => $growthConfig,
            'team_active_reward'  => $pendingGrowth ? [$pendingGrowth->toArray()] : [],
        ];
    }

    /**
     * @param list<array{name: string, step_length: int, step: list<array<string, mixed>>}> $config2Groups
     * @param array<string, mixed> $growthTeam
     * @return list<array<string, mixed>>
     */
    private function buildConfigList(int $userId, array $config2Groups, array $growthTeam): array
    {
        if ($config2Groups === []) {
            return [];
        }

        $currentIndex = (int) ($growthTeam['config']['index'] ?? 0);
        $list = [];

        foreach ($config2Groups as $key => $item) {
            $steps = $item['step'] ?? [];
            if ($currentIndex > $key) {
                $step = $steps[array_key_last($steps)] ?? $this->normalizeStep([], $key);
            } elseif ($currentIndex === $key) {
                $step = $growthTeam['config'] ?? ($steps[0] ?? $this->normalizeStep([], $key));
            } else {
                $step = $steps[0] ?? $this->normalizeStep([], $key);
            }

            $pendingRewards = TeamActiveRewardV2Model::where('user_id', $userId)
                ->where('reward_level', 2)
                ->where('status', 0)
                ->where('index', $key)
                ->get()
                ->map(static fn (TeamActiveRewardV2Model $row) => $row->toArray())
                ->all();

            $list[] = [
                'name'               => (string) ($item['name'] ?? ('成长团队活跃奖' . ($key + 1))),
                'step'               => $this->normalizeStep(is_array($step) ? $step : [], $key),
                'team_active_reward' => $pendingRewards,
                'receive_count'      => TeamActiveRewardV2Model::where('user_id', $userId)
                    ->where('reward_level', 2)
                    ->where('status', 1)
                    ->where('index', $key)
                    ->count(),
                'total_phase'        => (int) ($item['step_length'] ?? count($steps) ?: 1),
            ];
        }

        return $list;
    }

    /**
     * @param array<string, mixed> $config1
     * @param mixed $rawConfig1
     */
    private function resolveRewardAmount(array $config1, int $phase, mixed $rawConfig1 = null): float
    {
        if (is_array($rawConfig1) && $rawConfig1 !== [] && !isset($rawConfig1['phase_count'])) {
            $phaseRow = $rawConfig1[max(0, $phase - 1)] ?? $rawConfig1[0] ?? [];
            if (is_array($phaseRow)) {
                return (float) ($phaseRow['reward'] ?? $phaseRow['amount'] ?? 0);
            }
        }

        $base = (float) ($config1['reward_amount'] ?? 0);
        $stepRaw = (string) ($config1['reward_amount_step'] ?? '0');
        $phaseCount = max(1, (int) ($config1['phase_count'] ?? 10));

        if (str_contains($stepRaw, '-')) {
            $steps = array_map('floatval', explode('-', $stepRaw));
        } else {
            $stepValue = (float) $stepRaw;
            $steps = array_fill(0, $phaseCount, $stepValue);
        }

        $extra = (float) ($steps[max(0, $phase - 1)] ?? end($steps) ?: 0);

        return $base + $extra;
    }

    public function getRewardV2(int $userId, int $rewardId): void
    {
        DB::transaction(function () use ($userId, $rewardId) {
            $reward = TeamActiveRewardV2Model::where('id', $rewardId)->where('user_id', $userId)
                ->where('status', 0)->lockForUpdate()->first();
            if (!$reward) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '暂无可领取奖励');
            }
            $amount = (string) $reward->reward_amount;
            if (bccomp($amount, '0', 4) > 0) {
                $this->walletService->changeBalance(
                    $userId, $amount, MemberWalletService::WALLET_BASE, MemberWalletService::DIRECTION_IN, '团队活跃奖励', 15
                );
            }
            $reward->update(['status' => 1, 'received_at' => time()]);
        });
    }

    public function addProxyCenterData(int $userId, array $data): array
    {
        return $this->applyOperationCenter($userId, 1, $data);
    }

    public function addGroupCenterData(int $userId, array $data): array
    {
        return $this->applyOperationCenter($userId, 2, $data);
    }

    public function addCityCenterData(int $userId, array $data): array
    {
        return $this->applyOperationCenter($userId, 3, $data);
    }

    public function handleCenterReward(int $userId, int $issueId): void
    {
        $issue = TeamOperationCenterIssueModel::where('id', $issueId)->first();
        if (!$issue) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '期次不存在');
        }
        $center = TeamOperationCenterModel::where('id', $issue->center_id)->where('user_id', $userId)->first();
        if (!$center) {
            throw new BusinessException(ErrorCode::FORBIDDEN, '无权领取');
        }
        $bonus = (string) ($issue->bonus ?? '0');
        if (bccomp($bonus, '0', 4) > 0) {
            $this->walletService->changeBalance(
                $userId, $bonus, MemberWalletService::WALLET_BASE, MemberWalletService::DIRECTION_IN, '运营中心奖励', 15
            );
        }
        $issue->update(['status' => 3]);
    }

    private function applyOperationCenter(int $userId, int $type, array $data): array
    {
        $center = TeamOperationCenterModel::updateOrCreate(
            ['user_id' => $userId, 'type' => $type],
            [
                'submit_user_id' => $userId,
                'auth_code' => (string) ($data['auth_code'] ?? ''),
                'auth_data' => json_encode($data, JSON_UNESCAPED_UNICODE),
                'status' => TeamOperationCenterModel::STATUS_PENDING,
            ]
        );
        return $center->toArray();
    }

    private function formatOperationCenter(int $userId, ?TeamOperationCenterModel $center, string $issue): ?array
    {
        if (!$center) {
            return null;
        }
        $row = $center->toArray();
        $row['statusText'] = match ((int) $center->status) {
            1 => '待认证', 2 => '已认证', 3 => '认证失败', 4 => '资格已失效', default => '待审核',
        };
        $issueModel = TeamOperationCenterIssueModel::where('center_id', $center->id)
            ->where('issue', $issue)->orderByDesc('id')->first();
        if ($issueModel) {
            $row['issueId'] = $issueModel->id;
            $row['dividend'] = $issueModel->dividend;
            $row['rebate'] = $issueModel->rebate;
            $row['fund'] = $issueModel->fund;
            $row['bonus'] = $issueModel->bonus;
            $row['issue'] = $issueModel->issue;
            $row['issueStatus'] = $issueModel->status;
            $row['issueStatusText'] = match ((int) $issueModel->status) {
                1 => '未结算', 2 => '已结算', 3 => '已领取', default => '',
            };
        }
        return $row;
    }
}
