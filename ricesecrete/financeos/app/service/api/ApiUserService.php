<?php

declare(strict_types=1);

namespace app\service\api;

use app\exception\BusinessException;
use app\model\member\PromotionRewardDayModel;
use app\model\member\UserMessageModel;
use app\model\member\UserModel;
use app\service\MemberService;
use app\service\MemberWalletService;
use app\service\MemberWaterService;
use app\service\SettingService;
use app\model\member\UserWaterModel;
use app\support\ErrorCode;
use app\support\MemberPassword;
use app\support\UserWaterDetailType;
use Illuminate\Database\Capsule\Manager as DB;

class ApiUserService
{
    public function __construct(
        private ?MemberAuthService $authService = null,
        private ?MemberService $memberService = null,
        private ?SettingService $settingService = null,
    ) {
        $this->authService = $authService ?? new MemberAuthService();
        $this->memberService = $memberService ?? new MemberService();
        $this->settingService = $settingService ?? new SettingService();
    }

    public function profile(int $userId): array
    {
        $user = $this->requireUser($userId);
        $team = $this->memberService->teamInfo($userId);
        $data = $this->authService->transformUser($user);

        $data['team_num'] = $team['total_team'];
        $data['today_team_num'] = UserModel::where('leader1', $userId)
            ->where('created_at', '>=', date('Y-m-d 00:00:00'))->count();
        $data['total_income'] = (string) UserWaterModel::where('user_id', $userId)
            ->where('direction', 1)->sum('amount');
        $data['total_brokerage_income'] = (string) UserWaterModel::where('user_id', $userId)
            ->where('type', 3)->where('direction', 1)->sum('amount');
        $data['credit_score_detail'] = ['trade' => 0, 'active' => 0, 'new' => 0];

        $siteUrl = (string) $this->settingService->getValue('site_url', '');
        $data['share_link'] = rtrim($siteUrl, '/') . '/#/register?type=3&ic=' . $user->invite_code;

        return $data;
    }

