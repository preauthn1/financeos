<?php

declare(strict_types=1);

namespace app\service;

use app\model\sys\AdminLogModel;
use app\support\DateTimeFormat;

class LogService
{
    public function list(int $page, int $limit, array $filters = []): array
    {
        $query = AdminLogModel::query();

        if (!empty($filters['admin_id'])) {
            $query->where('admin_id', $filters['admin_id']);
        }
        if (!empty($filters['module'])) {
            $query->where('module', $filters['module']);
        }
        if (!empty($filters['start_date'])) {
            $query->where('created_at', '>=', strtotime($filters['start_date'] . ' 00:00:00'));
        }
        if (!empty($filters['end_date'])) {
            $query->where('created_at', '<=', strtotime($filters['end_date'] . ' 23:59:59'));
        }

        $total = $query->count();
        $items = $query->with('admin:id,username,nickname')
            ->orderBy('id', 'desc')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'admin_id' => $log->admin_id,
                    'admin_name' => $log->admin?->username ?? '',
                    'module' => $log->module,
                    'action' => $log->action,
                    'description' => $log->description,
                    'request_data' => $log->request_data,
                    'ip' => $log->ip,
                    'created_at' => DateTimeFormat::datetime($log->created_at),
                ];
            });

        return ['total' => $total, 'items' => $items];
    }

    public function record(
        int $adminId,
        string $module,
        string $action,
        string $description = '',
        ?array $requestData = null,
        string $ip = '',
        string $userAgent = ''
    ): void {
        AdminLogModel::create([
            'admin_id' => $adminId,
            'module' => $module,
            'action' => $action,
            'description' => $description,
            'request_data' => $requestData,
            'ip' => $ip,
            'user_agent' => $userAgent,
        ]);
    }

    public function recordLoginAttempt(
        string $username,
        bool $success,
        ?int $adminId = null,
        string $ip = '',
        string $userAgent = '',
        string $reason = ''
    ): void {
        $this->record(
            adminId: $adminId ?? 0,
            module: 'auth',
            action: $success ? 'login_success' : 'login_failed',
            description: $success
                ? "用户 {$username} 登录成功"
                : "用户 {$username} 登录失败" . ($reason !== '' ? "：{$reason}" : ''),
            requestData: [
                'username' => $username,
                'success' => $success,
                'reason' => $reason,
            ],
            ip: $ip,
            userAgent: $userAgent
        );
    }
}
