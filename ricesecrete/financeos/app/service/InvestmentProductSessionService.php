<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\invest\InvestmentProductSessionModel;
use app\support\ErrorCode;

/**
 * AI 托管开放时间段（对齐旧站 InvestmentProductSession）
 */
class InvestmentProductSessionService
{
    /**
     * 当前是否处于可购买开放时段
     */
    public function getCurrentSession(): ?InvestmentProductSessionModel
    {
        $current = date('H:i:s');

        return InvestmentProductSessionModel::query()
            ->where('status', InvestmentProductSessionModel::STATUS_ENABLE)
            ->where('start_time', '<=', $current)
            ->where('end_time', '>=', $current)
            ->orderBy('start_time')
            ->first();
    }

    public function isOpenNow(): bool
    {
        return $this->getCurrentSession() !== null;
    }

    /** 买入前校验：不在开放时段则拒绝 */
    public function assertOpenForBuy(): void
    {
        if (!$this->isOpenNow()) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '目前无法购买');
        }
    }

    /** @return array{total:int,items:array<int,array>} */
    public function list(int $page, int $limit): array
    {
        $query = InvestmentProductSessionModel::query()->orderBy('start_time')->orderBy('sort');
        $total = (clone $query)->count();
        $items = $query->offset(($page - 1) * $limit)->limit($limit)->get()->toArray();

        return ['total' => $total, 'items' => $items];
    }

    public function create(array $data): InvestmentProductSessionModel
    {
        [$start, $end] = $this->normalizeRange($data);

        return InvestmentProductSessionModel::create([
            'start_time' => $start,
            'end_time' => $end,
            'status' => (int) ($data['status'] ?? 1),
            'sort' => (int) ($data['sort'] ?? 0),
        ]);
    }

    public function update(int $id, array $data): InvestmentProductSessionModel
    {
        $row = InvestmentProductSessionModel::query()->find($id);
        if (!$row) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '开放时间段不存在');
        }

        $payload = array_intersect_key($data, array_flip(['start_time', 'end_time', 'status', 'sort']));
        if (isset($payload['start_time']) || isset($payload['end_time'])) {
            [$start, $end] = $this->normalizeRange(array_merge($row->toArray(), $payload));
            $payload['start_time'] = $start;
            $payload['end_time'] = $end;
        }
        if (isset($payload['status'])) {
            $payload['status'] = (int) $payload['status'];
        }
        if (isset($payload['sort'])) {
            $payload['sort'] = (int) $payload['sort'];
        }

        $row->fill($payload)->save();

        return $row->fresh();
    }

    public function toggleStatus(int $id): InvestmentProductSessionModel
    {
        $row = InvestmentProductSessionModel::query()->find($id);
        if (!$row) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '开放时间段不存在');
        }
        $row->status = (int) $row->status === InvestmentProductSessionModel::STATUS_ENABLE
            ? InvestmentProductSessionModel::STATUS_DISABLE
            : InvestmentProductSessionModel::STATUS_ENABLE;
        $row->save();

        return $row->fresh();
    }

    public function delete(int $id): void
    {
        $row = InvestmentProductSessionModel::query()->find($id);
        if (!$row) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '开放时间段不存在');
        }
        $row->delete();
    }

    /**
     * @return array{0:string,1:string}
     */
    private function normalizeRange(array $data): array
    {
        $start = $this->normalizeTime((string) ($data['start_time'] ?? ''));
        $end = $this->normalizeTime((string) ($data['end_time'] ?? ''));
        if ($start === $end) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '开始时间和结束时间不能相同');
        }
        if ($start > $end) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '开始时间不能晚于结束时间');
        }

        return [$start, $end];
    }

    private function normalizeTime(string $time): string
    {
        $time = trim($time);
        if ($time === '') {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '请填写时间');
        }
        if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            $time .= ':00';
        }
        if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '时间格式应为 HH:mm:ss');
        }
        $parts = array_map('intval', explode(':', $time));
        if ($parts[0] > 23 || $parts[1] > 59 || $parts[2] > 59) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '时间无效');
        }

        return sprintf('%02d:%02d:%02d', $parts[0], $parts[1], $parts[2]);
    }
}
