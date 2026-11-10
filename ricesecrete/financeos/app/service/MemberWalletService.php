<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\member\UserModel;
use app\model\member\UserWaterModel;
use app\support\ErrorCode;
use app\support\UserWaterType;
use Illuminate\Database\Capsule\Manager as DB;

class MemberWalletService
{
    public const WALLET_BASE = UserWaterType::TYPE_USER;
    public const WALLET_RECHARGE = UserWaterType::TYPE_RECHARGE;
    public const WALLET_TYJ = UserWaterType::TYPE_TYJ;
    public const WALLET_FROZEN = UserWaterType::TYPE_FROZEN;
    public const WALLET_BROKERAGE_POOL = UserWaterType::TYPE_BROKERAGE_POOL;

    /** @deprecated 使用 WALLET_BROKERAGE_POOL */
    public const WALLET_BROKERAGE = self::WALLET_BROKERAGE_POOL;

    public const DIRECTION_IN = 1;
    public const DIRECTION_OUT = 2;

    public function changeBalance(
        int $userId,
        string $amount,
        int $walletType,
        int $direction,
        string $title,
        ?int $detailType = null,
        int $orderId = 0,
        string $remark = ''
    ): void {
        if (bccomp($amount, '0', 4) <= 0) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '金额无效');
        }

        DB::transaction(function () use ($userId, $amount, $walletType, $direction, $title, $detailType, $orderId, $remark) {
            $user = UserModel::lockForUpdate()->find($userId);
            if (!$user) {
                throw new BusinessException(ErrorCode::NOT_FOUND, '用户不存在');
            }

            $field = match ($walletType) {
                self::WALLET_RECHARGE => 'recharge_balance',
                self::WALLET_TYJ => 'trial_balance',
                self::WALLET_BROKERAGE_POOL => 'brokerage_balance',
                self::WALLET_FROZEN => 'frozen_balance',
                default => 'balance',
            };

            $before = (string) $user->{$field};
            if ($direction === self::DIRECTION_OUT && bccomp($before, $amount, 4) < 0) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '余额不足');
            }

            $after = $direction === self::DIRECTION_IN
                ? bcadd($before, $amount, 4)
                : bcsub($before, $amount, 4);

            $user->{$field} = $after;
            $user->save();

            UserWaterModel::create([
                'user_id' => $userId,
                'order_no'       => $orderId,
                'title' => $title,
                'type' => $walletType,
                'detail_type' => $detailType,
                'direction' => $direction,
                'amount' => $amount,
                'before_balance' => $before,
                'after_balance' => $after,
                'remark' => $remark,
            ]);
        });
    }

    public function transferBrokerageToBalance(int $userId, string $amount): void
    {
        DB::transaction(function () use ($userId, $amount) {
            $this->changeBalance($userId, $amount, self::WALLET_BROKERAGE_POOL, self::DIRECTION_OUT, '佣金转余额', 20);
            $this->changeBalance($userId, $amount, self::WALLET_BASE, self::DIRECTION_IN, '佣金转余额', 20);
        });
    }
}
