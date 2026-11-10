<?php

declare(strict_types=1);

namespace app\service\api;

use app\exception\BusinessException;
use app\model\activity\TurntableCountModel;
use app\model\activity\TurntableLogModel;
use app\model\activity\TurntableModel;
use app\model\activity\TurntablePrizeModel;
use app\model\member\UserModel;
use app\service\MemberWalletService;
use app\support\ErrorCode;
use Illuminate\Database\Capsule\Manager as DB;

class ApiTurntableService
{
    public function __construct(private ?MemberWalletService $walletService = null)
    {
        $this->walletService = $walletService ?? new MemberWalletService();
    }

    public function getTurntable(int $userId): array
    {
        $turntable = TurntableModel::where('status', 1)->orderByDesc('is_default')->orderByDesc('id')->first();
        if (!$turntable) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '暂无转盘活动');
        }

        $count = TurntableCountModel::firstOrCreate(
            ['user_id' => $userId, 'turntable_id' => $turntable->id],
            ['reg_num' => (int) $turntable->register_num, 'num' => 0]
        );

        $type = (int) $count->reg_num > 0 ? 1 : 2;
        $user = UserModel::find($userId);
        $levelId = (int) $turntable->prize === 1 ? -1 : (int) ($user?->level_id ?? 0);

        $prizes = TurntablePrizeModel::where('turntable_id', $turntable->id)
            ->where('status', 1)->where('class', $type)
            ->when($levelId >= 0, fn ($q) => $q->whereIn('level_id', [-1, $levelId]))
            ->orderBy('sort')->get()
            ->values()
            ->map(fn ($prize, $index) => array_merge($prize->toArray(), [
                'angle' => (($index + 1) * 45) . 'deg',
            ]));

        return [
            'turntable_count' => $count->toArray(),
            'turntable' => $turntable->toArray(),
            'turntable_prize' => $prizes,
        ];
    }

    public function spin(int $userId, int $turntableId): array
    {
        return DB::transaction(function () use ($userId, $turntableId) {
            $turntable = TurntableModel::where('id', $turntableId)->where('status', 1)->lockForUpdate()->first();
            if (!$turntable) {
                throw new BusinessException(ErrorCode::NOT_FOUND, '转盘活动不存在');
            }

            $count = TurntableCountModel::where('user_id', $userId)
                ->where('turntable_id', $turntableId)->lockForUpdate()->first();
            if (!$count) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '暂无抽奖次数');
            }

            $useReg = (int) $count->reg_num > 0;
            if ($useReg) {
                $count->reg_num = max(0, (int) $count->reg_num - 1);
            } elseif ((int) $count->num > 0) {
                $count->num = max(0, (int) $count->num - 1);
            } else {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '抽奖次数不足');
            }
            $count->save();

            $type = $useReg ? 1 : 2;
            $user = UserModel::find($userId);
            $levelId = (int) $turntable->prize === 1 ? -1 : (int) ($user?->level_id ?? 0);
            $prizes = TurntablePrizeModel::where('turntable_id', $turntableId)
                ->where('status', 1)->where('class', $type)
                ->when($levelId >= 0, fn ($q) => $q->whereIn('level_id', [-1, $levelId]))
                ->get();
            if ($prizes->isEmpty()) {
                throw new BusinessException(ErrorCode::NOT_FOUND, '奖品配置为空');
            }

            $prize = $this->drawPrize($prizes->all());
            $money = (string) ($prize->value ?? '0');
            if (bccomp($money, '0', 4) > 0) {
                $this->walletService->changeBalance(
                    $userId, $money, MemberWalletService::WALLET_BASE, MemberWalletService::DIRECTION_IN, '转盘抽奖', 5
                );
            }

            TurntableLogModel::create([
                'user_id' => $userId,
                'prize_id' => $prize->id,
                'money' => $money,
                'name' => $prize->name,
                'type' => $type,
            ]);

            return [
                'code' => 0,
                'msg' => '抽奖成功',
                'data' => [
                    'prize_id' => $prize->id,
                    'name' => $prize->name,
                    'money' => $money,
                    'img' => $prize->img,
                ],
            ];
        });
    }

    public function getLog(int $userId, int $page, int $limit): array
    {
        $query = TurntableLogModel::where('user_id', $userId);
        $total = (clone $query)->count();
        $items = $query->orderByDesc('id')->offset(($page - 1) * $limit)->limit($limit)->get();
        return ['total' => $total, 'items' => $items];
    }

    /** @param TurntablePrizeModel[] $prizes */
    private function drawPrize(array $prizes): TurntablePrizeModel
    {
        $total = array_sum(array_map(fn ($p) => (float) $p->probability, $prizes));
        $rand = mt_rand(1, max(1, (int) ($total * 10000))) / 10000;
        $cursor = 0.0;
        foreach ($prizes as $prize) {
            $cursor += (float) $prize->probability;
            if ($rand <= $cursor) {
                return $prize;
            }
        }
        return $prizes[array_key_last($prizes)];
    }
}
