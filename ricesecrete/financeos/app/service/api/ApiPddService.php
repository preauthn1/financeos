<?php

declare(strict_types=1);

namespace app\service\api;

use app\support\DateTimeFormat;
use app\model\activity\PddTurntableCountModel;
use app\model\activity\PddTurntableModel;
use app\model\activity\PddTurntableRecordModel;
use app\model\member\UserModel;
use app\service\MemberWalletService;
use app\support\ErrorCode;
use Illuminate\Database\Capsule\Manager as DB;

class ApiPddService
{
    public function __construct(private ?MemberWalletService $walletService = null)
    {
        $this->walletService = $walletService ?? new MemberWalletService();
    }

    public function getTurntable(int $userId): array
    {
        $now = time();
        $activity = PddTurntableModel::where('status', 1)
            ->where('started_at', '<', $now)->where('ended_at', '>', $now)
            ->orderByDesc('id')->first();
        if (!$activity) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '暂无进行中的抽奖活动');
        }

        $lastRecord = PddTurntableRecordModel::where('user_id', $userId)
            ->where('turntable_id', $activity->id)->where('is_big_prize', 1)
            ->orderByDesc('id')->first();

        if ($lastRecord && (int) $lastRecord->is_receive === 0
            && (DateTimeFormat::unix($lastRecord->created_at) + (int) $lastRecord->task_period * 3600) > $now) {
            return array_merge($lastRecord->toArray(), [
                'progress_model' => 1,
                'user_id' => $userId,
                'title' => $activity->title,
            ]);
        }

        $config = is_array($activity->config) ? $activity->config : [];
        $prizes = $config['prizes'] ?? [];

        return [
            'progress_model' => 0,
            'id' => $activity->id,
            'title' => $activity->title,
            'user_id' => $userId,
            'prizes' => $prizes,
            'available_count' => PddTurntableCountModel::availableCount($userId),
        ];
    }

    public function draw(int $userId, int $turntableId): array
    {
        return DB::transaction(function () use ($userId, $turntableId) {
            if (PddTurntableCountModel::availableCount($userId) <= 0) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '暂无可用抽奖次数');
            }

            $activity = PddTurntableModel::where('id', $turntableId)->where('status', 1)->lockForUpdate()->first();
            if (!$activity) {
                throw new BusinessException(ErrorCode::NOT_FOUND, '活动不存在');
            }

            $countRow = PddTurntableCountModel::where('user_id', $userId)
                ->where('turntable_id', 0)
                ->orderBy('id')
                ->lockForUpdate()
                ->first();
            if (!$countRow) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '暂无可用抽奖次数');
            }

            $config = is_array($activity->config) ? $activity->config : [];
            $prizes = $config['prizes'] ?? [['id' => 1, 'name' => '谢谢参与', 'amount' => 0, 'is_big_prize' => 0]];
            $prize = $prizes[array_rand($prizes)];

            $record = PddTurntableRecordModel::create([
                'user_id' => $userId,
                'turntable_id' => $turntableId,
                'prize_id' => (int) ($prize['id'] ?? 0),
                'prize_name' => (string) ($prize['name'] ?? ''),
                'amount' => (string) ($prize['amount'] ?? '0'),
                'progress' => (float) ($prize['progress'] ?? random_int(1, 30)),
                'is_big_prize' => (int) ($prize['is_big_prize'] ?? 0),
                'is_receive' => 0,
                'task_period' => (int) ($config['task_period'] ?? 24),

            ]);

            $countRow->update([
                'turntable_id' => $turntableId,
                'prize_id' => (int) ($prize['id'] ?? 0),
                'record_id' => (int) $record->id,
            ]);

            return $record->toArray();
        });
    }

    public function updateBigPrizeProgress(int $userId, int $recordId, float $progress): array
    {
        $record = PddTurntableRecordModel::where('id', $recordId)->where('user_id', $userId)->first();
        if (!$record) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '记录不存在');
        }
        $record->update(['progress' => min(100, max((float) $record->progress, $progress))]);
        return $record->fresh()->toArray();
    }

    public function receiveBigPrize(int $userId, int $recordId): void
    {
        DB::transaction(function () use ($userId, $recordId) {
            $record = PddTurntableRecordModel::where('id', $recordId)->where('user_id', $userId)->lockForUpdate()->first();
            if (!$record || (int) $record->is_receive === 1) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '奖励已领取或不存在');
            }
            if ((float) $record->progress < 100) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '进度未完成');
            }
            $amount = (string) $record->amount;
            if (bccomp($amount, '0', 4) > 0) {
                $this->walletService->changeBalance(
                    $userId, $amount, MemberWalletService::WALLET_BASE, MemberWalletService::DIRECTION_IN, 'PDD大奖', 5
                );
            }
            $record->update(['is_receive' => 1]);
        });
    }

    public function records(int $userId, int $page, int $limit, string $type = 'draw'): array
    {
        $query = PddTurntableRecordModel::where('user_id', $userId);
        if ($type === 'receive') {
            $query->where('is_receive', 1);
        }
        $total = (clone $query)->count();
        $items = $query->orderByDesc('id')->offset(($page - 1) * $limit)->limit($limit)->get();
        return ['total' => $total, 'items' => $items];
    }

    public function invitedRecords(int $userId, int $page, int $limit): array
    {
        $ids = UserModel::where('leader1', $userId)->orderByDesc('id')
            ->offset(($page - 1) * $limit)->limit($limit)->pluck('id');
        return ['total' => UserModel::where('leader1', $userId)->count(), 'items' => $ids];
    }

    public function financeProductRecords(int $userId, int $page, int $limit): array
    {
        $query = PddTurntableCountModel::where('user_id', $userId)->where('from_user_id', $userId);
        $total = (clone $query)->count();
        $items = $query->orderByDesc('id')->offset(($page - 1) * $limit)->limit($limit)->get();
        return ['total' => $total, 'items' => $items];
    }
}
