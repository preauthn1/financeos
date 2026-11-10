<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\activity\ActivityModel;
use app\support\ErrorCode;

class ActivityService
{
    public function list(int $page, int $limit, array $filters = []): array
    {
        $query = ActivityModel::query();
        if (!empty($filters['keyword'])) {
            $kw = (string) $filters['keyword'];
            $query->where('title', 'like', "%{$kw}%");
        }
        if (isset($filters['type']) && $filters['type'] !== '') {
            $query->where('type', (int) $filters['type']);
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int) $filters['status']);
        }
        $total = (clone $query)->count();
        $items = $query->orderBy('id', 'desc')
            ->offset(($page - 1) * $limit)->limit($limit)->get()
            ->map(fn ($row) => $this->formatItem($row));
        return ['total' => $total, 'items' => $items];
    }

    public function show(int $id): array
    {
        $item = ActivityModel::find($id);
        if (!$item) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '活动不存在');
        }
        return $this->formatItem($item);
    }

    public function create(array $data): ActivityModel
    {
        return ActivityModel::create($data);
    }

    public function update(int $id, array $data): ActivityModel
    {
        $item = ActivityModel::find($id);
        if (!$item) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '活动不存在');
        }
        $item->fill($data)->save();
        return $item->fresh();
    }

    public function toggleStatus(int $id, int $status): ActivityModel
    {
        $item = ActivityModel::find($id);
        if (!$item) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '活动不存在');
        }
        $item->status = $status;
        $item->save();
        return $item;
    }

    private function formatItem(ActivityModel $row): array
    {
        $data = $row->toArray();
        $data['type_text'] = ActivityModel::$typeMap[$row->type] ?? '未知';
        return $data;
    }
}
