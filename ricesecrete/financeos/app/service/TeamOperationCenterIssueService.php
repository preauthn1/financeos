<?php

declare(strict_types=1);

namespace app\service;

use app\model\ops\TeamOperationCenterIssueModel;
use app\model\ops\TeamOperationCenterModel;

class TeamOperationCenterIssueService
{
    public function list(int $page, int $limit, array $filters = []): array
    {
        $query = TeamOperationCenterIssueModel::query()->with([
            'user:id,username,nickname',
            'center:id,auth_code,status',
        ]);

        if (!empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }
        if (!empty($filters['submit_user_id'])) {
            $query->where('submit_user_id', (int) $filters['submit_user_id']);
        }
        if (!empty($filters['issue'])) {
            $query->where('issue', (string) $filters['issue']);
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int) $filters['status']);
        }
        if (isset($filters['type']) && $filters['type'] !== '') {
            $query->where('type', (int) $filters['type']);
        }
        if (!empty($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date'] . ' 00:00:00');
        }
        if (!empty($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date'] . ' 23:59:59');
        }

        $statsQuery = clone $query;
        $stats = [
            'total_bonus' => (string) (clone $statsQuery)->sum('bonus'),
            'total_dividend' => (string) (clone $statsQuery)->sum('dividend'),
            'total_rebate' => (string) (clone $statsQuery)->sum('rebate'),
            'total_fund' => (string) (clone $statsQuery)->sum('fund'),
        ];

        $total = (clone $query)->count();
        $items = $query->orderBy('id', 'desc')
            ->offset(($page - 1) * $limit)->limit($limit)->get()
            ->map(fn ($row) => $this->formatItem($row));

        return [
            'total' => $total,
            'items' => $items,
            'stats' => $stats,
            'meta' => [
                'types' => $this->options(TeamOperationCenterModel::$typeMap),
                'statuses' => $this->options(TeamOperationCenterIssueModel::$statusMap),
            ],
        ];
    }

    private function formatItem(TeamOperationCenterIssueModel $row): array
    {
        return [
            'id' => (int) $row->id,
            'user_id' => (int) $row->user_id,
            'username' => (string) ($row->user?->username ?? ''),
            'submit_user_id' => (int) ($row->submit_user_id ?? 0),
            'center_id' => (int) ($row->center_id ?? 0),
            'type' => (int) $row->type,
            'type_text' => TeamOperationCenterModel::$typeMap[$row->type] ?? '未知',
            'status' => (int) $row->status,
            'status_text' => TeamOperationCenterIssueModel::$statusMap[$row->status] ?? '未知',
            'issue' => (string) ($row->issue ?? ''),
            'dividend' => (string) ($row->dividend ?? '0'),
            'rebate' => (string) ($row->rebate ?? '0'),
            'fund' => (string) ($row->fund ?? '0'),
            'bonus' => (string) ($row->bonus ?? '0'),
            'sub_count' => (int) ($row->sub_count ?? 0),
            'sub_reward' => (string) ($row->sub_reward ?? '0'),
            'dividend_item' => (string) ($row->dividend_item ?? ''),
            'created_at' => (string) $row->created_at,
        ];
    }

    /** @param array<int|string, string> $map @return list<array{value:int,label:string}> */
    private function options(array $map): array
    {
        $out = [];
        foreach ($map as $value => $label) {
            $out[] = ['value' => (int) $value, 'label' => $label];
        }
        return $out;
    }
}
