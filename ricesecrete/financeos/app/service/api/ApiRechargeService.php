<?php

declare(strict_types=1);

namespace app\service\api;

use app\exception\BusinessException;
use app\model\member\UserModel;
use app\model\pay\PayPlatformModel;
use app\model\pay\RechargeOrderModel;
use app\service\PayPlatformService;
use app\service\SettingService;
use app\support\ErrorCode;

class ApiRechargeService
{
    public function __construct(
        private ?PayPlatformService $platformService = null,
        private ?SettingService $settingService = null,
    ) {
        $this->platformService = $platformService ?? new PayPlatformService();
        $this->settingService = $settingService ?? new SettingService();
    }

    public function channels(int $userId): array
    {
        $isFirst = !RechargeOrderModel::where('user_id', $userId)
            ->where('status', RechargeOrderModel::STATUS_SUCCESS)
            ->where('order_source', RechargeOrderModel::FROM_FRONT)->exists();

        $platforms = PayPlatformModel::where('status', 1)
            ->where('recharge_is_show', 1)
            ->when(
                $isFirst,
                fn ($q) => $q->where('is_first_charge', 1),
                fn ($q) => $q->where('is_first_charge', 0)
            )
            ->orderBy('sort')
            ->get();

        $rebate = $this->settingService->getValue('first_charge_rebate', []);
        if (!is_array($rebate)) {
            $rebate = [];
        }

        $offlineAccounts = $this->settingService->getValue('offline_pay_account', []);
        if (!is_array($offlineAccounts)) {
            $offlineAccounts = [];
        }

        $items = [];
        foreach ($platforms as $p) {
            $row = $p->toArray();
            $config = is_array($p->config) ? $p->config : [];
            $row['name'] = $p->show_name ?: $p->name;
            $row['image'] = $p->image_url;
            $row['first_charge_rebate_min'] = $rebate['min'] ?? 0;
            $row['first_charge_rebate_max'] = $rebate['max'] ?? 0;
            $row['first_charge_rebate_percent'] = $rebate['percent'] ?? 0;
            $row['first_charge_rebate_cap'] = $rebate['cap'] ?? 0;
            $isOfflinePay = (($config['class_name'] ?? '') === 'Offlinepay')
                || (int) $p->currency_type === PayPlatformModel::CURRENCY_BANK;
            $row['offline_pay_account'] = $isOfflinePay ? $offlineAccounts : [];
            $items[] = $row;
        }

        return $items;
    }

