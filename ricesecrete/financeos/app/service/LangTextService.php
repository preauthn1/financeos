<?php

declare(strict_types=1);

namespace app\service;

use app\model\sys\LangItemDetailModel;
use app\model\sys\LangItemModel;
use app\model\sys\LangModel;
use Illuminate\Database\Eloquent\Builder;

class LangTextService
{
    public function __construct(private ?LangExportService $exportService = null)
    {
        $this->exportService = $exportService ?? new LangExportService();
    }

    public function list(int $page, int $limit, array $filters = []): array
    {
        $query = LangItemModel::query()->orderByDesc('id');
        if (!empty($filters['keyword'])) {
            $kw = (string) $filters['keyword'];
            $query->where('title', 'like', "%{$kw}%");
        }

        $total = (clone $query)->count();
        $rows = $query->forPage($page, $limit)->get();
        $langs = LangModel::orderByDesc('is_default')->orderBy('sort')->get();
        $itemIds = $rows->pluck('id')->all();

        $details = [];
        if ($itemIds !== []) {
            $detailRows = LangItemDetailModel::whereIn('item_id', $itemIds)->get();
            foreach ($detailRows as $detail) {
                $details[(int) $detail->item_id][(int) $detail->lang_id] = (string) $detail->text;
            }
        }

        $items = $rows->map(function (LangItemModel $row) use ($langs, $details): array {
            $texts = [];
            foreach ($langs as $lang) {
                $texts[(string) $lang->lang] = $details[(int) $row->id][(int) $lang->id] ?? '';
            }
            return [
                'id' => (int) $row->id,
                'title' => (string) $row->title,
                'texts' => $texts,
                'created_at' => (string) $row->created_at,
                'updated_at' => (string) $row->updated_at,
            ];
        })->all();

        return [
            'total' => $total,
            'items' => $items,
            'langs' => $langs->map(fn (LangModel $l) => [
                'id' => (int) $l->id,
                'title' => (string) $l->title,
                'lang' => (string) $l->lang,
            ])->all(),
        ];
    }

    public function show(int $id): array
    {
        $row = LangItemModel::findOrFail($id);
        $langs = LangModel::orderByDesc('is_default')->orderBy('sort')->get();
        $details = LangItemDetailModel::where('item_id', $id)->get()->keyBy('lang_id');

        $texts = [];
        foreach ($langs as $lang) {
            $texts[(string) $lang->lang] = (string) ($details[(int) $lang->id]->text ?? '');
        }

        return [
            'id' => (int) $row->id,
            'title' => (string) $row->title,
            'texts' => $texts,
        ];
    }

    public function save(array $data): array
    {
        $id = (int) ($data['id'] ?? 0);
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('请填写文案键');
        }

        if ($id > 0) {
            $item = LangItemModel::findOrFail($id);
            $item->title = $title;
            $item->save();
        } else {
            $item = LangItemModel::firstOrCreate(['title' => $title]);
        }

        $texts = (array) ($data['texts'] ?? []);
        $langs = LangModel::where('status', 1)->get();
        foreach ($langs as $lang) {
            $code = (string) $lang->lang;
            if (!array_key_exists($code, $texts)) {
                continue;
            }
            LangItemDetailModel::updateOrCreate(
                ['lang_id' => (int) $lang->id, 'item_id' => (int) $item->id],
                ['text' => trim((string) $texts[$code])]
            );
        }

        $this->exportService->exportAll();
        return $this->show((int) $item->id);
    }

    public function delete(int $id): void
    {
        LangItemDetailModel::where('item_id', $id)->delete();
        LangItemModel::where('id', $id)->delete();
        $this->exportService->exportAll();
    }

    public function export(): int
    {
        return $this->exportService->exportAll();
    }
}
