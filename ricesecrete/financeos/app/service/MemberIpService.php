<?php

declare(strict_types=1);

namespace app\service;

use app\model\member\UserIpModel;
use app\model\member\UserLogModel;
use app\model\member\UserModel;
use app\support\DateTimeFormat;
use app\support\IpLocationResolver;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\Builder;

class MemberIpService
{
    /** @var list<string> */
    private const EXCLUDED_IPS = [
        '203.170.142.100', '203.170.142.101', '171.102.239.157', '171.102.239.156',
        '203.170.142.107', '110.78.118.35', '110.78.118.36', '203.170.225.197',
        '203.170.142.185', '203.170.142.184', '203.170.225.194', '203.156.12.21',
        '171.102.10.168',
    ];

    /**
     * IP 监控列表（仿 reference member_ip/index）
     */
    public function monitorList(int $page, int $limit, array $filters = []): array
    {
        $query = $this->buildMonitorQuery($filters);

        $total = (clone $query)->count();
        $rows = $query->orderByDesc('a.ip_count')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        $items = [];
        foreach ($rows as $row) {
            $ip = (string) $row->ip;
            $userId = (int) $row->user_id;
            $items[] = [
                'user_id'            => $userId,
                'user_name'          => (string) ($row->user_name ?? ''),
                'username'           => (string) ($row->user_name ?? ''),
                'top_leader_remark'  => $this->getTopLeaderRemark($userId),
                'ip'                 => $ip,
                'area'               => IpLocationResolver::resolveOrStored($ip, (string) ($row->area ?? '')),
                'device'             => (string) ($row->device ?? ''),
                'uuid'               => (string) ($row->uuid ?? ''),
                'ip_count'           => $this->countLoginUsersByIp($ip),
                'latest_login_time'  => DateTimeFormat::datetime($row->latest_login_time ?? null),
                'created_at'         => DateTimeFormat::datetime($row->latest_login_time ?? null),
            ];
        }

        return ['total' => $total, 'items' => $items];
    }

    /**
     * 同 IP 用户（点击同 IP 数量时）
     *
     * @return list<array<string, mixed>>
     */
    public function getSameIpUsers(int $userId, string $ip = ''): array
    {
        if ($ip === '' && $userId > 0) {
            $ips = UserIpModel::where('user_id', $userId)->pluck('ip')->unique()->all();
            if ($ips === []) {
                return [];
            }
            $records = UserIpModel::with('user:id,username')
                ->whereIn('ip', $ips)
                ->where('user_id', '!=', $userId)
                ->orderByDesc('id')
                ->get();
        } else {
            if ($ip === '') {
                return [];
            }
            $query = UserIpModel::with('user:id,username')->where('ip', $ip);
            if ($userId > 0) {
                $query->where('user_id', '<>', $userId);
            }
            $records = $query->orderByDesc('id')->get();
        }

        return $records->map(static function (UserIpModel $record) {
            return [
                'id'         => (int) $record->id,
                'user_id'    => (int) $record->user_id,
                'user_name'  => (string) ($record->user?->username ?? ''),
                'username'   => (string) ($record->user?->username ?? ''),
                'ip'         => (string) $record->ip,
                'create_time'=> DateTimeFormat::datetime($record->created_at),
                'created_at' => DateTimeFormat::datetime($record->created_at),
            ];
        })->values()->all();
    }

    /**
     * IP 风险分析（保留兼容）
     */
    public function analysis(int $page, int $limit, array $filters = []): array
    {
        $sharedIps = UserIpModel::query()
            ->select('ip', DB::raw('COUNT(DISTINCT user_id) as user_count'))
            ->groupBy('ip')
            ->having('user_count', '>', 1);

        if (!empty($filters['ip'])) {
            $sharedIps->where('ip', 'like', '%' . $filters['ip'] . '%');
        }

        $minCount = match ($filters['risk'] ?? '') {
            'high' => 10,
            'watch', 'medium' => 5,
            default => 2,
        };
        $sharedIps->having('user_count', '>=', $minCount);

        $ipList = $sharedIps->orderByDesc('user_count')->pluck('user_count', 'ip');
        $total = $ipList->count();
        $pagedIps = $ipList->forPage($page, $limit);

        $items = [];
        foreach ($pagedIps as $ip => $count) {
            $riskLevel = $count >= 10 ? 'high' : ($count >= 5 ? 'watch' : 'normal');
            $userIds = UserIpModel::where('ip', $ip)->pluck('user_id')->unique()->all();
            $users = $this->getUsersByIds($userIds);
            $lastLogin = UserLogModel::where('ip', $ip)->max('created_at');

            $items[] = [
                'ip'               => $ip,
                'user_count'       => $count,
                'risk_level'       => $riskLevel,
                'risk'             => $riskLevel,
                'last_login_at'    => $lastLogin,
                'last_activity'    => DateTimeFormat::datetime($lastLogin),
                'location'         => '',
                'proxy_line_count' => count(array_filter(array_unique(array_column($users, 'leader1')))),
                'users'            => array_map(static fn ($u) => [
                    'id'         => $u['id'],
                    'username'   => $u['username'] ?? '',
                    'nickname'   => $u['nickname'] ?? '',
                    'level_id'   => $u['level_id'] ?? 0,
                    'is_virtual' => $u['is_virtual'] ?? 0,
                    'status'     => $u['status'] ?? 0,
                ], $users),
            ];
        }

        return [
            'total' => $total,
            'items' => $items,
            'stats' => [
                'total_ips'     => $ipList->count(),
                'high_risk_ips' => $ipList->filter(static fn ($c) => $c >= 10)->count(),
                'watch_ips'     => $ipList->filter(static fn ($c) => $c >= 5 && $c < 10)->count(),
                'multi_account' => $ipList->filter(static fn ($c) => $c >= 5)->count(),
            ],
        ];
    }

