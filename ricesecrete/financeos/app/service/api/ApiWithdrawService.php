<?php

declare(strict_types=1);

namespace app\service\api;

use app\exception\BusinessException;
use app\model\member\UserCardModel;
use app\model\member\UserModel;
use app\model\member\UserWaterModel;
use app\model\pay\PayPlatformModel;
use app\model\pay\WithdrawOrderModel;
use app\service\MemberWalletService;
use app\service\PayPlatformService;
use app\service\SettingService;
use app\service\WithdrawLevelService;
use app\support\ErrorCode;
use app\support\MemberPassword;
use app\support\UserWaterType;
use Illuminate\Database\Capsule\Manager as DB;

class ApiWithdrawService
{
    public function __construct(
        private ?PayPlatformService $platformService = null,
        private ?SettingService $settingService = null,
        private ?WithdrawLevelService $withdrawLevelService = null,
    ) {
        $this->platformService = $platformService ?? new PayPlatformService();
        $this->settingService = $settingService ?? new SettingService();
        $this->withdrawLevelService = $withdrawLevelService ?? new WithdrawLevelService();
    }

    public function config(int $userId): array
    {
        $user = UserModel::find($userId);
        $platforms = $this->platformService->options(false, true);
        $feeRatePercent = (float) $this->withdrawLevelService->getFeeRate($userId, '0');
        $feeConfig = $this->normalizeArraySetting(
            $this->settingService->getValue('withdraw_fee', ['rate' => 0, 'fixed' => 0])
        );
        $withdrawLevels = $this->withdrawLevelService->getConfig();

        return [
            'platforms' => $platforms,
            'bank_platforms' => $platforms,
            'balance' => (string) ($user?->balance ?? 0),
            'brokerage_balance' => (string) ($user?->brokerage_balance ?? 0),
            'withdraw_status' => (int) ($user?->withdraw_status ?? 1),
            'withdraw_level' => (int) ($user?->withdraw_level ?? 0),
            'withdraw_levels' => $withdrawLevels,
            'deposit_upgrade_enabled' => (int) ($withdrawLevels['deposit_upgrade_enabled'] ?? 0),
            // 百分比，如 5 表示 5%
            'fee_rate' => $feeRatePercent,
            // 兼容前端：小数费率，如 0.05 表示 5%
            'withdraw_rate' => round($feeRatePercent / 100, 6),
            'withdraw_rate_method' => (int) $this->settingService->getValue('withdraw_rate_method', 0),
            'fee_fixed' => (float) ($feeConfig['fixed'] ?? 0),
            'free_withdraw_fee_num' => (int) $this->settingService->getValue('free_withdraw_fee_num', 0),
            'min_withdraw' => PayPlatformModel::where('withdraw_is_show', 1)->min('min_withdraw_amount'),
            'max_withdraw' => PayPlatformModel::where('withdraw_is_show', 1)->max('max_withdraw_amount'),
        ];
    }

    private function normalizeArraySetting(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($value) ? $value : [];
    }

