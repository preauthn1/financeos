<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\ops\TeamOperationCenterIssueModel;
use app\model\ops\TeamOperationCenterModel;
use app\support\ErrorCode;
use Illuminate\Database\Capsule\Manager as DB;

class TeamOperationCenterService
{
    public function __construct(private ?SettingService $settingService = null)
    {
        $this->settingService = $settingService ?? new SettingService();
    }

    public function list(int $page, int $limit, array $filters = []): array
    {
        $query = TeamOperationCenterModel::query()->with([
            'user:id,username,nickname',
            'submitUser:id,username,nickname',
        ]);

        if (!empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }
        if (!empty($filters['submit_user_id'])) {
            $query->where('submit_user_id', (int) $filters['submit_user_id']);
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

        $total = (clone $query)->count();
        $items = $query->orderBy('id', 'desc')
            ->offset(($page - 1) * $limit)->limit($limit)->get()
            ->map(fn ($row) => $this->formatItem($row));

        return [
            'total' => $total,
            'items' => $items,
            'meta' => [
                'types' => $this->options(TeamOperationCenterModel::$typeMap),
                'statuses' => $this->options(TeamOperationCenterModel::$statusMap),
            ],
        ];
    }

    public function changeStatus(int $id, int $status): TeamOperationCenterModel
    {
        if (!isset(TeamOperationCenterModel::$statusMap[$status])) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '状态有误');
        }