    /**
     * 用户登录记录列表
     */
    public function logList(int $page, int $limit, array $filters = []): array
    {
        $query = UserLogModel::query();

        if (!empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (!empty($filters['ip'])) {
            $query->where('ip', 'like', '%' . $filters['ip'] . '%');
        }

        if (isset($filters['type']) && $filters['type'] !== '') {
            $query->where('type', (int) $filters['type']);
        }

        if (!empty($filters['start_date']) || !empty($filters['start_time'])) {
            $start = (string) ($filters['start_date'] ?? $filters['start_time']);
            $query->where('created_at', '>=', $start);
        }
        if (!empty($filters['end_date']) || !empty($filters['end_time'])) {
            $end = (string) ($filters['end_date'] ?? $filters['end_time']);
            $query->where('created_at', '<=', str_contains($end, ':') ? $end : $end . ' 23:59:59');
        }

        $total = $query->count();
        $items = $query->orderByDesc('id')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get()
            ->map(function ($log) {
                $sameIpCount = UserLogModel::query()
                    ->where('ip', $log->ip)
                    ->where('type', 0)
                    ->distinct()
                    ->count('user_id');

                return [
                    'id'            => $log->id,
                    'user_id'       => $log->user_id,
                    'ip'            => $log->ip,
                    'area'          => IpLocationResolver::resolveOrStored((string) $log->ip, (string) $log->area),
                    'location'      => IpLocationResolver::resolveOrStored((string) $log->ip, (string) $log->area),
                    'type'          => $log->type,
                    'device'        => $log->device,
                    'browser'       => $log->device,
                    'uuid'          => $log->uuid,
                    'same_ip_count' => max(0, $sameIpCount - 1),
                    'created_at'    => DateTimeFormat::datetime($log->created_at),
                ];
            });

        return ['total' => $total, 'items' => $items];
    }

    private function buildMonitorQuery(array $filters): Builder
    {
        $sharedIpsQuery = UserIpModel::query()
            ->select('ip')
            ->selectRaw('COUNT(DISTINCT user_id) as ip_count')
            ->whereNotIn('ip', self::EXCLUDED_IPS)
            ->groupBy('ip')
            ->havingRaw('COUNT(DISTINCT user_id) > 1');

        $latestAggSub = UserLogModel::query()
            ->select('user_id', 'ip')
            ->selectRaw('MAX(created_at) as latest_login_time')
            ->where('type', 0)
            ->groupBy('user_id', 'ip');

        $latestLoginSub = DB::query()
            ->fromSub($latestAggSub, 'latest')
            ->join('me_user_log as l', function ($join) {
                $join->on('l.user_id', '=', 'latest.user_id')
                    ->on('l.ip', '=', 'latest.ip')
                    ->on('l.created_at', '=', 'latest.latest_login_time')
                    ->where('l.type', '=', 0);
            })
            ->select([
                'l.user_id',
                'l.ip',
                'l.device',
                'l.uuid',
                'l.area',
                'latest.latest_login_time',
            ]);

        $query = DB::query()
            ->fromSub($sharedIpsQuery, 'a')
            ->joinSub($latestLoginSub, 'b', 'a.ip', '=', 'b.ip')
            ->join('me_user as c', 'c.id', '=', 'b.user_id')
            ->where('b.user_id', '>', 0)
            ->select([
                'a.ip',
                'a.ip_count',
                'b.user_id',
                'b.latest_login_time',
                'b.device',
                'b.uuid',
                'b.area',
                'c.username as user_name',
            ]);

        if (!empty($filters['user_id'])) {
            $query->where('b.user_id', (int) $filters['user_id']);
        }

        if (!empty($filters['ip'])) {
            $query->where('a.ip', (string) $filters['ip']);
        }

        if (!empty($filters['uuid'])) {
            $query->where('b.uuid', (string) $filters['uuid']);
        }

        if (!empty($filters['user_name']) || !empty($filters['username'])) {
            $username = (string) ($filters['user_name'] ?? $filters['username']);
            $query->where('c.username', 'like', '%' . $username . '%');
        }

        $start = (string) ($filters['start_time'] ?? $filters['startTime'] ?? '');
        $end = (string) ($filters['end_time'] ?? $filters['endTime'] ?? '');
        if ($start !== '' && $end !== '') {
            $query->whereBetween('b.latest_login_time', [$start, $end]);
        }

        return $query;
    }

    private function countLoginUsersByIp(string $ip): int
    {
        return (int) UserLogModel::query()
            ->where('ip', $ip)
            ->where('type', 0)
            ->distinct()
            ->count('user_id');
    }

    private function getTopLeaderRemark(int $userId): string
    {
        $user = UserModel::query()->find($userId, ['leader7', 'leader1']);
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

    /**
     * @param list<int> $ids
     * @return list<array<string, mixed>>
     */
    private function getUsersByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return UserModel::whereIn('id', $ids)
            ->get(['id', 'username', 'nickname', 'level_id', 'is_virtual', 'status', 'leader1'])
            ->map(static fn (UserModel $u) => $u->toArray())
            ->all();
    }
}
