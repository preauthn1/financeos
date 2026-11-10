<?php

declare(strict_types=1);

namespace app\service\api;

use app\exception\BusinessException;
use app\model\member\UserBrokeragePoolModel;
use app\model\member\UserModel;
use app\support\ErrorCode;
use Illuminate\Database\Capsule\Manager as DB;

class ApiBrokeragePoolService
{
    public function index(int $userId): array
    {
        $pool = UserBrokeragePoolModel::firstOrCreate(
            ['user_id' => $userId],
            ['receivable' => 0, 'received' => 0]
        );
        $pending = max(0, (float) $pool->receivable - (float) $pool->received);
        return [
            'receivable' => (string) $pool->receivable,
            'received' => (string) $pool->received,
            'pending' => (string) $pending,
        ];
    }

    public function receive(int $userId): array
    {
        return DB::transaction(function () use ($userId) {
            $pool = UserBrokeragePoolModel::where('user_id', $userId)->lockForUpdate()->first();
            if (!$pool) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '暂无可领取佣金');
            }
            $pending = bcsub((string) $pool->receivable, (string) $pool->received, 4);
            if (bccomp($pending, '0', 4) <= 0) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '暂无可领取佣金');
            }

            $user = UserModel::where('id', $userId)->lockForUpdate()->first();
            $user->brokerage_balance = bcadd((string) $user->brokerage_balance, $pending, 4);
            $user->save();

            $pool->received = bcadd((string) $pool->received, $pending, 4);
            $pool->save();

            return ['received' => $pending];
        });
    }
}
