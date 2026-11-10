<?php

declare(strict_types=1);

namespace app\service\api;

use app\exception\BusinessException;
use app\service\SettingService;
use app\service\api\FinanceProductLevelConfigService;
use app\support\ErrorCode;

class ApiLevelService
{
    public function __construct(private ?SettingService $settingService = null)
    {
        $this->settingService = $settingService ?? new SettingService();
    }

    public function upLevel(int $userId, int $levelId): array
    {
        if ($levelId <= 0) {
            return ['code' => -1, 'msg' => '请选择广告位'];
        }

        $result = (new FinanceProductVipUpgradeService())->upgradeBeforeMatch($userId, $levelId);
        if (in_array((int) $result['code'], [1, 13], true)) {
            return ['code' => 0, 'msg' => '成功', 'level_id' => $levelId];
        }

        return ['code' => (int) $result['code'], 'msg' => (string) $result['msg']];
    }

    public function checkPassword(string $password, int $levelId, int $type = 0): array
    {
        if ($password === '') {
            return ['code' => -1, 'msg' => '请输入编码'];
        }

        return (new FinanceProductLevelConfigService())->checkPassword($password, $levelId, $type, -1);
    }
}
