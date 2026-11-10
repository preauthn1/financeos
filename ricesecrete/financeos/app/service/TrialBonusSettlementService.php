<?php

declare(strict_types=1);

namespace app\service;

use app\model\activity\ActivityRecordModel;
use app\model\member\UserModel;
use app\support\UserWaterDetailType;
use Illuminate\Database\Capsule\Manager as DB;
use support\Log;

class TrialBonusSettlementService
{
    public function __construct(private ?MemberWalletService $walletService = null)
    {
        $this->walletService = $walletService ?? new MemberWalletService();
    }

    /**
     * 体验金到期回收
     */
    public function returnExpired(): int
    {
        $now = time();
        $records = ActivityRecordModel::query()
            ->where('reward_type', 3)
            ->where('status', 1)
            ->where('returned_at', '>', 0)
            ->where('returned_at', '<', $now)
            ->limit(200)
            ->get();

        $count = 0;
        foreach ($records as $record) {
            try {
                DB::transaction(function () use ($record, $now) {
                    $locked = ActivityRecordModel::query()
                        ->where('id', $record->id)
                        ->where('status', 1)
                        ->lockForUpdate()
                        ->first();

                    if (!$locked || (int) $locked->returned_at >= $now) {
                        return;
                    }

                    $userId = (int) $locked->user_id;
                    $amount = (string) $locked->reward;

                    if (bccomp($amount, '0', 4) > 0) {
                        $user = UserModel::where('id', $userId)->lockForUpdate()->first();
                        if ($user) {
                            $available = (string) $user->trial_balance;
                            $deduct = bccomp($available, $amount, 4) >= 0 ? $amount : $available;
                            if (bccomp($deduct, '0', 4) > 0) {
                                $this->walletService->changeBalance(
                                    $userId,
                                    $deduct,
                                    MemberWalletService::WALLET_TYJ,
                                    MemberWalletService::DIRECTION_OUT,
                                    '体验金到期退回',
                                    UserWaterDetailType::TRIAL_BONUS_RETURN,
                                    (int) $locked->id,
                                    '体验金到期退回'
                                );
                            }
                        }
                    }

                    $locked->status = 2;
                    $locked->save();

                    UserModel::where('id', $userId)->update(['withdraw_status' => 1]);
                });
                $count++;
            } catch (\Throwable $e) {
                Log::error('trial bonus return failed: ' . $e->getMessage(), ['record_id' => $record->id]);
            }
        }

        return $count;
    }
}
