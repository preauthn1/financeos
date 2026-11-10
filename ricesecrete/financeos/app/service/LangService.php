<?php

declare(strict_types=1);

namespace app\service;

use app\model\sys\LangItemDetailModel;
use app\model\sys\LangItemModel;
use app\model\sys\LangModel;
use app\support\Locale;
use Illuminate\Database\Eloquent\Builder;

class LangService
{
    public function list(int $page, int $limit, array $filters = []): array
    {
        $query = LangModel::query()->orderByDesc('is_default')->orderBy('sort')->orderBy('id');
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int) $filters['status']);
        }
        if (!empty($filters['keyword'])) {
            $kw = (string) $filters['keyword'];
            $query->where(function (Builder $q) use ($kw): void {
                $q->where('title', 'like', "%{$kw}%")->orWhere('lang', 'like', "%{$kw}%");
            });
        }

        $total = (clone $query)->count();
        $items = $query->forPage($page, $limit)->get()->map(fn (LangModel $row) => $this->formatLang($row))->all();

        return compact('total', 'items');
    }

    public function options(bool $enabledOnly = true): array
    {
        $query = LangModel::query()->orderByDesc('is_default')->orderBy('sort')->orderBy('id');
        if ($enabledOnly) {
            $query->where('status', 1);
        }
        return $query->get()->map(fn (LangModel $row) => $this->formatLang($row))->all();
    }

    public function show(int $id): array
    {
        $row = LangModel::findOrFail($id);
        return $this->formatLang($row);
    }

    public function create(array $data): array
    {
        $this->normalizeDefaults($data, 0);
        $row = LangModel::create([
            'title' => (string) ($data['title'] ?? ''),
            'lang' => Locale::normalize((string) ($data['lang'] ?? '')),
            'remark' => (string) ($data['remark'] ?? ''),
            'is_default' => (int) ($data['is_default'] ?? 0),
            'is_default_lang' => (int) ($data['is_default_lang'] ?? 0),
            'flag' => (string) ($data['flag'] ?? ''),
            'status' => (int) ($data['status'] ?? 1),
            'sort' => (int) ($data['sort'] ?? 0),
        ]);
        return $this->formatLang($row);
    }

    public function update(int $id, array $data): array
    {
        $row = LangModel::findOrFail($id);
        $this->normalizeDefaults($data, $id);
        if (isset($data['lang'])) {
            $data['lang'] = Locale::normalize((string) $data['lang']);
        }
        $row->fill(array_intersect_key($data, array_flip($row->getFillable())));
        $row->save();
        return $this->formatLang($row->fresh());
    }

    public function delete(int $id): void
    {
        LangItemDetailModel::where('lang_id', $id)->delete();
        LangModel::where('id', $id)->delete();
    }

    public function publicList(): array
    {
        return LangModel::where('status', 1)
            ->orderByDesc('is_default')
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->map(function (LangModel $row): array {
                $item = $this->formatLang($row);
                $item['front_code'] = Locale::toFrontCode((string) $row->lang);
                return $item;
            })
            ->all();
    }

    private function normalizeDefaults(array &$data, int $currentId): void
    {
        if (!empty($data['is_default'])) {
            LangModel::when($currentId > 0, fn (Builder $q) => $q->where('id', '!=', $currentId))
                ->update(['is_default' => 0]);
        }
        if (!empty($data['is_default_lang'])) {
            LangModel::when($currentId > 0, fn (Builder $q) => $q->where('id', '!=', $currentId))
                ->update(['is_default_lang' => 0]);
        }
    }

    private function formatLang(LangModel $row): array
    {
        return [
            'id' => (int) $row->id,
            'title' => (string) $row->title,
            'lang' => (string) $row->lang,
            'remark' => (string) $row->remark,
            'is_default' => (int) $row->is_default,
            'is_default_lang' => (int) $row->is_default_lang,
            'flag' => (string) $row->flag,
            'status' => (int) $row->status,
            'sort' => (int) $row->sort,
            'created_at' => (string) $row->created_at,
            'updated_at' => (string) $row->updated_at,
        ];
    }
}