        return DB::transaction(function () use ($id, $status) {
            $item = TeamOperationCenterModel::where('id', $id)->lockForUpdate()->first();
            if (!$item) {
                throw new BusinessException(ErrorCode::NOT_FOUND, '申请不存在');
            }

            $item->status = $status;
            if ($status === TeamOperationCenterModel::STATUS_APPROVED) {
                $item->authenticated_at = time();
                $this->ensureCurrentIssue($item);
            }
            $item->save();

            return $item->fresh(['user', 'submitUser']);
        });
    }

    /** @deprecated use changeStatus */
    public function audit(int $id, int $status, string $remark = ''): TeamOperationCenterModel
    {
        // 兼容旧前端：1=通过 → 已认证(2)，2=拒绝 → 认证失败(3)
        $mapped = match ($status) {
            1 => TeamOperationCenterModel::STATUS_APPROVED,
            2 => TeamOperationCenterModel::STATUS_REJECTED,
            default => $status,
        };
        return $this->changeStatus($id, $mapped);
    }

    /** @return array<string, mixed> */
    public function getBrokerageConfig(string $key): array
    {
        $defaults = $this->defaultBrokerageConfigs();
        if (!isset($defaults[$key])) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '配置类型无效');
        }
        $raw = $this->settingService->getValue($key, $defaults[$key]);
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : $defaults[$key];
        }
        if (!is_array($raw)) {
            $raw = $defaults[$key];
        }

        return ['key' => $key, 'items' => array_values($raw)];
    }

    /** @param list<array<string, mixed>> $items */
    public function saveBrokerageConfig(string $key, array $items): array
    {
        $defaults = $this->defaultBrokerageConfigs();
        if (!isset($defaults[$key])) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '配置类型无效');
        }

        $normalized = [];
        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            if ($key === 'center_rebate_brokerage_config') {
                $normalized[] = [
                    'recharge_num' => (float) ($row['recharge_num'] ?? 0),
                    'rate' => (float) ($row['rate'] ?? 0),
                ];
            } else {
                $normalized[] = [
                    'active_num' => (int) ($row['active_num'] ?? 0),
                    'share_num' => (int) ($row['share_num'] ?? 0),
                    'amount' => (float) ($row['amount'] ?? 0),
                ];
            }
        }

        $labels = [
            'center_dividend_brokerage_config' => '交易日分红佣金配置',
            'center_rebate_brokerage_config' => 'USD入金佣金配置',
            'center_fund_brokerage_config' => '管理发展基金佣金配置',
        ];
        $this->settingService->upsert($key, $normalized, $labels[$key] ?? $key);

        return $this->getBrokerageConfig($key);
    }

    private function ensureCurrentIssue(TeamOperationCenterModel $center): void
    {
        $issue = $this->getCurrentIssue();
        $exists = TeamOperationCenterIssueModel::where('center_id', $center->id)
            ->where('issue', $issue)
            ->exists();
        if ($exists) {
            return;
        }

        TeamOperationCenterIssueModel::create([
            'center_id' => $center->id,
            'submit_user_id' => $center->submit_user_id,
            'user_id' => $center->user_id,
            'issue' => $issue,
            'dividend' => 0,
            'rebate' => 0,
            'fund' => 0,
            'bonus' => 0,
            'sub_count' => 0,
            'sub_reward' => 0,
            'type' => $center->type,
            'status' => TeamOperationCenterIssueModel::STATUS_UNSETTLED,
        ]);
    }

    public function getCurrentIssue(): string
    {
        $year = date('Y');
        $month = date('m');
        $day = (int) date('j');

        if ($day >= 1 && $day <= 10) {
            return $year . $month . '11';
        }
        if ($day >= 11 && $day <= 20) {
            return $year . $month . '21';
        }

        $next = strtotime('first day of next month') ?: time();
        return date('Y', $next) . date('m', $next) . '01';
    }

    /** @return array<string, list<array<string, float|int>>> */
    private function defaultBrokerageConfigs(): array
    {
        return [
            'center_dividend_brokerage_config' => [
                ['active_num' => 40, 'share_num' => 5, 'amount' => 100],
                ['active_num' => 100, 'share_num' => 10, 'amount' => 200],
            ],
            'center_rebate_brokerage_config' => [
                ['recharge_num' => 10000, 'rate' => 2],
                ['recharge_num' => 50000, 'rate' => 3],
                ['recharge_num' => 100000, 'rate' => 5],
            ],
            'center_fund_brokerage_config' => [
                ['active_num' => 20, 'share_num' => 5, 'amount' => 1000],
                ['active_num' => 50, 'share_num' => 5, 'amount' => 2000],
                ['active_num' => 200, 'share_num' => 5, 'amount' => 4000],
            ],
        ];
    }

    public function formatItem(TeamOperationCenterModel $row): array
    {
        $authFiles = $this->parseAuthFiles((string) ($row->auth_data ?? ''));

        return [
            'id' => (int) $row->id,
            'user_id' => (int) $row->user_id,
            'username' => (string) ($row->user?->username ?? ''),
            'submit_user_id' => (int) ($row->submit_user_id ?? 0),
            'submit_username' => (string) ($row->submitUser?->username ?? ''),
            'type' => (int) $row->type,
            'type_text' => TeamOperationCenterModel::$typeMap[$row->type] ?? '未知',
            'auth_code' => (string) ($row->auth_code ?? ''),
            'auth_data' => (string) ($row->auth_data ?? ''),
            'auth_files' => $authFiles,
            'status' => (int) $row->status,
            'status_text' => TeamOperationCenterModel::$statusMap[$row->status] ?? '未知',
            'dividend' => (string) ($row->dividend ?? '0'),
            'rebate' => (string) ($row->rebate ?? '0'),
            'fund' => (string) ($row->fund ?? '0'),
            'bonus' => (string) ($row->bonus ?? '0'),
            'sub_count' => (int) ($row->sub_count ?? 0),
            'authenticated_at' => (int) ($row->authenticated_at ?? 0),
            'authenticated_at_text' => ((int) ($row->authenticated_at ?? 0)) > 0
                ? date('Y-m-d H:i:s', (int) $row->authenticated_at)
                : '-',
            'created_at' => (string) $row->created_at,
        ];
    }

    /** @return list<array{url:string,kind:string}> */
    private function parseAuthFiles(string $raw): array
    {
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        $urls = [];
        if (is_array($decoded)) {
            foreach (['images', 'videos', 'files', 'auth_data'] as $key) {
                if (!empty($decoded[$key]) && is_array($decoded[$key])) {
                    foreach ($decoded[$key] as $u) {
                        $urls[] = (string) $u;
                    }
                }
            }
            if (isset($decoded['url'])) {
                $urls[] = (string) $decoded['url'];
            }
            if ($urls === [] && isset($decoded[0]) && is_string($decoded[0])) {
                $urls = array_map('strval', $decoded);
            }
        } else {
            $urls = array_filter(array_map('trim', explode(',', $raw)));
        }

        $files = [];
        foreach ($urls as $url) {
            if ($url === '') {
                continue;
            }
            $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: $url, PATHINFO_EXTENSION));
            $kind = in_array($ext, ['mp4', 'webm', 'mov', 'ogg'], true) ? 'video' : 'image';
            $files[] = ['url' => $url, 'kind' => $kind];
        }

        return $files;
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