    public function store(int $userId, array $data, string $ip = ''): array
    {
        $amount = (string) ($data['money'] ?? $data['amount'] ?? '0');
        if (bccomp($amount, '0', 4) <= 0) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '提现金额无效');
        }

        $payPassword = (string) ($data['pay_password'] ?? $data['paypwd'] ?? $data['password'] ?? '');
        if ($payPassword === '') {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '请输入安全密码');
        }
        return DB::transaction(function () use ($userId, $data, $amount, $payPassword, $ip) {
            $user = UserModel::where('id', $userId)->lockForUpdate()->first();
            if (!$user || (int) $user->withdraw_status !== 1) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '提现功能已关闭');
            }
            if (!$this->verifyPayPassword($user, $payPassword)) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '安全密码错误');
            }
            $changedAt = (int) ($user->pay_password_changed_at ?? 0);
            if ($changedAt > 0 && time() - $changedAt < 86400) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '支付密码修改后24小时内无法提现，请稍后再试');
            }
            if (bccomp((string) $user->balance, $amount, 4) < 0) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '余额不足');
            }

            $cardId = (int) ($data['card_id'] ?? 0);
            $card = $cardId > 0
                ? UserCardModel::where('id', $cardId)->where('user_id', $userId)->first()
                : null;

            // 兼容 H5：无 card_id 时用 bank_number 匹配，再回落默认卡
            if (!$card) {
                $bankNumber = (string) ($data['bank_number'] ?? '');
                if ($bankNumber !== '') {
                    $card = UserCardModel::where('user_id', $userId)
                        ->where('card_number', $bankNumber)
                        ->where('status', 1)
                        ->orderByDesc('is_default')
                        ->first();
                }
            }
            if (!$card) {
                $card = UserCardModel::where('user_id', $userId)
                    ->where('status', 1)
                    ->whereIn('type', ['bank', 'alipay'])
                    ->orderByDesc('is_default')
                    ->orderByDesc('id')
                    ->first();
            }

            $trueName = trim((string) ($card?->card_holder ?: ($data['true_name'] ?? '')));
            $bankNumber = trim((string) ($card?->card_number ?: ($data['bank_number'] ?? '')));
            $bankBranch = trim((string) ($card?->bank_branch ?? ''));
            $cardType = (string) ($card?->type ?: ($data['type'] ?? 'bank'));
            $bankName = trim((string) ($card?->bank_name ?: ''));
            if ($bankName === '') {
                $reqBankName = trim((string) ($data['bank_name'] ?? ''));
                // 排除前端误把通道名/通道代码当成银行名
                if ($reqBankName !== '' && !in_array(strtolower($reqBankName), ['bank', 'alipay'], true)) {
                    $bankName = $reqBankName;
                }
            }
            $bankCode = trim((string) ($card?->bank_code ?: ($data['bank_code'] ?? '')));

            // 银行卡/支付宝提现必须有收款账号
            $platformCurrency = strtolower((string) ($data['bank_code'] ?? $data['currency_code'] ?? ''));
            $needCard = in_array($platformCurrency, ['bank', 'alipay'], true)
                || in_array(strtolower($cardType), ['bank', 'alipay'], true);
            if ($needCard && ($bankNumber === '' || $trueName === '')) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '请先绑定提现账号');
            }

            $feeConfig = $this->normalizeArraySetting(
                $this->settingService->getValue('withdraw_fee', ['rate' => 0, 'fixed' => 0])
            );
            $feeRate = $this->withdrawLevelService->getFeeRate($userId, $amount);
            $feeFixed = (float) ($feeConfig['fixed'] ?? 0);
            $handlingFee = bcadd(bcmul($amount, (string) ($feeRate / 100), 4), (string) $feeFixed, 4);
            $actual = bcsub($amount, $handlingFee, 4);
            if (bccomp($actual, '0', 4) <= 0) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '提现金额扣除手续费后无效');
            }

            $orderNo = 'W' . date('YmdHis') . random_int(1000, 9999);
            $order = WithdrawOrderModel::create([
                'order_no' => $orderNo,
                'user_id' => $userId,
                'is_virtual' => (int) $user->is_virtual,
                'withdraw_amount' => $actual,
                'original_amount' => $amount,
                'handling_fee' => $handlingFee,
                'status' => WithdrawOrderModel::STATUS_PENDING,
                'type' => $this->mapCardType($cardType),
                'true_name' => $trueName,
                'bank_name' => $bankName,
                'bank_code' => $bankCode,
                'bank_number' => $bankNumber,
                'bank_branch' => $bankBranch,
                'withdrawal_address' => $bankNumber !== ''
                    ? $bankNumber
                    : (string) ($data['withdraw_address'] ?? $data['bank_number'] ?? ''),
                'ip' => $ip,
                'remark' => (string) ($data['remark'] ?? ''),
            ]);

            // 申请即扣余额并冻结（与参考系统一致），使用 query update 避免 decimal cast 未标记 dirty
            $this->freezeOnApply($user, $amount, (int) $order->id);

            return ['order_no' => $order->order_no, 'status' => $order->status, 'withdraw_amount' => (string) $order->withdraw_amount];
        });
    }

    private function freezeOnApply(UserModel $user, string $amount, int $orderId): void
    {
        $userId = (int) $user->id;
        $before = (string) $user->balance;
        $after = bcsub($before, $amount, 4);
        $frozenBefore = (string) $user->frozen_balance;
        $frozenAfter = bcadd($frozenBefore, $amount, 4);

        $affected = UserModel::where('id', $userId)->update([
            'balance' => $after,
            'frozen_balance' => $frozenAfter,
        ]);
        if ($affected < 1) {
            throw new BusinessException(ErrorCode::INTERNAL_ERROR, '余额扣减失败');
        }

        $user->balance = $after;
        $user->frozen_balance = $frozenAfter;

        UserWaterModel::create([
            'user_id' => $userId,
            'order_no' => $orderId,
            'title' => '提现申请',
            'type' => UserWaterType::TYPE_USER,
            'detail_type' => 2,
            'direction' => MemberWalletService::DIRECTION_OUT,
            'amount' => $amount,
            'before_balance' => $before,
            'after_balance' => $after,
            'remark' => '提现申请扣减余额',
            'is_virtual' => (int) $user->is_virtual,
        ]);
        UserWaterModel::create([
            'user_id' => $userId,
            'order_no' => $orderId,
            'title' => '提现冻结',
            'type' => UserWaterType::TYPE_FROZEN,
            'detail_type' => 2,
            'direction' => MemberWalletService::DIRECTION_IN,
            'amount' => $amount,
            'before_balance' => $frozenBefore,
            'after_balance' => $frozenAfter,
            'remark' => '提现申请冻结',
            'is_virtual' => (int) $user->is_virtual,
        ]);
    }

    public function depositUpgrade(int $userId, int $targetLevel): array
    {
        return $this->withdrawLevelService->depositUpgrade($userId, $targetLevel);
    }

    private function verifyPayPassword(UserModel $user, string $plain): bool
    {
        $hash = (string) ($user->pay_password ?? '');
        if ($hash === '' || $plain === '') {
            return false;
        }

        return MemberPassword::verify($plain, $hash);
    }

    private function mapCardType(string $type): int
    {
        $type = strtolower(trim($type));
        return match ($type) {
            'usdt', 'trc20', 'erc20', 'bep20' => WithdrawOrderModel::TYPE_CRYPTO,
            'alipay' => WithdrawOrderModel::TYPE_ALIPAY,
            default => WithdrawOrderModel::TYPE_BANK,
        };
    }
}
