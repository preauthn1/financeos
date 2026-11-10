<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\invest\InvestmentProductModel;
use app\support\ErrorCode;

class InvestmentProductService
{
    /** 长周期 AI 托管：全局进行中购买次数上限（所有长周期产品共用） */
    public const CONFIG_LONG_ORDER_LIMIT = 'investment_product_long_order_limit';

    public function __construct(private ?SettingService $settingService = null)
    {
        $this->settingService = $settingService ?? new SettingService();
    }

    public function list(int $page, int $limit, array $filters = []): array
    {
        $query = InvestmentProductModel::query();
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int) $filters['status']);
        }
        if (!empty($filters['keyword'])) {
            $kw = (string) $filters['keyword'];
            $query->where('product_name', 'like', "%{$kw}%");
        }
        $total = (clone $query)->count();
        $items = $query->orderBy('sort')->orderBy('id', 'desc')
            ->offset(($page - 1) * $limit)->limit($limit)->get();

        return [
            'total' => $total,
            'items' => $items,
            'meta' => [
                'long_order_limit' => $this->getLongOrderLimit(),
            ],
        ];
    }

    public function show(int $id): InvestmentProductModel
    {
        $item = InvestmentProductModel::find($id);
        if (!$item) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '产品不存在');
        }

        return $item;
    }

    public function create(array $data): InvestmentProductModel
    {
        return InvestmentProductModel::create($this->normalizeProductData($data));
    }

    public function update(int $id, array $data): InvestmentProductModel
    {
        $item = $this->show($id);
        $item->fill($this->normalizeProductData($data))->save();

        return $item->fresh();
    }

    /**
     * 长周期产品共用全局次数；短周期仍可用产品字段。
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeProductData(array $data): array
    {
        $profitType = (int) ($data['profit_type'] ?? 2);
        if ($profitType === 1) {
            if (!isset($data['cycle']) || (float) $data['cycle'] <= 0) {
                $data['cycle'] = 1;
            }
            // 长周期次数走全局配置，产品字段不再使用
            $data['order_limit'] = 0;
        } else {
            $data['profit_type'] = 2;
            $data['cycle'] = 0;
            // 最高余额 / 总额度 / 返佣 仅长周期使用
            $data['max_balance'] = 0;
            $data['total_quota'] = 0;
            $data['commission_rates'] = null;
        }

        return $data;
    }

    public function toggle(int $id, string $field, int $value): InvestmentProductModel
    {
        if (!in_array($field, ['status', 'sales_status'], true)) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '字段不允许切换');
        }
        $item = $this->show($id);
        $item->{$field} = $value;
        $item->save();

        return $item;
    }

    /** 长周期全局购买次数（0=不限） */
    public function getLongOrderLimit(): int
    {
        return max(0, (int) $this->settingService->getValue(self::CONFIG_LONG_ORDER_LIMIT, 5));
    }

    public function saveLongOrderLimit(int $limit): int
    {
        if ($limit < 0) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '购买次数不能为负数');
        }
        $this->settingService->upsert(
            self::CONFIG_LONG_ORDER_LIMIT,
            $limit,
            '长周期AI托管全局进行中购买次数（所有长周期产品共用，0不限）'
        );

        return $limit;
    }
}
