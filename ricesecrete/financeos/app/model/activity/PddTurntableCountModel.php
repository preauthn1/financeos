<?php

declare(strict_types=1);

namespace app\model\activity;

use app\model\Concerns\DefinesTableSchema;
use app\model\ad\AdProductOrderModel;
use app\model\member\UserModel;
use Illuminate\Database\Eloquent\Model;

class PddTurntableCountModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'act_pdd_turntable_count';

    protected $fillable = [
        'user_id', 'from_user_id', 'turntable_id', 'prize_id', 'record_id',
        'order_id', 'session_no', 'value', 'type',
    ];

    public static function tableSchema(): array
    {
        return [
            'table' => 'act_pdd_turntable_count',
            'comment' => 'PDD转盘抽奖次数记录',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'user_id' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '获得次数用户ID'],
                'from_user_id' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '来源用户ID'],
                'turntable_id' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '转盘ID'],
                'prize_id' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '奖项ID'],
                'record_id' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '抽奖记录ID'],
                'order_id' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '来源订单ID'],
                'session_no' => ['type' => 'string', 'length' => 64, 'default' => '', 'comment' => '广告位批次'],
                'value' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '值'],
                'type' => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '1金额 2进度'],
            ],
            'timestamps'     => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [
                ['columns' => ['user_id']],
                ['columns' => ['from_user_id']],
                ['columns' => ['created_at']],
            ],
        ];
    }

    public static function addCount(int $userId, AdProductOrderModel $order, ?array $taskSession): bool
    {
        if (!$taskSession) {
            return false;
        }

        [$start, $end] = [
            strtotime(date('Y-m-d') . ' ' . ($taskSession['started_at'] ?? '00:00:00')),
            strtotime(date('Y-m-d') . ' ' . ($taskSession['ended_at'] ?? '23:59:59')),
        ];

        $hasOrder = AdProductOrderModel::where('user_id', $userId)
            ->whereBetween('matched_at', [$start, $end])
            ->exists();
        if (!$hasOrder) {
            return false;
        }

        $exists = self::where('user_id', $userId)
            ->where('from_user_id', $userId)
            ->whereBetween('created_at', [date('Y-m-d H:i:s', $start), date('Y-m-d H:i:s', $end)])
            ->exists();
        if ($exists) {
            return false;
        }

        self::create([
            'user_id' => $userId,
            'from_user_id' => $userId,
            'order_id' => $order->id,
            'session_no' => (string) $order->session_no,
        ]);

        return true;
    }

    public static function availableCount(int $userId): int
    {
        return self::where('user_id', $userId)->where('turntable_id', 0)->count();
    }

    public static function addLeader1Count(int $userId, float $amount): bool
    {
        $threshold = (float) (new \app\service\SettingService())->getValue('recharge_num_valid_user', 0);
        if ($threshold > 0 && $amount < $threshold) {
            return false;
        }

        $user = UserModel::find($userId);
        if (!$user || (int) $user->leader1 <= 0) {
            return false;
        }

        $hasOrder = AdProductOrderModel::where('user_id', $userId)
            ->whereIn('status', [AdProductOrderModel::STATUS_BOUGHT, AdProductOrderModel::STATUS_SETTLED])
            ->exists();
        if (!$hasOrder) {
            return false;
        }

        $exists = self::where('user_id', (int) $user->leader1)
            ->where('from_user_id', $userId)
            ->exists();
        if ($exists) {
            return false;
        }

        self::create([
            'user_id' => (int) $user->leader1,
            'from_user_id' => $userId,
        ]);

        return true;
    }
}
