<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\invest\InvestmentSpeedCodeModel;
use app\support\ErrorCode;

class InvestmentSpeedCodeService
{
    public function list(int $page, int $limit, array $filters = []): array
    {
        $query = InvestmentSpeedCodeModel::query();
        if (!empty($filters['keyword'])) {
            $kw = (string) $filters['keyword'];
            $query->where(fn ($q) => $q->where('code', 'like', "%{$kw}%")->orWhere('remark', 'like', "%{$kw}%"));
        }
        if (isset($filters['product_id']) && $filters['product_id'] !== '') {
            $query->where('product_id', (int) $filters['product_id']);
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int) $filters['status']);
        }

        $total = (clone $query)->count();
        $items = $query->orderByDesc('id')->offset(($page - 1) * $limit)->limit($limit)->get();

        return ['total' => $total, 'items' => $items];
    }

    public function create(array $data): InvestmentSpeedCodeModel
    {
        if (trim((string) ($data['code'] ?? '')) === '') {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '请填写加速编码');
        }

        return InvestmentSpeedCodeModel::create([
            'product_id' => (int) ($data['product_id'] ?? 0),
            'code' => (string) $data['code'],
            'max_uses' => (int) ($data['max_uses'] ?? 0),
            'used_count' => 0,
            'status' => (int) ($data['status'] ?? 1),
            'remark' => (string) ($data['remark'] ?? ''),
        ]);
    }

    public function update(int $id, array $data): InvestmentSpeedCodeModel
    {
        $row = InvestmentSpeedCodeModel::find($id);
        if (!$row) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '加速编码不存在');
        }
        $row->fill($data)->save();

        return $row->fresh();
    }

    public function delete(int $id): void
    {
        $row = InvestmentSpeedCodeModel::find($id);
        if (!$row) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '加速编码不存在');
        }
        $row->delete();
    }
}
