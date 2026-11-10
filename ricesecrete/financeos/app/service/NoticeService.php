<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\content\NoticeInfoModel;
use app\model\content\NoticeModel;
use app\model\member\UserModel;
use app\support\ContentCache;
use app\support\ErrorCode;
use support\Redis;

class NoticeService
{
    public function list(int $page, int $limit, array $filters = []): array
    {
        $query = NoticeModel::query();
        if (!empty($filters['keyword'])) {
            $kw = (string) $filters['keyword'];
            if (ctype_digit($kw)) {
                $query->where('id', (int) $kw);
            } else {
                $query->where('title', 'like', "%{$kw}%");
            }
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int) $filters['status']);
        }

        $total = (clone $query)->count();
        $items = $query->orderByDesc('id')
            ->offset(($page - 1) * $limit)->limit($limit)->get()
            ->map(fn ($row) => $this->formatItem($row));

        return ['total' => $total, 'items' => $items];
    }

    public function show(int $id): array
    {
        $item = NoticeModel::with('translations')->find($id);
        if (!$item) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '公告不存在');
        }
        return $this->formatItem($item, true);
    }

    public function create(array $data): NoticeModel
    {
        $payload = $this->normalize($data);
        if ($payload['user_id'] > 0) {
            $payload['type'] = 1;
            if (!UserModel::where('id', $payload['user_id'])->exists()) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '指定用户不存在');
            }
        }

        $item = NoticeModel::create($payload);
        $this->syncTranslations($item->id, $data['translations'] ?? []);
        ContentCache::clearNotice();
        return $item->fresh(['translations']);
    }

    public function update(int $id, array $data): NoticeModel
    {
        $item = NoticeModel::find($id);
        if (!$item) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '公告不存在');
        }

        $payload = $this->normalize($data);
        if ($payload['user_id'] > 0) {
            $payload['type'] = 1;
            if (!UserModel::where('id', $payload['user_id'])->exists()) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '指定用户不存在');
            }
        } else {
            $payload['type'] = 0;
        }

        $item->fill($payload)->save();
        if (array_key_exists('translations', $data)) {
            $this->syncTranslations($item->id, $data['translations'] ?? []);
        }
        ContentCache::clearNotice();
        return $item->fresh(['translations']);
    }

    public function delete(int $id): void
    {
        $item = NoticeModel::find($id);
        if (!$item) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '公告不存在');
        }
        NoticeInfoModel::where('notice_id', $id)->delete();
        $item->delete();
        ContentCache::clearNotice();
    }

    /** @return array{total:int,data:list<array<string,mixed>>} */
    public function publicList(int $page, int $limit, string $lang = 'zh-cn'): array
    {
        $cacheKey = ContentCache::noticeKey($lang) . ":{$page}:{$limit}";
        $cached = Redis::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            $decoded = json_decode($cached, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $query = NoticeModel::query()->where('status', 1)->where('type', 0);
        $total = (clone $query)->count();
        $rows = $query->orderByDesc('id')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        $data = $rows->map(fn (NoticeModel $row) => $this->transformPublic($row, $lang))->values()->all();
        $result = ['total' => $total, 'data' => $data];
        Redis::setex($cacheKey, 600, json_encode($result, JSON_UNESCAPED_UNICODE));
        return $result;
    }

    /** @return list<array<string,mixed>> */
    public function publicPreview(int $limit = 5, string $lang = 'zh-cn'): array
    {
        $result = $this->publicList(1, $limit, $lang);
        return $result['data'];
    }

    private function transformPublic(NoticeModel $row, string $lang): array
    {
        $title = (string) $row->title;
        $content = (string) $row->content;
        if ($lang !== (string) $row->lang) {
            $translation = NoticeInfoModel::where('notice_id', $row->id)->where('lang', $lang)->first();
            if ($translation) {
                $title = (string) ($translation->title ?: $title);
                $content = (string) ($translation->content ?: $content);
            }
        }

        $createdAt = $row->created_at;
        $timestamp = is_numeric($createdAt) ? (int) $createdAt : strtotime((string) $createdAt);

        return [
            'id' => $row->id,
            'lang' => $lang,
            'title' => $title,
            'content' => nl2br($content),
            'create_time' => $timestamp > 0 ? date('Y-m-d H:i:s', $timestamp) : '',
        ];
    }

    /** @param array<string,array{title?:string,content?:string}|string> $translations */
    private function syncTranslations(int $noticeId, array $translations): void
    {
        foreach ($translations as $lang => $value) {
            $lang = (string) $lang;
            if ($lang === '') {
                continue;
            }
            $title = is_array($value) ? (string) ($value['title'] ?? '') : '';
            $content = is_array($value) ? (string) ($value['content'] ?? '') : (string) $value;
            if ($title === '' && $content === '') {
                NoticeInfoModel::where('notice_id', $noticeId)->where('lang', $lang)->delete();
                continue;
            }
            NoticeInfoModel::updateOrCreate(
                ['notice_id' => $noticeId, 'lang' => $lang],
                ['title' => $title, 'content' => $content]
            );
        }
    }

    private function normalize(array $data): array
    {
        return [
            'user_id' => (int) ($data['user_id'] ?? 0),
            'lang' => (string) ($data['lang'] ?? 'zh-cn'),
            'title' => (string) ($data['title'] ?? ''),
            'type' => (int) ($data['type'] ?? 0),
            'content' => (string) ($data['content'] ?? ''),
            'status' => (int) ($data['status'] ?? 1),
        ];
    }

    private function formatItem(NoticeModel $row, bool $withTranslations = false): array
    {
        $data = $row->toArray();
        if ($withTranslations) {
            $translations = [];
            foreach ($row->translations ?? [] as $item) {
                $translations[$item->lang] = [
                    'title' => $item->title,
                    'content' => $item->content,
                ];
            }
            $data['translations'] = $translations;
        }
        return $data;
    }
}
