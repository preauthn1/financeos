<?php

declare(strict_types=1);

namespace app\service;

use app\model\sys\LangItemDetailModel;
use app\model\sys\LangItemModel;
use app\model\sys\LangModel;

class LangExportService
{
    public function exportAll(): int
    {
        $dir = base_path('resource/translations');
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException("无法创建翻译目录: {$dir}");
        }

        $langs = LangModel::where('status', 1)->orderByDesc('is_default')->orderBy('sort')->get();
        $items = LangItemModel::orderBy('id')->get(['id', 'title']);
        $details = LangItemDetailModel::query()->get(['lang_id', 'item_id', 'text']);
        $detailMap = [];
        foreach ($details as $row) {
            $detailMap[(int) $row->lang_id . '_' . (int) $row->item_id] = (string) $row->text;
        }

        $count = 0;
        foreach ($langs as $lang) {
            $config = [];
            foreach ($items as $item) {
                $key = (int) $lang->id . '_' . (int) $item->id;
                if (isset($detailMap[$key]) && $detailMap[$key] !== '') {
                    $config[(string) $item->title] = $detailMap[$key];
                }
            }
            $file = $dir . '/' . $this->fileName((string) $lang->lang);
            $code = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($config, true) . ";\n";
            file_put_contents($file, $code);
            $count++;
        }

        return $count;
    }

    public function loadForLocale(string $apiLocale): array
    {
        $file = base_path('resource/translations/' . $this->fileName($apiLocale));
        if (!is_file($file)) {
            return [];
        }
        $data = include $file;
        return is_array($data) ? $data : [];
    }

    private function fileName(string $langCode): string
    {
        return str_replace('-', '_', strtolower($langCode)) . '.php';
    }
}
