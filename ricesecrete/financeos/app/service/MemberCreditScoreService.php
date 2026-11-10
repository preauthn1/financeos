<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\member\UserCreditScoreLogModel;
use app\model\member\UserCreditScoreModel;
use app\model\member\UserModel;
use app\support\DateTimeFormat;
use app\support\ErrorCode;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class MemberCreditScoreService
{
    /**
     * @var array<int, string>
     */
    public const TYPE_MAP = UserCreditScoreLogModel::TYPE_MAP;

    /**
     * 当月信誉分总览（管理页）
     */
    public function overview(int $page, int $limit, array $filters = []): array
    {
        $period = (string) ($filters['period'] ?? date('Ym'));
        $query = UserCreditScoreModel::query()
            ->with(['user:id,username,nickname,is_virtual,leader1,leader7,credit_score'])
            ->where('period', $period);

        if (!empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        $keyword = (string) ($filters['username'] ?? $filters['user_name'] ?? '');
        if ($keyword !== '') {
            if (ctype_digit($keyword)) {
                $query->where('user_id', (int) $keyword);
            } else {
                $userIds = UserModel::query()
                    ->where('username', 'like', '%' . $keyword . '%')
                    ->pluck('id');
                $query->whereIn('user_id', $userIds->isEmpty() ? [0] : $userIds);
            }
        }

        $total = (clone $query)->count();
        $rows = $query->orderByDesc('total_score')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return [
            'period' => $period,
            'total' => $total,
            'items' => $rows->map(function (UserCreditScoreModel $row): array {
                $user = $row->user;

                return [
                    'user_id' => (int) $row->user_id,
                    'user_name' => (string) ($user?->username ?? ''),
                    'nickname' => (string) ($user?->nickname ?? ''),
                    'total_score' => (int) $row->total_score,
                    'trade_score' => (int) $row->trade_score,
                    'active_score' => (int) $row->active_score,
                    'new_score' => (int) $row->new_score,
                    'credit_score' => (int) ($user?->credit_score ?? $row->total_score),
                    'is_virtual' => (int) ($user?->is_virtual ?? 0),
                    'top_leader_remark' => $this->getTopLeaderRemark($user),
                    'period' => (string) $row->period,
                ];
            })->all(),
        ];
    }

    /**
     * 信用分变动日志列表
     */
    public function list(int $page, int $limit, array $filters = []): array
    {
        $query = $this->buildLogQuery($filters);
        $total = (clone $query)->count();
        $rows = $query->orderByDesc('id')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return [
            'total' => $total,
            'items' => $rows->map(fn (UserCreditScoreLogModel $log) => $this->formatLogRow($log))->all(),
        ];
    }

    /**
     * 导出信用分日志
     *
     * @return list<array<string, mixed>>
     */
    public function exportRows(array $filters = []): array
    {
        return $this->buildLogQuery($filters)
            ->orderByDesc('id')
            ->get()
            ->map(fn (UserCreditScoreLogModel $log) => $this->formatLogRow($log))
            ->all();
    }

    /**
     * 手动调整信用分（仿 reference creditScoreAdd）
     */
    public function adjust(
        int $userId,
        int $type,
        int $score,
        string $remarks = '',
        int $adminId = 0
    ): array {
        if ($userId <= 0) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '请输入正确的用户ID');
        }
        if ($score === 0) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '请输入正确的信用分');
        }
        if (!isset(self::TYPE_MAP[$type])) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '请选择操作类型');
        }

        $user = UserModel::find($userId);
        if (!$user) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '用户不存在');
        }

        $log = $this->changeCreditScore($userId, $score, $type, $remarks, $adminId);

        return $this->formatLogRow($log);
    }

    /**
     * 重新计算本月信用分（保留兼容）
     */
    public function recalculate(string $period): int
    {
        $userIds = UserModel::where('status', 1)->pluck('id')->toArray();
        $count = 0;

        foreach ($userIds as $uid) {
            $tradeScore  = $this->calcTradeScore((int) $uid, $period);
            $activeScore = $this->calcActiveScore((int) $uid, $period);
            $newScore    = $this->calcNewScore((int) $uid, $period);
            $totalScore  = $tradeScore + $activeScore + $newScore;

            UserCreditScoreModel::updateOrCreate(
                ['user_id' => $uid, 'period' => $period],
                [
                    'total_score'  => $totalScore,
                    'trade_score'  => $tradeScore,
                    'active_score' => $activeScore,
                    'new_score'    => $newScore,
                ]
            );

            UserModel::where('id', $uid)->update(['credit_score' => $totalScore]);
            $count++;
        }

        return $count;
    }

    private function buildLogQuery(array $filters): Builder
    {
        $query = UserCreditScoreLogModel::query()
            ->with([
                'user:id,username,is_virtual,tree_id,leader7,leader1',
                'admin:id,username',
            ]);

        if (isset($filters['type']) && $filters['type'] !== '') {
            $query->where('type', (int) $filters['type']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        $username = (string) ($filters['username'] ?? $filters['user_name'] ?? '');
        if ($username !== '') {
            if (ctype_digit($username)) {
                $query->where('user_id', (int) $username);
            } else {
                $userId = UserModel::query()
                    ->where('username', 'like', '%' . $username . '%')
                    ->orWhere('mobile', $username)
                    ->value('id');
                $query->where('user_id', $userId ?? 0);
            }
        }

        $isVirtual = $filters['is_virtual'] ?? $filters['is_xuni'] ?? null;
        if ($isVirtual !== null && $isVirtual !== '' && (int) $isVirtual >= 0) {
            $query->whereHas('user', static function (Builder $builder) use ($isVirtual) {
                $builder->where('is_virtual', (int) $isVirtual);
            });
        }

        $start = (string) ($filters['start_time'] ?? $filters['startTime'] ?? '');
        $end = (string) ($filters['end_time'] ?? $filters['endTime'] ?? '');
        if ($start !== '' && $end !== '') {
            $query->whereBetween('created_at', [$start, $end]);
        }

        return $query;
    }

    private function formatLogRow(UserCreditScoreLogModel $log): array
    {
        $user = $log->user;
        $userId = (int) $log->user_id;

        return [
            'id'                => (int) $log->id,
            'user_id'           => $userId,
            'user_name'         => (string) ($user?->username ?? ''),
            'username'          => (string) ($user?->username ?? ''),
            'type'              => (int) $log->type,
            'type_str'          => self::TYPE_MAP[(int) $log->type] ?? (string) $log->type,
            'top_leader_remark' => $this->getTopLeaderRemark($user),
            'before'            => (int) $log->before,
            'score'             => (int) $log->score,
            'after'             => (int) $log->after,
            'title'             => (string) $log->title,
            'remarks'           => (string) $log->remarks,
            'admin_name'        => (string) ($log->admin?->username ?? ''),
            'tree_level'        => $this->getTreeLevel((string) ($user?->tree_id ?? '')),
            'is_virtual'        => (int) ($user?->is_virtual ?? 0),
            'is_xuni'           => (int) ($user?->is_virtual ?? 0),
            'is_xuni_text'      => ($user?->is_virtual ?? 0) ? '是' : '否',
            'create_time'       => DateTimeFormat::datetime($log->created_at),
            'created_at'        => DateTimeFormat::datetime($log->created_at),
        ];
    }

    private function changeCreditScore(
        int $userId,
        int $score,
        int $type,
        string $remarks,
        int $adminId
    ): UserCreditScoreLogModel {
        $user = UserModel::findOrFail($userId);
        $periodRecord = $this->getOrCreatePeriodRecord($userId);
        $before = (int) ($user->credit_score ?? 0);

        if ($before <= 0 && $score < 0 && !in_array($type, [UserCreditScoreLogModel::TYPE_OVERDUE], true)) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '用户信用分已达到0');
        }
        if ($before >= 100 && $score > 0) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '用户信用分已达到100');
        }

        if (in_array($type, [UserCreditScoreLogModel::TYPE_ADMIN_SUB, UserCreditScoreLogModel::TYPE_OVERDUE], true)) {
            $score = -abs($score);
        }

        $field = UserCreditScoreLogModel::TYPE_FIELD_MAP[$type] ?? null;
        if ($field !== null) {
            $typeScore = (int) ($periodRecord->{$field} ?? 0);
            if ($typeScore + $score < 0) {
                $score = -$typeScore;
            }
            if ($score === 0) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '该类型信用分已达到0分');
            }
            $periodRecord->{$field} = $typeScore + $score;
        }

        $after = $type === UserCreditScoreLogModel::TYPE_OVERDUE
            ? $before
            : max(0, min(100, $before + $score));

        if ($type !== UserCreditScoreLogModel::TYPE_OVERDUE && $after !== $before + $score) {
            $score = $after - $before;
        }

        $symbol = $score > 0 ? '增加' : '减少';
        $typeLabel = self::TYPE_MAP[$type] ?? '信用分';
        $title = $typeLabel . $symbol . '信用分';
        $autoRemark = $typeLabel . $symbol . "信用分:{$score}";
        $fullRemarks = trim($remarks) !== '' ? $autoRemark . ' ' . $remarks : $autoRemark;

        $log = UserCreditScoreLogModel::create([
            'user_id'      => $userId,
            'from_user_id' => 0,
            'before'       => $before,
            'score'        => $score,
            'after'        => $after,
            'type'         => $type,
            'value'        => 0,
            'times'        => 0,
            'admin_id'     => $adminId,
            'order_id'     => 0,
            'title'        => $title,
            'remarks'      => $fullRemarks,
            'overdue_at'   => Carbon::now()->endOfMonth(),
        ]);

        if ($type !== UserCreditScoreLogModel::TYPE_OVERDUE) {
            $periodRecord->total_score = $after;
            $periodRecord->save();
            $user->credit_score = $after;
            $user->save();
        }

        return $log->load(['user:id,username,is_virtual,tree_id,leader7,leader1', 'admin:id,username']);
    }

    private function getOrCreatePeriodRecord(int $userId): UserCreditScoreModel
    {
        $period = date('Ym');

        return UserCreditScoreModel::firstOrCreate(
            ['user_id' => $userId, 'period' => $period],
            [
                'total_score'  => 0,
                'trade_score'  => 0,
                'active_score' => 0,
                'new_score'    => 0,
            ]
        );
    }

    private function getTopLeaderRemark(?UserModel $user): string
    {
        if (!$user) {
            return '';
        }

        $topId = (int) ($user->leader7 ?: $user->leader1);
        if ($topId <= 0) {
            return '';
        }

        $leader = UserModel::query()->find($topId, ['id', 'username', 'remark']);

        return UserModel::displayLabel($leader, $topId);
    }

    private function getTreeLevel(string $treeId): int
    {
        if ($treeId === '') {
            return 0;
        }

        preg_match_all('/\d+/', $treeId, $matches);

        return count($matches[0] ?? []);
    }

    private function calcTradeScore(int $uid, string $period): int
    {
        return 20;
    }

    private function calcActiveScore(int $uid, string $period): int
    {
        return 30;
    }

    private function calcNewScore(int $uid, string $period): int
    {
        return 10;
    }
}