    public function createOrder(int $userId, int $platformId, string $money, string $ip = ''): array
    {
        $platform = PayPlatformModel::where('id', $platformId)->where('status', 1)->first();
        if (!$platform) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '支付通道不存在');
        }
        if ((float) $money < (float) $platform->min_recharge_amount) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '充值金额低于最低限额');
        }

        $user = UserModel::find($userId);
        $orderNo = 'R' . date('YmdHis') . random_int(1000, 9999);
        $config = is_array($platform->config) ? $platform->config : [];
        $type = match ((int) $platform->currency_type) {
            PayPlatformModel::CURRENCY_CRYPTO => RechargeOrderModel::TYPE_CONTRACT,
            PayPlatformModel::CURRENCY_BANK => RechargeOrderModel::TYPE_OFFLINE,
            default => RechargeOrderModel::TYPE_THIRD,
        };

        $order = RechargeOrderModel::create([
            'order_no' => $orderNo,
            'user_id' => $userId,
            'is_virtual' => (int) ($user?->is_virtual ?? 0),
            'money' => $money,
            'original_amount' => $money,
            'exchange_rate' => $platform->exchange_rate ?: 1,
            'type' => $type,
            'status' => RechargeOrderModel::STATUS_WAIT,
            'pay_platform_id' => $platformId,
            'order_source' => RechargeOrderModel::FROM_FRONT,
            'ip' => $ip,
            'coin' => $platform->coin,
            'to_address' => (string) ($config['address'] ?? $config['recharge_address'] ?? ''),
        ]);

        $payUrl = (string) ($config['pay_url'] ?? $config['url'] ?? '');

        return [
            'order_no' => $order->order_no,
            'money' => (string) $order->money,
            'to_address' => $order->to_address,
            'recharge_address' => $order->to_address,
            'url' => $payUrl,
            'pay_url' => $payUrl,
            'instruction' => $platform->instruction,
            'platform' => $platform->only(['id', 'name', 'show_name', 'coin', 'image_url']),
        ];
    }

    /**
     * 线下充值提交凭证 → 生成待审核订单（status=0）
     */
    public function offlinePay(int $userId, array $data): array
    {
        $platformId = (int) ($data['platform_id'] ?? $data['pay_platform_id'] ?? 0);
        $money = (string) ($data['money'] ?? '0');
        $img = (string) ($data['img'] ?? $data['voucher_url'] ?? $data['image'] ?? '');
        $channel = (string) ($data['channel'] ?? '');
        $orderNo = trim((string) ($data['order_no'] ?? ''));

        // 兼容：已有订单号时仅补凭证并转待审核
        if ($orderNo !== '') {
            $order = RechargeOrderModel::where('order_no', $orderNo)
                ->where('user_id', $userId)
                ->first();
            if (!$order) {
                throw new BusinessException(ErrorCode::NOT_FOUND, '订单不存在');
            }
            if (!in_array((int) $order->status, [RechargeOrderModel::STATUS_PENDING, RechargeOrderModel::STATUS_WAIT], true)) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '订单状态不可提交凭证');
            }
            $order->update([
                'status' => RechargeOrderModel::STATUS_PENDING,
                'voucher_url' => $img !== '' ? $img : (string) $order->voucher_url,
                'transaction_hash' => (string) ($data['transaction_hash'] ?? $order->transaction_hash),
                'from_address' => (string) ($data['from_address'] ?? $order->from_address),
                'pay_account' => (string) ($data['pay_account'] ?? $order->pay_account),
                'user_note' => (string) ($data['user_note'] ?? $order->user_note),
                'result' => $channel !== '' ? $channel : (string) $order->result,
            ]);

            return ['order_no' => $order->order_no, 'status' => (int) $order->status];
        }

        if ($img === '') {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '请上传支付凭证');
        }
        if ($platformId <= 0 || (float) $money <= 0) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '通道和金额必填');
        }

        $channelArr = json_decode($channel, true);
        if (!is_array($channelArr) || !isset($channelArr['channel'], $channelArr['type'], $channelArr['name'], $channelArr['account'])) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '支付方式参数错误');
        }

        $platform = PayPlatformModel::where('id', $platformId)->where('status', 1)->first();
        if (!$platform) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '支付通道不存在');
        }
        if ((float) $money < (float) $platform->min_recharge_amount) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '充值金额低于最低限额');
        }

        $user = UserModel::find($userId);
        if (!$user) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '用户不存在');
        }

        $pendingExists = RechargeOrderModel::where('user_id', $userId)
            ->where('type', RechargeOrderModel::TYPE_OFFLINE)
            ->where('status', RechargeOrderModel::STATUS_PENDING)
            ->whereDate('created_at', date('Y-m-d'))
            ->exists();
        if ($pendingExists) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '当前有1笔充值订单未完成，请查看充值记录完成或撤销');
        }

        $order = RechargeOrderModel::create([
            'order_no' => 'R' . date('YmdHis') . random_int(1000, 9999),
            'user_id' => $userId,
            'is_virtual' => (int) $user->is_virtual,
            'money' => $money,
            'original_amount' => $money,
            'exchange_rate' => $platform->exchange_rate ?: 1,
            'type' => RechargeOrderModel::TYPE_OFFLINE,
            'status' => RechargeOrderModel::STATUS_PENDING,
            'pay_platform_id' => $platformId,
            'order_source' => RechargeOrderModel::FROM_FRONT,
            'ip' => (string) ($data['ip'] ?? ''),
            'coin' => $platform->coin,
            'voucher_url' => $img,
            'pay_account' => (string) ($channelArr['account'] ?? ''),
            'user_note' => (string) ($data['user_note'] ?? ''),
            'result' => $channel,
        ]);

        return ['order_no' => $order->order_no, 'status' => (int) $order->status];
    }

    public function userOrders(int $userId, int $page, int $limit): array
    {
        $query = RechargeOrderModel::where('user_id', $userId)->where('order_source', RechargeOrderModel::FROM_FRONT);
        $total = (clone $query)->count();
        $items = $query->orderByDesc('id')->offset(($page - 1) * $limit)->limit($limit)->get();

        return ['total' => $total, 'items' => $items];
    }
}
