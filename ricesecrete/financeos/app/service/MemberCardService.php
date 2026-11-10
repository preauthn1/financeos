<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\member\UserCardModel;
use app\support\ErrorCode;

class MemberCardService
{
    public function list(int $page, int $limit, array $filters = []): array
    {
        $query = UserCardModel::query()->with('user:id,username,nickname');

        if (!empty($filters['keyword'])) {
            $kw = $filters['keyword'];
            $query->where(function ($q) use ($kw) {
                $q->whereHas('user', fn ($u) => $u->where('username', 'like', "%{$kw}%")
                        ->orWhere('nickname', 'like', "%{$kw}%"))
                    ->orWhere('card_number', 'like', "%{$kw}%")
                    ->orWhere('card_holder', 'like', "%{$kw}%");
            });
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        $total = $query->count();
        $items = $query->orderBy('id', 'desc')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return ['total' => $total, 'items' => $items];
    }

    public function create(array $data): UserCardModel
    {
        return UserCardModel::create($data);
    }

    public function update(int $id, array $data): UserCardModel
    {
        $card = UserCardModel::find($id);
        if (!$card) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '银行卡不存在');
        }
        $card->update($data);
        return $card;
    }

    public function delete(int $id): void
    {
        $card = UserCardModel::find($id);
        if (!$card) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '银行卡不存在');
        }
        $card->delete();
    }
}
