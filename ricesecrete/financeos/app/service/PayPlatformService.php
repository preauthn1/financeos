<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\pay\PayPlatformModel;
use app\support\ErrorCode;

class PayPlatformService
{
    public function list(int $page, int $limit, array $filters = []): array
    {
        $query = PayPlatformModel::query();

        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int) $filters['status']);
        }
        if (isset($filters['currency_type']) && $filters['currency_type'] !== '') {
            $query->where('currency_type', (int) $filters['currency_type']);
        }
        if (!empty($filters['keyword'])) {
            $kw = (string) $filters['keyword'];
            $query->where(function ($q) use ($kw) {
                $q->where('name', 'like', "%{$kw}%")
                    ->orWhere('show_name', 'like', "%{$kw}%")
                    ->orWhere('coin', 'like', "%{$kw}%");
            });
        }

        $total = (clone $query)->count();
        $items = $query->orderBy('sort')->orderBy('id', 'desc')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return ['total' => $total, 'items' => $items];
    }

    public function options(bool $rechargeOnly = false, bool $withdrawOnly = false): array
    {
        $query = PayPlatformModel::query()->where('status', 1);
        if ($rechargeOnly) {
            $query->where('recharge_is_show', 1);
        }
        if ($withdrawOnly) {
            $query->where('withdraw_is_show', 1);
        }

        return $query->orderBy('sort')->orderBy('id')
            ->get()
            ->map(fn (PayPlatformModel $row) => $this->formatPublicPlatform($row))
            ->values()
            ->all();
    }

    /**
     * H5 充值/提现通道字段（对齐参考 PaymentPlatformTransform）
     *
     * @return array<string, mixed>
     */
    public function formatPublicPlatform(PayPlatformModel $row): array
    {
        $currencyCode = trim((string) ($row->currency_code ?? ''));
        if ($currencyCode === '') {
            $currencyCode = match ((int) $row->currency_type) {
                PayPlatformModel::CURRENCY_BANK => 'Bank',
                PayPlatformModel::CURRENCY_CRYPTO => (string) ($row->coin ?: 'TRC20'),
                default => 'Bank',
            };
        }

        return [
            'id' => (int) $row->id,
            'name' => (string) ($row->show_name ?: $row->name),
            'show_name' => (string) ($row->show_name ?? ''),
            'coin' => (string) ($row->coin ?? ''),
            'currency_type' => (int) $row->currency_type,
            'currency_code' => $currencyCode,
            'unit' => (string) ($row->unit ?? ''),
            'image' => (string) ($row->image_url ?? ''),
            'image_url' => (string) ($row->image_url ?? ''),
            'exchange_rate' => (string) $row->exchange_rate,
            'withdraw_money_type' => (int) ($row->withdraw_money_type ?? 1),
            'min_withdraw_money' => (string) $row->min_withdraw_amount,
            'max_withdraw_money' => (string) $row->max_withdraw_amount,
            'min_withdraw_amount' => (string) $row->min_withdraw_amount,
            'max_withdraw_amount' => (string) $row->max_withdraw_amount,
            'min_recharge_money' => (string) $row->min_recharge_amount,
            'min_recharge_amount' => (string) $row->min_recharge_amount,
            'fast_amount' => (string) ($row->fast_amount ?? ''),
            'instruction' => (string) ($row->instruction ?? ''),
        ];
    }

    public function show(int $id): PayPlatformModel
    {
        $platform = PayPlatformModel::find($id);
        if (!$platform) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '支付通道不存在');
        }
        return $platform;
    }

    public function create(array $data): PayPlatformModel
    {
        return PayPlatformModel::create($this->normalizeData($data));
    }

    public function update(int $id, array $data): PayPlatformModel
    {
        $platform = $this->show($id);
        $platform->update($this->normalizeData($data));
        return $platform->fresh();
    }

    public function delete(int $id): void
    {
        $platform = $this->show($id);
        $platform->delete();
    }

    public function toggle(int $id, string $field, int $value): PayPlatformModel
    {
        if (!in_array($field, ['status', 'recharge_is_show', 'withdraw_is_show', 'is_first_charge'], true)) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '不支持的开关字段');
        }
        $platform = $this->show($id);
        $platform->{$field} = $value;
        $platform->save();
        return $platform;
    }

    private function normalizeData(array $data): array
    {
        if (isset($data['fast_amount']) && is_array($data['fast_amount'])) {
            $data['fast_amount'] = implode(',', $data['fast_amount']);
        }
        if (isset($data['config']) && is_string($data['config'])) {
            $decoded = json_decode($data['config'], true);
            $data['config'] = is_array($decoded) ? $decoded : [];
        }
        return $data;
    }
}
