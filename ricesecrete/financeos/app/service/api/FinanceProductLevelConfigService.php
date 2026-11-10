<?php

declare(strict_types=1);

namespace app\service\api;

use app\exception\FinanceProductMatchException;
use app\model\member\UserAdRatioConfigModel;
use app\model\ad\AdConfigModel;
use app\service\AdConfigService;
use app\service\SettingService;
use app\support\ErrorCode;

class FinanceProductLevelConfigService
{
    public const TYPE_EXPERIENCE = 1;
    public const TYPE_NORMAL = 2;
    public const TYPE_PROMOTION = 3;
    public const TYPE_ACTIVITY = 4;

    public function __construct(private ?SettingService $settingService = null)
    {
        $this->settingService = $settingService ?? new SettingService();
    }

    public function getLevel(int $levelId): ?array
    {
        foreach ($this->getLevels() as $level) {
            if ((int) ($level['level_id'] ?? 0) === $levelId) {
                return $level;
            }
        }
        return null;
    }

    /** @return array<int, array<string, mixed>> */
    public function getLevels(): array
    {
        if (AdConfigModel::query()->exists()) {
            return (new AdConfigService())->runtimeLevels(true);
        }

        $levels = $this->settingService->getValue('finance_product_levels', []);
        return is_array($levels) ? $levels : [];
    }

    /** @return array<int, array<string, mixed>> */
    public function getAllLevels(): array
    {
        if (AdConfigModel::query()->exists()) {
            return (new AdConfigService())->runtimeLevels(false);
        }

        return $this->getLevels();
    }

    public function getCurrentTaskSession(int $levelId): ?array
    {
        $level = $this->getLevel($levelId);
        if (!$level) {
            return null;
        }

        $now = date('H:i:s');
        $type = (int) ($level['type'] ?? self::TYPE_NORMAL);
        foreach ($this->getTaskSessions() as $session) {
            $types = (string) ($session['level_types'] ?? '');
            if ($types !== '' && !str_contains($types, ',' . $type . ',')) {
                continue;
            }
            $start = (string) ($session['started_at'] ?? '00:00:00');
            $end = (string) ($session['ended_at'] ?? '23:59:59');
            if ($start <= $now && $now <= $end) {
                return $session;
            }
        }
        return null;
    }

    /** @return array<int, array<string, mixed>> */
    public function getTaskSessions(): array
    {
        $sessions = $this->settingService->getValue('finance_product_task_sessions', [
            ['id' => 1, 'started_at' => '00:00:00', 'ended_at' => '23:59:59', 'level_types' => ',1,2,3,4,'],
        ]);
        return is_array($sessions) ? $sessions : [];
    }

    public function canUnlockAtCurrentTime(array $level): bool
    {
        $start = (string) ($level['unlock_time_start'] ?? '00:00:00');
        $end = (string) ($level['unlock_time_end'] ?? '23:59:59');
        $now = date('H:i:s');
        return $start <= $now && $now <= $end;
    }

    public function checkPassword(string $password, int $levelId, int $type, int $errorCode = -1): array
    {
        if (in_array($type, [self::TYPE_NORMAL, self::TYPE_PROMOTION], true)) {
            $level = $this->getLevelByType($type);
            return [
                'code' => 0,
                'expire_time' => (int) ($level['password_expired_at'] ?? 0),
                'time_left' => max(0, (int) ($level['password_expired_at'] ?? 0) - time()),
                'type' => $type,
            ];
        }

        // 体验/活动区：按 type 取可购买配置校验密码（与参考 LevelRepository::checkPassword 一致）
        $candidates = [];
        if ($type > 0) {
            $byType = $this->getLevelByType($type);
            if ($byType !== []) {
                $candidates[] = $byType;
            }
        } else {
            $byId = $this->getLevel($levelId);
            if ($byId !== null) {
                $candidates[] = $byId;
            }
            foreach ([self::TYPE_EXPERIENCE, self::TYPE_ACTIVITY] as $t) {
                $row = $this->getLevelByType($t);
                if ($row !== []) {
                    $candidates[] = $row;
                }
            }
        }

        $level = null;
        foreach ($candidates as $item) {
            $expected = (string) ($item['invest_password'] ?? '');
            if ($expected === '') {
                continue;
            }
            if (hash_equals($expected, $password)) {
                $level = $item;
                break;
            }
        }

        if ($level === null) {
            // 兼容 settings 兜底密码
            $expected = '';
            $passwords = $this->settingService->getValue('finance_product_level_passwords', []);
            if (is_array($passwords)) {
                $expected = (string) ($passwords[$levelId] ?? '');
            }
            if ($expected === '') {
                $expected = (string) $this->settingService->getValue('finance_product_password', '');
            }
            if ($expected === '' || !hash_equals($expected, $password)) {
                throw new FinanceProductMatchException($errorCode, '编码不正确');
            }
            $level = $this->getLevel($levelId) ?? $this->getLevelByType($type ?: self::TYPE_EXPERIENCE);
        }

        $expire = (int) ($level['password_expired_at'] ?? 0);
        if ($expire > 0 && $expire < time()) {
            throw new FinanceProductMatchException($errorCode, '编码已过期');
        }

        return [
            'code' => 0,
            'expire_time' => $expire,
            'time_left' => max(0, $expire - time()),
            'type' => (int) ($level['type'] ?? $type),
        ];
    }

    public function getUserRatioConfig(int $userId, int $levelId): ?UserAdRatioConfigModel
    {
        return UserAdRatioConfigModel::where('user_id', $userId)->where('level_id', $levelId)->first();
    }

    public function getTeamRatioConfig(int $userId, int $levelId): ?UserAdRatioConfigModel
    {
        $user = \app\model\member\UserModel::find($userId);
        if (!$user || !$user->tree_id) {
            return null;
        }
        $treeIds = array_reverse(array_filter(explode('/', trim((string) $user->tree_id, '/'))));
        foreach ($treeIds as $treeId) {
            $config = UserAdRatioConfigModel::where('user_id', (int) $treeId)
                ->where('level_id', $levelId)->first();
            if (!$config) {
                continue;
            }
            $ratios = array_filter((array) $config->ratio);
            if ($ratios === []) {
                continue;
            }
            if ((int) $treeId === $userId || (int) $config->apply_to_subordinates === 1) {
                return $config;
            }
        }
        return null;
    }

    private function getLevelByType(int $type): array
    {
        $fallback = [];
        foreach ($this->getAllLevels() as $level) {
            if ((int) ($level['type'] ?? 0) !== $type) {
                continue;
            }
            // 优先可购买配置（参考 getLevelByType）
            if ((int) ($level['is_can_buy'] ?? 1) === 1) {
                return $level;
            }
            if ($fallback === []) {
                $fallback = $level;
            }
        }
        return $fallback;
    }
}
