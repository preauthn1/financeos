<?php

declare(strict_types=1);

namespace app\service\api;

use app\model\pay\WithdrawOrderModel;
use app\service\MemberWaterService;

class ApiMoneyLogService
{
    public function list(int $userId, int $page, int $limit, array $filters = []): array
    {
        $filters['user_id'] = $userId;

        // H5「全部」传 detail_type=0；0 在业务里表示后台调整，不能按 0 过滤
        if (!array_key_exists('detail_type', $filters)
            || $filters['detail_type'] === ''
            || $filters['detail_type'] === null
            || (int) $filters['detail_type'] === 0
        ) {
            unset($filters['detail_type']);
        } else {
            $filters['detail_type'] = (int) $filters['detail_type'];
        }

        if (isset($filters['type']) && $filters['type'] !== '' && $filters['type'] !== null) {
            $filters['type'] = (int) $filters['type'];
        }

        $result = (new MemberWaterService())->list($page, $limit, $filters);

        $items = $result['items'] ?? [];
        if (!is_array($items) || $items === []) {
            return $result;
        }

        $orderIds = [];
        foreach ($items as $item) {
            if ((int) ($item['detail_type'] ?? 0) === 2 && !empty($item['order_no'])) {
                $orderIds[] = (int) $item['order_no'];
            }
        }

        $statusMap = [];
        if ($orderIds !== []) {
            $statusMap = WithdrawOrderModel::query()
                ->whereIn('id', array_values(array_unique($orderIds)))
                ->pluck('status', 'id')
                ->all();
        }

        foreach ($items as &$item) {
            $item['withdrawals_status'] = 0;
            if ((int) ($item['detail_type'] ?? 0) === 2) {
                $oid = (int) ($item['order_no'] ?? 0);
                $item['withdrawals_status'] = (int) ($statusMap[$oid] ?? 0);
            }
            $item['create_time'] = $item['create_time'] ?? $item['created_at'] ?? '';
        }
        unset($item);

        $result['items'] = $items;
        // 兼容部分前端把分页 data 当列表读
        $result['data'] = $items;

        return $result;
    }
}
