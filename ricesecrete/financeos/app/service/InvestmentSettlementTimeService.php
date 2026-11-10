<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\invest\InvestmentProductSettlementTimeModel;
use app\support\ErrorCode;

/**
 * AI 短周期托管：结算时间点 + 结算周期（小时）计算
 *
 * 对齐旧站 InvestmentProductSettlementTimeRepository
 */
class InvestmentSettlementTimeService
{
    public const CONFIG_CYCLE_KEY = 'investment_product_settlement_cycle';

    public function __construct(private ?SettingService $settingService = null)
    {
        $this->settingService = $settingService ?? new SettingService();
    }

    /**
     * 下一档可结算时间戳
     */
    public function getNextSettlementTimestamp(?int $afterTimestamp = null): int
    {
        $times = $this->enabledSettlementTimes();
        if ($times === []) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '未配置短周期结算时间点');
        }

        $base = $afterTimestamp ?? time();
        $nowTime = date('H:i:s', $base);
        $day = date('Y-m-d', $base);

        foreach ($times as $time) {
            if ($time > $nowTime) {
                $ts = strtotime($day . ' ' . $time);
                if ($ts !== false && $ts > $base) {
                    return $ts;
                }
            }
        }

        $ts = strtotime(date('Y-m-d', strtotime('+1 day', $base)) . ' ' . $times[0]);
        if ($ts === false) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '结算时间点无效');
        }

        return $ts;
    }

    /**
     * @return list<string>
     */
    private function enabledSettlementTimes(): array
    {
        return InvestmentProductSettlementTimeModel::query()
            ->where('status', InvestmentProductSettlementTimeModel::STATUS_ENABLE)
            ->orderBy('settlement_time')
            ->pluck('settlement_time')
            ->map(static fn ($v) => (string) $v)
            ->filter(static fn (string $v) => $v !== '')
            ->values()
            ->all();
    }

    /** 结算周期（小时），如 2 表示每 2 小时一个结算单位 */
    public function getSettlementCycleHours(): float
    {
        $value = $this->settingService->getValue(self::CONFIG_CYCLE_KEY, 2);
        $hours = (float) $value;
        if ($hours <= 0) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '未配置短周期收益结算周期');
        }

        return $hours;
    }

    /**
     * 距结算点可计入的结算次数
     */
    public function getSettlementTimes(int $settlementTimestamp, ?int $fromTimestamp = null): int
    {
        $cycleHours = $this->getSettlementCycleHours();
        $from = $fromTimestamp ?? time();
        $diffHours = max(0, ($settlementTimestamp - $from) / 3600);

        return (int) floor($diffHours / $cycleHours);
    }

    /** 订单展示用投资时长（小时） */
    public function getCycleHours(int $settlementTimes): float
    {
        return $settlementTimes * $this->getSettlementCycleHours();
    }

    public function getProfitAmount(string $money, string|float $profit, int $settlementTimes): string
    {
        $rate = bcdiv((string) $profit, '100', 8);
        $one = bcmul($money, $rate, 8);

        return bcmul($one, (string) $settlementTimes, 8);
    }

    /**
     * 短周期买入参数
     *
     * 若下一档结算点距现在不足 1 个结算周期（times=0），自动顺延到后续结算点，
     * 避免「临近结算点整段无法买入」；仅当后续也找不到可用点时才拒绝。
     *
     * @return array{cycle:float,ended_at:int,settlement_times:int,profit_amount:string}
     */
    public function buildShortTermBuyParams(string $amount, string|float $profit): array
    {
        $timesConfig = $this->enabledSettlementTimes();
        if ($timesConfig === []) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '未配置短周期结算时间点');
        }

        $now = time();
        $cursor = $now;
        // 最多向后扫 2 天内的结算点（点数 × 2）
        $maxTries = max(2, count($timesConfig) * 2);
        $endedAt = 0;
        $settlementTimes = 0;

        for ($i = 0; $i < $maxTries; $i++) {
            $candidate = $this->getNextSettlementTimestamp($cursor);
            $candidateTimes = $this->getSettlementTimes($candidate, $now);
            if ($candidateTimes > 0) {
                $endedAt = $candidate;
                $settlementTimes = $candidateTimes;
                break;
            }
            // 跳过该点，从该点之后继续找
            $cursor = $candidate;
        }

        $cycleHours = $this->getCycleHours($settlementTimes);
        if ($endedAt <= 0 || $settlementTimes <= 0 || $cycleHours <= 0) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '当前处于结算周期阶段，暂不支持转入');
        }

        return [
            'cycle' => $cycleHours,
            'ended_at' => $endedAt,
            'settlement_times' => $settlementTimes,
            'profit_amount' => $this->getProfitAmount($amount, $profit, $settlementTimes),
        ];
    }

    /** @return array{total:int,items:array<int,array>,settlement_cycle:string} */
    public function listTimes(int $page, int $limit): array
    {
        $query = InvestmentProductSettlementTimeModel::query()->orderBy('sort')->orderBy('settlement_time');
        $total = (clone $query)->count();
        $items = $query->offset(($page - 1) * $limit)->limit($limit)->get()->toArray();

        return [
            'total' => $total,
            'items' => $items,
            'settlement_cycle' => (string) $this->settingService->getValue(self::CONFIG_CYCLE_KEY, '2'),
        ];
    }

    public function createTime(array $data): InvestmentProductSettlementTimeModel
    {
        $time = $this->normalizeTime((string) ($data['settlement_time'] ?? ''));
        $exists = InvestmentProductSettlementTimeModel::query()
            ->where('settlement_time', $time)
            ->exists();
        if ($exists) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '该结算时间点已存在，请选择其他时间点');
        }

        return InvestmentProductSettlementTimeModel::create([
            'settlement_time' => $time,
            'status' => (int) ($data['status'] ?? 1),
            'sort' => (int) ($data['sort'] ?? 0),
        ]);
    }

    public function updateTime(int $id, array $data): InvestmentProductSettlementTimeModel
    {
        $row = InvestmentProductSettlementTimeModel::query()->find($id);
        if (!$row) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '结算时间点不存在');
        }
        if (isset($data['settlement_time'])) {
            $data['settlement_time'] = $this->normalizeTime((string) $data['settlement_time']);
            $exists = InvestmentProductSettlementTimeModel::query()
                ->where('settlement_time', $data['settlement_time'])
                ->where('id', '!=', $id)
                ->exists();
            if ($exists) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '该结算时间点已存在，请选择其他时间点');
            }
        }
        $row->fill(array_intersect_key($data, array_flip(['settlement_time', 'status', 'sort'])))->save();

        return $row->fresh();
    }

    public function deleteTime(int $id): void
    {
        $row = InvestmentProductSettlementTimeModel::query()->find($id);
        if (!$row) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '结算时间点不存在');
        }
        $row->delete();
    }

    public function toggleStatus(int $id): InvestmentProductSettlementTimeModel
    {
        $row = InvestmentProductSettlementTimeModel::query()->find($id);
        if (!$row) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '结算时间点不存在');
        }
        $row->status = (int) $row->status === InvestmentProductSettlementTimeModel::STATUS_ENABLE
            ? InvestmentProductSettlementTimeModel::STATUS_DISABLE
            : InvestmentProductSettlementTimeModel::STATUS_ENABLE;
        $row->save();

        return $row->fresh();
    }

    public function saveSettlementCycle(float $hours): string
    {
        if ($hours <= 0) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '结算周期必须大于0小时');
        }
        $value = rtrim(rtrim(number_format($hours, 2, '.', ''), '0'), '.') ?: '0';
        $this->settingService->upsert(self::CONFIG_CYCLE_KEY, $value, 'AI短周期托管收益结算周期(小时)');

        return $value;
    }

    private function normalizeTime(string $time): string
    {
        $time = trim($time);
        if ($time === '') {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '请填写结算时间点');
        }
        if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            $time .= ':00';
        }
        if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '结算时间格式应为 HH:mm:ss');
        }
        $parts = array_map('intval', explode(':', $time));
        if ($parts[0] > 23 || $parts[1] > 59 || $parts[2] > 59) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '结算时间无效');
        }

        return sprintf('%02d:%02d:%02d', $parts[0], $parts[1], $parts[2]);
    }
}
