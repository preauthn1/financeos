<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\member\UserModel;
use app\model\service\ServiceCenterApplicationModel;
use app\model\service\ServiceCenterModel;
use app\support\ErrorCode;

class ServiceCenterService
{
    public function list(int $page, int $limit, array $filters = []): array
    {
        $query = ServiceCenterModel::query();
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int) $filters['status']);
        } elseif (empty($filters['admin_all'])) {
            $query->where('status', 1);
        }
        if (!empty($filters['keyword'])) {
            $kw = (string) $filters['keyword'];
            $query->where(fn ($q) => $q->where('name', 'like', "%{$kw}%")->orWhere('address', 'like', "%{$kw}%"));
        }
        $total = (clone $query)->count();
        $items = $query->orderBy('sort')->orderByDesc('id')
            ->offset(($page - 1) * $limit)->limit($limit)->get();

        return ['total' => $total, 'items' => $items];
    }

    public function show(int $id): array
    {
        $item = ServiceCenterModel::find($id);
        if (!$item) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '服务中心不存在');
        }

        return $item->toArray();
    }

    public function create(array $data): ServiceCenterModel
    {
        return ServiceCenterModel::create($data);
    }

    public function update(int $id, array $data): ServiceCenterModel
    {
        $item = ServiceCenterModel::find($id);
        if (!$item) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '服务中心不存在');
        }
        $item->fill($data)->save();

        return $item->fresh();
    }

    public function delete(int $id): void
    {
        $item = ServiceCenterModel::find($id);
        if (!$item) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '服务中心不存在');
        }
        $item->delete();
    }

    public function applyList(int $page, int $limit, array $filters = []): array
    {
        $query = ServiceCenterApplicationModel::query()->with(['user:id,username,nickname', 'serviceCenter:id,name']);
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int) $filters['status']);
        }
        $total = (clone $query)->count();
        $items = $query->orderByDesc('id')->offset(($page - 1) * $limit)->limit($limit)->get();

        return ['total' => $total, 'items' => $items];
    }

    public function apply(int $userId, int $centerId, array $data): ServiceCenterApplicationModel
    {
        $center = ServiceCenterModel::where('id', $centerId)->where('status', 1)->first();
        if (!$center) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '服务中心不存在');
        }

        $pending = ServiceCenterApplicationModel::where('user_id', $userId)
            ->where('status', ServiceCenterApplicationModel::STATUS_PENDING)->exists();
        if ($pending) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '已有待审核申请');
        }

        return ServiceCenterApplicationModel::create([
            'service_center_id' => $centerId,
            'user_id' => $userId,
            'auth_code' => (string) ($data['auth_code'] ?? ''),
            'auth_id' => (string) ($data['auth_id'] ?? ''),
            'media' => $data['media'] ?? [],
            'status' => ServiceCenterApplicationModel::STATUS_PENDING,
        ]);
    }

    public function audit(int $id, int $status, string $remark = ''): ServiceCenterApplicationModel
    {
        if (!in_array($status, [ServiceCenterApplicationModel::STATUS_APPROVED, ServiceCenterApplicationModel::STATUS_REJECTED], true)) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '审核状态无效');
        }

        $app = ServiceCenterApplicationModel::find($id);
        if (!$app) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '申请不存在');
        }

        $app->status = $status;
        $app->remark = $remark;
        $app->audited_at = time();
        $app->save();

        if ($status === ServiceCenterApplicationModel::STATUS_APPROVED) {
            UserModel::where('id', $app->user_id)->update(['studio_certified' => 1, 'kyc_status' => 1]);
        }

        return $app->fresh();
    }
}
