<?php

declare(strict_types=1);

namespace app\service;

use app\model\member\UserModel;

class MemberWoolPartyService
{
    /**
     * @return array{total: int, items: list<array<string, mixed>>, stats: array<string, int>}
     */
    public function list(int $page, int $limit, string $riskType = 'all', array $filters = []): array
    {
        $query = UserModel::query()->where('is_virtual', 0);

        if (!empty($filters['keyword'])) {
            $kw = (string) $filters['keyword'];
            $query->where(function ($q) use ($kw) {
                if (ctype_digit($kw)) {
                    $q->where('id', (int) $kw);
                } else {
                    $q->where('username', 'like', "%{$kw}%")
                        ->orWhere('mobile', 'like', "%{$kw}%")
                        ->orWhere('invite_code', 'like', "%{$kw}%");
                }
            });
        }

        $this->applyRiskFilter($query, $riskType);

        $total = (clone $query)->count();
        $items = $query->orderByDesc('id')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get()
            ->map(fn (UserModel $user) => $this->formatRow($user));

        return [
            'total' => $total,
            'items' => $items->all(),
            'stats' => $this->buildStats(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function exportRows(string $riskType = 'all', array $filters = [], int $limit = 10000): array
    {
        $query = UserModel::query()->where('is_virtual', 0);

        if (!empty($filters['keyword'])) {
            $kw = (string) $filters['keyword'];
            $query->where(function ($q) use ($kw) {
                if (ctype_digit($kw)) {
                    $q->where('id', (int) $kw);
                } else {
                    $q->where('username', 'like', "%{$kw}%")
                        ->orWhere('mobile', 'like', "%{$kw}%")
                        ->orWhere('invite_code', 'like', "%{$kw}%");
                }
            });
        }

        $this->applyRiskFilter($query, $riskType);

        return $query->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (UserModel $user) => $this->formatRow($user))
            ->all();
    }

    private function applyRiskFilter($query, string $riskType): void
    {
        match ($riskType) {
            'withdraw_gt_recharge' => $query->whereColumn('total_withdraw', '>', 'total_recharge'),
            'withdraw_no_recharge' => $query->where('total_recharge', '<=', 0)->where('total_withdraw', '>', 0),
            'frozen_balance' => $query->where('frozen_balance', '>', 0),
            'never_login' => $query->where(function ($q) {
                $q->whereNull('last_login_at')->orWhere('last_login_at', 0);
            }),
            default => $query->where(function ($q) {
                $q->whereColumn('total_withdraw', '>', 'total_recharge')
                    ->orWhere(function ($sub) {
                        $sub->where('total_recharge', '<=', 0)->where('total_withdraw', '>', 0);
                    })
                    ->orWhere('frozen_balance', '>', 0);
            }),
        };
    }

    private function buildStats(): array
    {
        $base = UserModel::query()->where('is_virtual', 0);

        return [
            'suspected_total' => (clone $base)->where(function ($q) {
                $q->whereColumn('total_withdraw', '>', 'total_recharge')
                    ->orWhere(function ($sub) {
                        $sub->where('total_recharge', '<=', 0)->where('total_withdraw', '>', 0);
                    })
                    ->orWhere('frozen_balance', '>', 0);
            })->count(),
            'withdraw_gt_recharge' => (clone $base)->whereColumn('total_withdraw', '>', 'total_recharge')->count(),
            'withdraw_no_recharge' => (clone $base)->where('total_recharge', '<=', 0)->where('total_withdraw', '>', 0)->count(),
            'frozen_balance' => (clone $base)->where('frozen_balance', '>', 0)->count(),
        ];
    }

    private function formatRow(UserModel $user): array
    {
        $recharge = (float) $user->total_recharge;
        $withdraw = (float) $user->total_withdraw;
        $diff = $recharge - $withdraw;

        $riskTags = [];
        if ($withdraw > $recharge) {
            $riskTags[] = '提现大于充值';
        }
        if ($recharge <= 0 && $withdraw > 0) {
            $riskTags[] = '未充值已提现';
        }
        if ((float) $user->frozen_balance > 0) {
            $riskTags[] = '存在冻结金额';
        }

        return [
            'id' => (int) $user->id,
            'username' => (string) $user->username,
            'invite_code' => (string) $user->invite_code,
            'total_recharge' => (string) $user->total_recharge,
            'total_withdraw' => (string) $user->total_withdraw,
            'recharge_withdraw_diff' => number_format($diff, 4, '.', ''),
            'balance' => (string) $user->balance,
            'frozen_balance' => (string) $user->frozen_balance,
            'credit_score' => (int) ($user->credit_score ?? 0),
            'last_login_ip' => (string) ($user->last_login_ip ?? ''),
            'registered_at_text' => $user->registered_at ? date('Y-m-d H:i:s', (int) $user->registered_at) : '-',
            'risk_tags' => $riskTags,
        ];
    }
}
