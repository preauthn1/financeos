<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\content\BannerModel;
use app\support\ContentCache;
use app\support\ErrorCode;
use support\Redis;

class BannerService
{
    public const REGIONS = [
        'index' => '首页',
        'introduce' => '资产',
    ];

    public function list(int $page, int $limit, array $filters = []): array
    {
        $query = BannerModel::query();
        if (!empty($filters['keyword'])) {
            $kw = (string) $filters['keyword'];
            $query->where('name', 'like', "%{$kw}%");
        }
        if (!empty($filters['region'])) {
            $query->where('region', (string) $filters['region']);
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int) $filters['status']);
        }

        $total = (clone $query)->count();
        $items = $query->orderBy('sort')->orderByDesc('id')
            ->offset(($page - 1) * $limit)->limit($limit)->get()
            ->map(fn ($row) => $this->formatItem($row));

        return ['total' => $total, 'items' => $items];
    }

    public function show(int $id): array
    {
        $item = BannerModel::find($id);
        if (!$item) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '轮播图不存在');
        }
        return $this->formatItem($item);
    }

    public function create(array $data): BannerModel
    {
        $item = BannerModel::create($this->normalize($data));
        ContentCache::clearBanner($item->region);
        return $item;
    }

    public function update(int $id, array $data): BannerModel
    {
        $item = BannerModel::find($id);
        if (!$item) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '轮播图不存在');
        }
        $oldRegion = (string) $item->region;
        $item->fill($this->normalize($data))->save();
        ContentCache::clearBanner($oldRegion);
        if ($item->region !== $oldRegion) {
            ContentCache::clearBanner((string) $item->region);
        }
        return $item->fresh();
    }

    public function delete(int $id): void
    {
        $item = BannerModel::find($id);
        if (!$item) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '轮播图不存在');
        }
        $region = (string) $item->region;
        $item->delete();
        ContentCache::clearBanner($region);
    }

    /** @return list<array<string,mixed>> */
    public function publicList(string $region = 'index', string $lang = 'zh-cn'): array
    {
        $cacheKey = ContentCache::bannerKey($region, $lang);
        $cached = Redis::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            $decoded = json_decode($cached, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $items = BannerModel::query()
            ->where('status', 1)
            ->where('region', $region)
            ->where('lang', $lang)
            ->orderBy('sort')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'lang' => $row->lang,
                'name' => $row->name,
                'link' => $row->link,
                'image_url' => $row->image_url,
                'status' => (int) $row->status,
                'sort' => (int) $row->sort,
                'region' => $row->region,
            ])
            ->values()
            ->all();

        Redis::setex($cacheKey, 3600, json_encode($items, JSON_UNESCAPED_UNICODE));
        return $items;
    }

    private function normalize(array $data): array
    {
        return [
            'lang' => (string) ($data['lang'] ?? 'zh-cn'),
            'name' => (string) ($data['name'] ?? ''),
            'link' => (string) ($data['link'] ?? ''),
            'image_url' => (string) ($data['image_url'] ?? ''),
            'status' => (int) ($data['status'] ?? 1),
            'sort' => (int) ($data['sort'] ?? 0),
            'region' => (string) ($data['region'] ?? 'index'),
        ];
    }

    private function formatItem(BannerModel $row): array
    {
        $data = $row->toArray();
        $data['region_text'] = self::REGIONS[$row->region] ?? $row->region;
        return $data;
    }
}
