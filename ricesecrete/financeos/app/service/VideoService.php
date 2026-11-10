<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\content\VideoModel;
use app\support\ContentCache;
use app\support\ErrorCode;
use Illuminate\Database\Eloquent\Builder;

class VideoService
{
    public function list(int $page, int $limit, array $filters = []): array
    {
        $query = VideoModel::query()->orderBy('sort')->orderByDesc('id');
        $this->applyFilters($query, $filters);

        $total = (clone $query)->count();
        $items = $query->forPage($page, $limit)->get()
            ->map(fn (VideoModel $row) => $this->formatItem($row))
            ->all();

        return ['total' => $total, 'items' => $items];
    }

    public function show(int $id): array
    {
        $row = VideoModel::find($id);
        if (!$row) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '视频不存在');
        }
        return $this->formatItem($row);
    }

    public function create(array $data): array
    {
        $this->validateCate((int) ($data['cate'] ?? 0));
        if (empty($data['video_url'])) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '请填写视频链接');
        }

        $row = VideoModel::create($this->normalize($data));
        ContentCache::clearVideo((string) ($row->lang ?? 'zh-cn'));
        return $this->formatItem($row);
    }

    public function update(int $id, array $data): array
    {
        $row = VideoModel::find($id);
        if (!$row) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '视频不存在');
        }
        if (isset($data['cate'])) {
            $this->validateCate((int) $data['cate']);
        }

        $oldLang = (string) $row->lang;
        $row->fill($this->normalize($data));
        $row->save();

        ContentCache::clearVideo($oldLang);
        if ((string) $row->lang !== $oldLang) {
            ContentCache::clearVideo((string) $row->lang);
        }

        return $this->formatItem($row->fresh());
    }

    public function delete(int $id): void
    {
        $row = VideoModel::find($id);
        if (!$row) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '视频不存在');
        }
        $lang = (string) $row->lang;
        $row->delete();
        ContentCache::clearVideo($lang);
    }

    /** @return array<int, string> */
    public function cateOptions(): array
    {
        return VideoModel::ADMIN_CATE_MAP;
    }

    private function validateCate(int $cate): void
    {
        if ($cate <= 0 || !isset(VideoModel::ADMIN_CATE_MAP[$cate])) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '请选择有效分类');
        }
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['keyword'])) {
            $kw = (string) $filters['keyword'];
            $query->where(function (Builder $q) use ($kw): void {
                $q->where('name', 'like', "%{$kw}%")
                    ->orWhere('sub_name', 'like', "%{$kw}%");
            });
        }
        if (isset($filters['cate']) && $filters['cate'] !== '') {
            $query->where('cate', (int) $filters['cate']);
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int) $filters['status']);
        }
        if (!empty($filters['lang'])) {
            $query->where('lang', (string) $filters['lang']);
        }
    }

    private function normalize(array $data): array
    {
        return [
            'lang' => (string) ($data['lang'] ?? 'zh-cn'),
            'cate' => (int) ($data['cate'] ?? 0),
            'level_id' => (int) ($data['level_id'] ?? 0),
            'sub_name' => (string) ($data['sub_name'] ?? ''),
            'name' => (string) ($data['name'] ?? ''),
            'image_url' => (string) ($data['image_url'] ?? ''),
            'video_url' => (string) ($data['video_url'] ?? ''),
            'duration' => (int) ($data['duration'] ?? 0),
            'director' => (string) ($data['director'] ?? ''),
            'description' => (string) ($data['description'] ?? ''),
            'watch_times' => (int) ($data['watch_times'] ?? 0),
            'sort' => (int) ($data['sort'] ?? 50),
            'status' => (int) ($data['status'] ?? 1),
            'remark' => (string) ($data['remark'] ?? ''),
        ];
    }

    private function formatItem(VideoModel $row): array
    {
        $data = $row->toArray();
        $data['cate_str'] = VideoModel::cateLabel((int) $row->cate);
        return $data;
    }
}
