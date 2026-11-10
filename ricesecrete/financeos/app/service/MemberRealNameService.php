<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\member\UserRealNameAuthModel;
use app\model\member\UserModel;
use app\support\DateTimeFormat;
use app\support\ErrorCode;

class MemberRealNameService
{
    public function list(int $page, int $limit, array $filters = []): array
    {
        $query = UserRealNameAuthModel::query()->with('user:id,username,nickname');

        if (!empty($filters['keyword'])) {
            $kw = $filters['keyword'];
            $query->where(function ($q) use ($kw) {
                $q->whereHas('user', fn ($u) => $u->where('username', 'like', "%{$kw}%")
                        ->orWhere('nickname', 'like', "%{$kw}%"))
                    ->orWhere('real_name', 'like', "%{$kw}%")
                    ->orWhere('id_card', 'like', "%{$kw}%");
            });
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int) $filters['status']);
        }

        $total = $query->count();
        $items = $query->orderBy('id', 'desc')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get()
            ->map(fn (UserRealNameAuthModel $auth) => $this->formatItem($auth))
            ->values()
            ->all();

        return ['total' => $total, 'items' => $items];
    }

    public function create(array $data, ?int $adminId = null): UserRealNameAuthModel
    {
        $userId = (int) ($data['user_id'] ?? 0);
        $realName = trim((string) ($data['real_name'] ?? ''));
        $idCard = trim((string) ($data['id_card'] ?? ''));

        if ($userId <= 0) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '请填写用户ID');
        }
        if ($realName === '') {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '请填写真实姓名');
        }
        if ($idCard === '') {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '请填写证件号');
        }
        if (!UserModel::find($userId)) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '用户不存在');
        }

        $exists = UserRealNameAuthModel::where('user_id', $userId)
            ->whereIn('status', [0, 1])
            ->exists();
        if ($exists) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '该用户已有待审核或已通过的实名记录');
        }

        $payload = [
            'user_id' => $userId,
            'real_name' => $realName,
            'id_card' => $idCard,
            'id_card_front' => (string) ($data['id_card_front'] ?? ''),
            'id_card_back' => (string) ($data['id_card_back'] ?? ''),
            'hand_hold_photo' => (string) ($data['hand_hold_photo'] ?? ''),
            'document_type' => (string) ($data['document_type'] ?? 'id_card'),
            'remark' => (string) ($data['remark'] ?? ''),
        ];

        if ($adminId !== null) {
            $payload['status'] = 1;
            $payload['admin_id'] = $adminId;
            $payload['audited_at'] = time();
            if ($payload['remark'] === '') {
                $payload['remark'] = '后台新增';
            }
        } else {
            $payload['status'] = 1;
            $payload['audited_at'] = time();
        }

        $auth = UserRealNameAuthModel::create($payload);

        if ((int) $auth->status === 1) {
            UserModel::where('id', $userId)->update([
                'kyc_status' => 1,
                'real_name' => $realName,
            ]);
        }

        return $auth;
    }

    public function update(int $id, array $data): UserRealNameAuthModel
    {
        $auth = UserRealNameAuthModel::find($id);
        if (!$auth) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '实名记录不存在');
        }
        $auth->update($data);
        return $auth;
    }

    public function delete(int $id): void
    {
        $auth = UserRealNameAuthModel::find($id);
        if (!$auth) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '实名记录不存在');
        }
        $auth->delete();
    }

    /**
     * 审核实名认证
     */
    public function audit(int $id, int $status, string $remark, int $auditorId): UserRealNameAuthModel
    {
        if (!in_array($status, [1, 2], true)) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '审核状态必须为 1(通过) 或 2(拒绝)');
        }

        $auth = UserRealNameAuthModel::find($id);
        if (!$auth) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '实名记录不存在');
        }

        if ($auth->status !== 0) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '该记录已审核');
        }

        $auth->update([
            'status'     => $status,
            'remark'     => $remark,
            'admin_id' => $auditorId,
            'audited_at' => time(),
        ]);

        // 审核通过后更新用户的 kyc_status
        if ($status === 1) {
            UserModel::where('id', $auth->user_id)->update([
                'kyc_status' => 1,
                'real_name' => (string) $auth->real_name,
            ]);
        }

        return $auth;
    }

    public function formatItem(UserRealNameAuthModel $auth): array
    {
        $user = $auth->user;
        $status = (int) $auth->status;

        return [
            'id'              => (int) $auth->id,
            'user_id'         => (int) $auth->user_id,
            'username'        => (string) ($user?->username ?? ''),
            'nickname'        => (string) ($user?->nickname ?? ''),
            'real_name'       => (string) $auth->real_name,
            'id_card'         => (string) $auth->id_card,
            'id_card_front'   => (string) $auth->id_card_front,
            'id_card_back'    => (string) $auth->id_card_back,
            'hand_hold_photo' => (string) $auth->hand_hold_photo,
            'document_type'   => (string) $auth->document_type,
            'status'          => $status,
            'status_text'     => match ($status) {
                1       => '已通过',
                2       => '已拒绝',
                default => '待审核',
            },
            'remark'          => (string) $auth->remark,
            'admin_id'        => (int) $auth->admin_id,
            'audited_at'      => DateTimeFormat::datetime($auth->audited_at),
            'created_at'      => DateTimeFormat::datetime($auth->created_at),
            'updated_at'      => DateTimeFormat::datetime($auth->updated_at),
        ];
    }
}