    public function changePassword(int $userId, string $oldPassword, string $newPassword): void
    {
        $user = $this->requireUser($userId);
        if (!MemberPassword::verify($oldPassword, (string) $user->password)) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '原密码错误');
        }
        $this->memberService->resetPassword($userId, $newPassword);
    }

    public function changePayPassword(int $userId, string $oldPassword, string $newPassword): void
    {
        $user = $this->requireUser($userId);
        if ($user->pay_password && !MemberPassword::verify($oldPassword, (string) $user->pay_password)) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '原安全密码错误');
        }
        $this->memberService->resetPayPassword($userId, $newPassword);
        UserModel::where('id', $userId)->update(['pay_password_changed_at' => time()]);
    }

    public function bindBank(int $userId, array $data): array
    {
        $type = $this->normalizeCardType((string) ($data['type'] ?? 'bank'));
        $this->requireUser($userId);

        $cardService = new \app\service\MemberCardService();
        if ($type === 'bank') {
            $count = \app\model\member\UserCardModel::where('user_id', $userId)->where('type', 'bank')->where('status', 1)->count();
            if ($count >= 5) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '最多绑定5张银行卡');
            }
        } elseif ($type === 'alipay') {
            if (\app\model\member\UserCardModel::where('user_id', $userId)->where('type', 'alipay')->where('status', 1)->exists()) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '支付宝账户只能绑定一个');
            }
        } elseif ($type === 'usdt') {
            if (\app\model\member\UserCardModel::where('user_id', $userId)->where('type', 'usdt')->where('status', 1)->exists()) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, 'USDT账户只能绑定一个');
            }
        }

        $card = $cardService->create([
            'user_id' => $userId,
            'type' => $type,
            'bank_name' => $data['bank_name'] ?? ($type === 'alipay' ? '支付宝' : ''),
            'bank_code' => $data['bank_code'] ?? ($type === 'alipay' ? 'Alipay' : ''),
            'card_number' => $data['card_number'] ?? $data['bank_number'] ?? '',
            'card_holder' => $data['card_holder'] ?? $data['true_name'] ?? '',
            'bank_branch' => $data['bank_branch'] ?? '',
            'bank_address' => $data['bank_address'] ?? '',
            'reserve_phone' => $data['reserve_phone'] ?? $data['mobile'] ?? '',
            'chain_type' => $data['chain_type'] ?? '',
            'is_default' => (int) ($data['is_default'] ?? 1),
            'status' => 1,
        ]);

        return $this->formatCardForApi($card->toArray());
    }

    public function bindBankList(int $userId, string $type = ''): array
    {
        $query = \app\model\member\UserCardModel::where('user_id', $userId)->where('status', 1);
        if ($type !== '') {
            $query->where('type', $this->normalizeCardType($type));
        }

        return $query->orderByDesc('is_default')->orderByDesc('id')->get()
            ->map(fn ($row) => $this->formatCardForApi($row->toArray()))
            ->values()
            ->all();
    }

    private function normalizeCardType(string $type): string
    {
        $type = strtolower(trim($type));
        return match ($type) {
            'bank', 'alipay', 'usdt' => $type,
            default => 'bank',
        };
    }

    /** @param array<string, mixed> $card */
    private function formatCardForApi(array $card): array
    {
        $type = (string) ($card['type'] ?? 'bank');
        $card['type'] = match ($type) {
            'alipay' => 'Alipay',
            'usdt' => 'USDT',
            default => 'Bank',
        };
        $card['bank_number'] = (string) ($card['card_number'] ?? '');
        $card['true_name'] = (string) ($card['card_holder'] ?? '');

        return $card;
    }

    public function signList(int $userId): array
    {
        $user = $this->requireUser($userId);
        return [
            'sign_in_days' => (int) $user->sign_in_days,
            'sign_last_at' => (int) $user->sign_last_at,
            'today_signed' => (int) $user->sign_last_at >= strtotime(date('Y-m-d')),
        ];
    }

    public function sign(int $userId): array
    {
        $user = $this->requireUser($userId);
        $today = strtotime(date('Y-m-d'));
        if ((int) $user->sign_last_at >= $today) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '今日已签到');
        }
        $days = ((int) $user->sign_last_at >= $today - 86400) ? (int) $user->sign_in_days + 1 : 1;
        $user->update(['sign_in_days' => $days, 'sign_last_at' => time()]);

        $reward = (string) $this->settingService->getValue('sign_in_reward', '1');
        if (bccomp($reward, '0', 4) > 0) {
            $pool = \app\model\member\UserBrokeragePoolModel::firstOrCreate(
                ['user_id' => $userId],
                ['receivable' => 0, 'received' => 0]
            );
            $pool->receivable = bcadd((string) $pool->receivable, $reward, 4);
            $pool->save();

            $before = (string) $user->brokerage_balance;
            $user->brokerage_balance = bcadd($before, $reward, 4);
            $user->save();

            \app\model\member\UserWaterModel::create([
                'user_id' => $userId,
                'title' => '签到奖励',
                'type' => \app\support\UserWaterType::TYPE_BROKERAGE_POOL,
                'detail_type' => UserWaterDetailType::SIGN_IN_POOL,
                'direction' => 1,
                'amount' => $reward,
                'before_balance' => $before,
                'after_balance' => (string) $user->brokerage_balance,
            ]);
        }

        return ['sign_in_days' => $days, 'reward' => $reward];
    }

    public function unreadMessageCount(int $userId): int
    {
        return UserMessageModel::where('user_id', $userId)->where('is_read', 0)->count();
    }

    public function transferMoney(int $userId, string $amount, string $password): void
    {
        $user = $this->requireUser($userId);
        if (bccomp($amount, '0', 4) <= 0) {
            throw new \app\exception\BusinessException(\app\support\ErrorCode::VALIDATION_FAILED, '请输入正确的数量');
        }
        if (!MemberPassword::verify($password, (string) $user->password)) {
            throw new \app\exception\BusinessException(\app\support\ErrorCode::VALIDATION_FAILED, '密码错误');
        }
        if (bccomp((string) $user->brokerage_balance, $amount, 4) < 0) {
            throw new \app\exception\BusinessException(\app\support\ErrorCode::VALIDATION_FAILED, '兑换金额不能大于目前账户金额');
        }
        (new \app\service\MemberWalletService())->transferBrokerageToBalance($userId, $amount);
    }

    public function promotionRewardList(int $userId): array
    {
        $config = $this->settingService->getValue('promotion_reward_days', [
            ['day' => 1, 'reward' => 10],
            ['day' => 3, 'reward' => 30],
            ['day' => 7, 'reward' => 100],
        ]);
        $records = PromotionRewardDayModel::where('user_id', $userId)->get()->keyBy('day');
        return array_map(function (array $item) use ($records) {
            $day = (int) $item['day'];
            $record = $records->get($day);
            return [
                'day' => $day,
                'reward' => $item['reward'],
                'status' => $record ? (int) $record->status : -1,
            ];
        }, is_array($config) ? $config : []);
    }

    public function receivePromotionReward(int $userId, int $day): void
    {
        DB::transaction(function () use ($userId, $day) {
            $record = PromotionRewardDayModel::query()
                ->where('user_id', $userId)
                ->where('day', $day)
                ->where('status', 0)
                ->lockForUpdate()
                ->first();

            if (!$record) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '暂无可领取奖励');
            }

            $amount = (string) $record->reward;
            if (bccomp($amount, '0', 4) > 0) {
                (new MemberWalletService())->changeBalance(
                    $userId,
                    $amount,
                    MemberWalletService::WALLET_BASE,
                    MemberWalletService::DIRECTION_IN,
                    '连续推广奖励',
                    UserWaterDetailType::PROMOTION_REWARD,
                    (int) $record->id
                );
            }

            $record->status = 1;
            $record->save();
        });
    }

    private function requireUser(int $userId): UserModel
    {
        $user = UserModel::find($userId);
        if (!$user) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '用户不存在');
        }

        return $user;
    }
}
