<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\sys\AdminIpWhitelistModel;
use app\support\ErrorCode;
use app\support\Security\IpMatcher;
use support\Redis;

class AdminIpWhitelistService
{
    private const CACHE_KEY = 'admin:ip_whitelist:rules';

    public function list(int $page, int $limit, array $filters = []): array
    {
        $query = AdminIpWhitelistModel::query();

        if (!empty($filters['keyword'])) {
            $kw = $filters['keyword'];
            $query->where(function ($q) use ($kw) {
                $q->where('ip_rule', 'like', "%{$kw}%")
                    ->orWhere('remark', 'like', "%{$kw}%");
            });
        }
        if (isset($filters['enabled']) && $filters['enabled'] !== '') {
            $query->where('enabled', (int) $filters['enabled']);
        }

        $total = $query->count();
        $items = $query->orderBy('id', 'desc')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return ['total' => $total, 'items' => $items];
    }

    public function create(array $data): AdminIpWhitelistModel
    {
        $ipRule = trim((string) ($data['ip_rule'] ?? ''));
        if ($ipRule === '') {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, 'ip_rule 不能为空');
        }

        if (AdminIpWhitelistModel::where('ip_rule', $ipRule)->exists()) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, 'IP 规则已存在');
        }

        $row = AdminIpWhitelistModel::create([
            'ip_rule' => $ipRule,
            'remark' => (string) ($data['remark'] ?? ''),
            'enabled' => (int) ($data['enabled'] ?? 1),
        ]);

        $this->clearCache();

        return $row;
    }

    public function update(int $id, array $data): AdminIpWhitelistModel
    {
        $row = AdminIpWhitelistModel::find($id);
        if (!$row) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '白名单记录不存在');
        }

        $payload = [];
        if (array_key_exists('ip_rule', $data)) {
            $ipRule = trim((string) $data['ip_rule']);
            if ($ipRule === '') {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, 'ip_rule 不能为空');
            }
            $exists = AdminIpWhitelistModel::where('ip_rule', $ipRule)->where('id', '!=', $id)->exists();
            if ($exists) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, 'IP 规则已存在');
            }
            $payload['ip_rule'] = $ipRule;
        }

        if (array_key_exists('remark', $data)) {
            $payload['remark'] = (string) $data['remark'];
        }
        if (array_key_exists('enabled', $data)) {
            $payload['enabled'] = (int) $data['enabled'];
        }

        $row->update($payload);
        $this->clearCache();

        return $row;
    }

    public function delete(int $id): void
    {
        $row = AdminIpWhitelistModel::find($id);
        if (!$row) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '白名单记录不存在');
        }
        $row->delete();
        $this->clearCache();
    }

    /**
     * @return string[]
     */
    public function getEnabledRules(): array
    {
        try {
            $cached = Redis::get(self::CACHE_KEY);
            if ($cached !== false && $cached !== null) {
                $data = json_decode((string) $cached, true);
                if (is_array($data)) {
                    return array_values(array_filter(array_map('strval', $data)));
                }
            }
        } catch (\Throwable) {
            // ignore cache failure
        }

        $rules = AdminIpWhitelistModel::where('enabled', 1)
            ->orderBy('id', 'asc')
            ->pluck('ip_rule')
            ->toArray();

        $rules = array_values(array_filter(array_map('strval', $rules)));

        try {
            Redis::setex(self::CACHE_KEY, 300, json_encode($rules, JSON_UNESCAPED_UNICODE));
        } catch (\Throwable) {
            // ignore
        }

        return $rules;
    }

    public function isAllowed(string $ip): bool
    {
        $rules = $this->getEnabledRules();
        // 开关已开启但未配置任何启用规则时，默认拒绝（避免误以为开启无效）
        if ($rules === []) {
            return false;
        }

        return IpMatcher::match($ip, $rules);
    }

    public function clearCache(): void
    {
        try {
            Redis::del(self::CACHE_KEY);
        } catch (\Throwable) {
        }
    }
}

