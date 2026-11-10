<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\content\CustomerServiceLinkModel;
use app\support\ContentCache;
use app\support\ErrorCode;
use support\Redis;

class CustomerLinkService
{
    public function list(int $page, int $limit, array $filters = []): array
    {
        $query = CustomerServiceLinkModel::query();
        if (!empty($filters['keyword'])) {
            $kw = (string) $filters['keyword'];
            $query->where('name', 'like', "%{$kw}%");
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int) $filters['status']);
        }

        $total = (clone $query)->count();
        $items = $query->orderBy('sort')->orderByDesc('id')
            ->offset(($page - 1) * $limit)->limit($limit)->get()
            ->map(fn ($row) => $row->toArray());

        return ['total' => $total, 'items' => $items];
    }

    public function show(int $id): array
    {
        $item = CustomerServiceLinkModel::find($id);
        if (!$item) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '客服配置不存在');
        }
        return $item->toArray();
    }

    public function create(array $data): CustomerServiceLinkModel
    {
        $item = CustomerServiceLinkModel::create($this->normalize($data));
        ContentCache::clearCustomerService();
        return $item;
    }

    public function update(int $id, array $data): CustomerServiceLinkModel
    {
        $item = CustomerServiceLinkModel::find($id);
        if (!$item) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '客服配置不存在');
        }
        $item->fill($this->normalize($data))->save();
        ContentCache::clearCustomerService();
        return $item->fresh();
    }

    public function delete(int $id): void
    {
        $item = CustomerServiceLinkModel::find($id);
        if (!$item) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '客服配置不存在');
        }
        $item->delete();
        ContentCache::clearCustomerService();
    }

    /** @return list<array<string,mixed>> */
    public function publicList(): array
    {
        $cacheKey = ContentCache::customerServiceKey();
        $cached = Redis::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            $decoded = json_decode($cached, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $items = CustomerServiceLinkModel::query()
            ->where('status', 1)
            ->orderBy('sort')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($row) => [
                'image' => $row->image,
                'link' => $row->link,
                'name' => $row->name,
            ])
            ->values()
            ->all();

        Redis::setex($cacheKey, 3600, json_encode($items, JSON_UNESCAPED_UNICODE));
        return $items;
    }

    private function normalize(array $data): array
    {
        return [
            'image' => (string) ($data['image'] ?? ''),
            'link' => (string) ($data['link'] ?? ''),
            'name' => (string) ($data['name'] ?? ''),
            'sort' => (int) ($data['sort'] ?? 0),
            'status' => (int) ($data['status'] ?? 1),
        ];
    }
}
