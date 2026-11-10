<?php

declare(strict_types=1);

namespace app\service\api;

use app\support\DateTimeFormat;
use app\model\ad\AdAdvertisNumModel;
use app\model\ad\AdAdvertisSpaceModel;
use app\support\ErrorCode;

class ApiAdvertisService
{
    public function index(): array
    {
        $list = AdAdvertisSpaceModel::query()
            ->select('advertis_name', 'turnover', 'exposure', 'cpm_cost_amount', 'advertis_en_name as advertis_en', 'created_at')
            ->where('advertis_en_name', '<>', '')
            ->orderByDesc('created_at')
            ->get()
            ->unique('advertis_en')
            ->values();

        $stockAll = 0.0;
        $turnoverAll = 0.0;
        $exposureAll = 0.0;
        $items = [];
        foreach ($list as $item) {
            $stock = (float) (AdAdvertisNumModel::where('advertis_name', $item->advertis_name)->value('num') ?? 0);
            $exposure = (float) ($item->exposure ?? 0);
            $turnover = (float) ($item->turnover ?? 0);
            $items[] = [
                'advertis_name' => $item->advertis_name,
                'advertis_en' => $item->advertis_en,
                'turnover' => $turnover,
                'exposure' => $exposure,
                'cpm_cost_amount' => (float) $item->cpm_cost_amount,
                'matching_rate' => $exposure > 0 ? round($turnover / $exposure * 100, 2) : 0,
                'stock_num' => $stock,
            ];
            $stockAll += $stock;
            $turnoverAll += $turnover;
            $exposureAll += $exposure;
        }

        return [
            'stock_all_last' => round($stockAll, 4),
            'matching_rate' => $exposureAll > 0 ? round($turnoverAll / $exposureAll * 100, 2) : 0,
            'list' => $items,
        ];
    }

    public function detail(string $type, string $cycle): array
    {
        if ($type === '') {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '类型不能为空');
        }
        if ($cycle === '') {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '周期不能为空');
        }

        $between = $this->cycleRange($cycle);
        if ($type === 'all') {
            $names = AdAdvertisSpaceModel::where('advertis_en_name', '<>', '')->distinct()->pluck('advertis_name');
            $merged = [];
            foreach ($names as $name) {
                foreach ($this->dayList((string) $name, $between) as $row) {
                    $key = $row['formatted_date'] . '_' . $row['sort_type'];
                    if (!isset($merged[$key])) {
                        $merged[$key] = $row;
                    } else {
                        $merged[$key]['total_exposure'] += $row['total_exposure'];
                        $merged[$key]['total_turnover'] += $row['total_turnover'];
                    }
                }
            }
            $list = array_values($merged);
            usort($list, fn ($a, $b) => [$a['formatted_date'], (int) substr($a['sort_type'], 1)]
                <=> [$b['formatted_date'], (int) substr($b['sort_type'], 1)]);
            $lastData = [
                'advertis_name' => '', 'exposure' => 0, 'turnover' => 0,
                'turnover_num' => 0, 'turnover_amount' => 0, 'cpm_cost_amount' => 0, 'advertis_en_name' => '',
            ];
        } else {
            $lastData = AdAdvertisSpaceModel::where('advertis_name', $type)->orderByDesc('created_at')->first()?->toArray() ?? [];
            $list = $this->dayList($type, $between);
        }

        return ['type' => $type, 'last_data' => $lastData, 'list' => $list];
    }

    private function cycleRange(string $cycle): array
    {
        return match ($cycle) {
            '3' => [strtotime(date('Y-m-d', strtotime('-3 days'))), time()],
            '5' => [strtotime(date('Y-m-d', strtotime('-5 days'))), time()],
            '7' => [strtotime(date('Y-m-d', strtotime('-7 days'))), time()],
            default => [strtotime(date('Y-m-d')), time()],
        };
    }

    private function dayList(string $type, array $between): array
    {
        $list = AdAdvertisSpaceModel::where('advertis_name', $type)
            ->whereBetween('created_at', $between)
            ->orderBy('created_at')
            ->get(['id', 'exposure', 'turnover', 'created_at'])
            ->map(fn ($row) => [
                'id' => $row->id,
                'total_exposure' => (float) $row->exposure,
                'total_turnover' => (float) $row->turnover,
                'create_time' => DateTimeFormat::unix($row->created_at),
                'formatted_date' => DateTimeFormat::datetime($row->created_at, 'm/d'),
            ])->toArray();

        $sortTypeNum = 1;
        foreach ($list as $key => &$val) {
            if ($key > 0 && $val['formatted_date'] === $list[$key - 1]['formatted_date']) {
                $sortTypeNum++;
            } else {
                $sortTypeNum = 1;
            }
            $val['sort_type_num'] = $sortTypeNum;
            $val['sort_type'] = 'M' . $sortTypeNum;
        }
        return $list;
    }
}
