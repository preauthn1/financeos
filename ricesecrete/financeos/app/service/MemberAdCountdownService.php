<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\member\UserAdCountdownConfigModel;
use app\model\member\UserModel;
use app\support\ErrorCode;

class MemberAdCountdownService
{
    public function getConfig(int $userId): array
    {
        $row = UserAdCountdownConfigModel::where('user_id', $userId)->first();

        $buttonDuration = (int) ($row?->button_duration ?? 0);
        $cancelDuration = (int) ($row?->cancel_duration ?? 0);

        return [
            'user_id' => $userId,
            'finance_product_no_can_click' => (int) ($row?->can_click_on_no ?? 0),
            'finance_product_yes_can_click' => (int) ($row?->can_click_on_yes ?? 0),
            'finance_product_button_count_down' => $buttonDuration,
            'finance_product_dialog_btn_cancel' => $cancelDuration,
            'apply_to_subordinates' => (int) ($row?->apply_to_subordinates ?? 0),
            'enabled' => $row !== null && ($buttonDuration > 0 || $cancelDuration > 0) ? 1 : 0,
            'updated_at' => $row?->updated_at,
        ];
    }

    public function saveConfig(int $userId, array $data): array
    {
        if (!UserModel::find($userId)) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '用户不存在');
        }

        $row = UserAdCountdownConfigModel::updateOrCreate(
            ['user_id' => $userId],
            [
                'can_click_on_no' => (int) ($data['finance_product_no_can_click'] ?? 0),
                'can_click_on_yes' => (int) ($data['finance_product_yes_can_click'] ?? 0),
                'button_duration' => (int) ($data['finance_product_button_count_down'] ?? 3),
                'cancel_duration' => (int) ($data['finance_product_dialog_btn_cancel'] ?? 3),
                'apply_to_subordinates' => (int) ($data['apply_to_subordinates'] ?? 0),
            ]
        );

        return $this->getConfig((int) $row->user_id);
    }

    /**
     * 沿 tree_id 向上查找生效的缓冲倒计时配置（参考 FinanceProduct::overdue_config）
     */
    public function resolveForUser(int $userId): ?UserAdCountdownConfigModel
    {
        $user = UserModel::find($userId);
        if (!$user || $user->tree_id === '') {
            return null;
        }

        $treeIds = array_values(array_filter(explode('/', trim((string) $user->tree_id, '/'))));
        $treeIds = array_reverse($treeIds);

        foreach ($treeIds as $treeId) {
            $config = UserAdCountdownConfigModel::where('user_id', (int) $treeId)->first();
            if (!$config) {
                continue;
            }
            if ((int) $treeId === $userId || (int) $config->apply_to_subordinates === 1) {
                return $config;
            }
        }

        return null;
    }
}
