<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\sys\DictItemModel;
use app\model\sys\DictTypeModel;
use app\support\ErrorCode;
use support\Redis;

class DictService
{
    private const CACHE_PREFIX = 'dict:';

    public function listTypes(int $page, int $limit, array $filters = []): array
    {
        $query = DictTypeModel::query();
        if (!empty($filters['keyword'])) {
            $keyword = $filters['keyword'];
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('code', 'like', "%{$keyword}%");
            });
        }

        $total = $query->count();
        $items = $query->orderBy('id', 'desc')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return ['total' => $total, 'items' => $items];
    }

    public function createType(array $data): DictTypeModel
    {
        if (DictTypeModel::where('code', $data['code'])->exists()) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '字典编码已存在');
        }
        return DictTypeModel::create($data);
    }

    public function updateType(int $id, array $data): DictTypeModel
    {
        $type = DictTypeModel::find($id);
        if (!$type) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '字典类型不存在');
        }
        unset($data['code']);
        $type->update($data);
        $this->clearCache($type->code);
        return $type;
    }

    public function deleteType(int $id): void
    {
        $type = DictTypeModel::find($id);
        if (!$type) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '字典类型不存在');
        }
        DictItemModel::where('dict_type_id', $id)->delete();
        $this->clearCache($type->code);
        $type->delete();
    }

    public function listItems(int $typeId): array
    {
        return DictItemModel::where('dict_type_id', $typeId)
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->toArray();
    }

    public function saveItems(int $typeId, array $items): void
    {
        $type = DictTypeModel::find($typeId);
        if (!$type) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '字典类型不存在');
        }

        DictItemModel::where('dict_type_id', $typeId)->delete();
        foreach ($items as $index => $item) {
            DictItemModel::create([
                'dict_type_id' => $typeId,
                'label' => (string) ($item['label'] ?? ''),
                'value' => (string) ($item['value'] ?? ''),
                'sort' => (int) ($item['sort'] ?? $index),
                'status' => (int) ($item['status'] ?? 1),
                'remark' => (string) ($item['remark'] ?? ''),
            ]);
        }

        $this->clearCache($type->code);
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    public function getByCode(string $code): array
    {
        $cacheKey = self::CACHE_PREFIX . $code;
        $cached = Redis::get($cacheKey);
        if ($cached) {
            return json_decode($cached, true);
        }

        $type = DictTypeModel::where('code', $code)->where('status', 1)->first();
        if (!$type) {
            return [];
        }

        $items = DictItemModel::where('dict_type_id', $type->id)
            ->where('status', 1)
            ->orderBy('sort')
            ->orderBy('id')
            ->get(['label', 'value'])
            ->toArray();

        Redis::setex($cacheKey, 3600, json_encode($items));
        return $items;
    }

    private function clearCache(string $code): void
    {
        Redis::del(self::CACHE_PREFIX . $code);
    }
}
