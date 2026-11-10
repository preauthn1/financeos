<?php

declare(strict_types=1);

namespace app\service\api;

use app\exception\BusinessException;
use app\exception\FinanceProductMatchException;
use app\model\ad\AdAdvertisNumModel;
use app\model\ad\AdProductOrderModel;
use app\queue\redis\FinanceProductMatchConsumer;
use app\service\SettingService;
use app\support\ApiSignHelper;
use app\support\ErrorCode;
use app\support\FinanceProductMatchResult;
use app\support\FinanceProductPopupConfig;
use support\Redis;
use Webman\RedisQueue\Client;

class ApiFinanceProductService
{
    private const MATCH_LOCK_PREFIX = 'finance_product_match_lock:';

    public function __construct(
        private ?SettingService $settingService = null,
        private ?FinanceProductMatchService $matchService = null,
        private ?FinanceProductLevelConfigService $levelService = null,
        private ?FinanceProductLevelTeamService $teamService = null,
    ) {
        $this->settingService = $settingService ?? new SettingService();
        $this->matchService = $matchService ?? new FinanceProductMatchService();
        $this->levelService = $levelService ?? new FinanceProductLevelConfigService();
        $this->teamService = $teamService ?? new FinanceProductLevelTeamService();
    }

    public function index(int $userId, int $levelId = 0): array
    {
        $user = \app\model\member\UserModel::find($userId);

        // level_id=0 是体验区，不可再用 ?: 回落到用户当前等级
        $level = $this->levelService->getLevel($levelId);
        if ($level === null) {
            foreach ($this->levelService->getAllLevels() as $row) {
                if ((int) ($row['level_id'] ?? -1) === $levelId) {
                    $level = $row;
                    break;
                }
            }
        }
        if ($level === null) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '该广告位不存在');
        }
        if (array_key_exists('is_can_buy', $level) && (int) $level['is_can_buy'] === 0) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '该广告位暂未开放购买');
        }

        $teamRatio = $this->levelService->getTeamRatioConfig($userId, $levelId);
        if ($teamRatio !== null) {
            $ratios = array_values(array_filter(
                array_map('floatval', (array) $teamRatio->ratio),
                static fn (float $v): bool => $v > 0
            ));
            $investNum = $ratios !== [] ? count($ratios) : (int) ($level['invest_num'] ?? 0);
        } else {
            [$level] = $this->teamService->applyTeamLevel($userId, $levelId, $level);
            $investNum = (int) ($level['invest_num'] ?? 0);
        }

        [$sessionOrders] = $this->matchService->getLevelSessionOrders($levelId, $userId);
        $orders = collect($sessionOrders);
        $isExperience = (int) ($level['type'] ?? 0) === FinanceProductLevelConfigService::TYPE_EXPERIENCE;

        $pendingOrderCount = 0;
        $matchedOrderCount = 0;
        if ($orders->isNotEmpty()) {
            if ($isExperience) {
                $pendingOrderCount = $orders->filter(
                    static fn ($item) => (int) $item->status === AdProductOrderModel::STATUS_PENDING
                )->count();
                $matchedOrderCount = $orders->filter(
                    static fn ($item) => (int) $item->status !== AdProductOrderModel::STATUS_PENDING
                )->count();
            } else {
                $pendingOrderCount = $orders->filter(
                    static fn ($item) => (int) $item->status === AdProductOrderModel::STATUS_PENDING
                        && (int) $item->level_id === $levelId
                )->count();
                $matchedOrderCount = $orders->filter(
                    static fn ($item) => (int) $item->status !== AdProductOrderModel::STATUS_PENDING
                        && (int) $item->level_id === $levelId
                )->count();
            }
        }

        $orderCount = $pendingOrderCount + $matchedOrderCount;

        $waitBuy = AdProductOrderModel::where('user_id', $userId)
            ->where('level_id', $levelId)
            ->where('status', AdProductOrderModel::STATUS_WAIT_BUY)
            ->orderByDesc('id')->limit(10)->get()
            ->map(fn ($row) => FinanceProductPopupConfig::formatOrderForH5($row->toArray()));

        $ratio = \app\model\member\UserAdRatioConfigModel::where('user_id', $userId)
            ->where('level_id', $levelId)->first();

        return [
            'level_id' => $levelId,
            'balance' => (string) ($user?->balance ?? 0),
            'product_count' => $orderCount > 0 ? $orderCount : $investNum,
            'product_order_count' => $matchedOrderCount,
            'wait_buy_list' => $waitBuy,
            'wait_list' => $waitBuy,
            'today_bought_count' => AdProductOrderModel::where('user_id', $userId)
                ->where('status', AdProductOrderModel::STATUS_BOUGHT)
                ->where('created_at', '>=', strtotime(date('Y-m-d')))->count(),
            'ratio_config' => $ratio?->ratio,
        ];
    }

    /**
     * 广告位库存详情（库存交易列表 + 手续费率）
     *
     * @return array{level_id: int, fee_rate: float|int|string, advertis_arr: list<array{name: string, cpm: mixed}>}
     */
    public function details(int $levelId): array
    {
        $row = \app\model\ad\AdConfigModel::query()->where('level_id', $levelId)->first();
        if ($row === null) {
            // 兼容未落库、仅 settings 配置的场景
            $level = null;
            foreach ($this->levelService->getAllLevels() as $item) {
                if ((int) ($item['level_id'] ?? -1) === $levelId) {
                    $level = $item;
                    break;
                }
            }
            if ($level === null) {
                throw new BusinessException(ErrorCode::NOT_FOUND, '该广告位不存在');
            }
            $levelName = (string) ($level['level_name'] ?? $level['name'] ?? '');
            $feeRate = $level['fee_rate'] ?? 0;
        } else {
            $levelName = (string) $row->level_name;
            $feeRate = $row->fee_rate ?? 0;
        }

        $advertisArr = [[
            'name' => $levelName,
            'cpm' => '数量(B)',
        ]];
        foreach (AdAdvertisNumModel::query()->orderBy('id')->get() as $item) {
            $advertisArr[] = [
                'name' => (string) $item->advertis_name,
                'cpm' => $item->num,
            ];
        }

        return [
            'level_id' => $levelId,
            'fee_rate' => $feeRate,
            'advertis_arr' => $advertisArr,
        ];
    }

    public function orderList(int $userId, int $page, int $limit, ?int $status = null): array
    {
        $query = \app\model\ad\AdProductOrderModel::where('user_id', $userId);
        if ($status !== null) {
            $query->where('status', $status);
        }
        $total = (clone $query)->count();
        $items = $query->orderByDesc('id')->offset(($page - 1) * $limit)->limit($limit)->get()
            ->map(fn ($row) => array_merge(FinanceProductPopupConfig::formatOrderForH5($row->toArray()), [
                'status_text' => \app\model\ad\AdProductOrderModel::$statusMap[$row->status] ?? '',
            ]));
        return ['total' => $total, 'items' => $items];
    }

    public function orderStats(int $userId): array
    {
        // 统计已购买/已结算订单；表字段为 invest_amount/price、profit_amount、ended_at（非参考库 money/profit_money）
        $orders = AdProductOrderModel::where('user_id', $userId)
            ->whereIn('status', [AdProductOrderModel::STATUS_BOUGHT, AdProductOrderModel::STATUS_SETTLED])
            ->get(['invest_amount', 'price', 'profit_amount', 'ended_at']);

        $yesterdayStart = strtotime('yesterday midnight');
        $yesterdayEnd = strtotime('today midnight') - 1;

        $orderMoney = 0.0;
        $totalIncome = 0.0;
        $yesterdayIncome = 0.0;
        foreach ($orders as $order) {
            $amount = (float) ($order->invest_amount ?: $order->price);
            $orderMoney += $amount;
            $profit = (float) $order->profit_amount;
            $totalIncome += $profit;
            $endTime = (int) $order->ended_at;
            if ($endTime >= $yesterdayStart && $endTime <= $yesterdayEnd) {
                $yesterdayIncome += $profit;
            }
        }

        return [
            'order_money' => number_format($orderMoney, 2, '.', ''),
            'order_num' => $orders->count(),
            'total_invest_income' => number_format($totalIncome, 2, '.', ''),
            'yesterday_order_income' => number_format($yesterdayIncome, 2, '.', ''),
        ];
    }

    public function match(int $userId, array $data): array
    {
        return $this->validateAndQueue($userId, $data, FinanceProductMatchService::MATCH_TYPE_NORMAL);
    }

    public function overdueMatch(int $userId, array $data): array
    {
        return $this->validateAndQueue($userId, $data, FinanceProductMatchService::MATCH_TYPE_OVERDUE, true);
    }

    public function validateAndQueue(int $userId, array $data, int $matchType, bool $strictOverdue = false): array
    {
        $levelId = (int) ($data['level_id'] ?? 0);
        $password = (string) ($data['password'] ?? '');
        $type = (int) ($data['type'] ?? 0);
        $sign = (string) ($data['sign'] ?? '');

        try {
            if ($strictOverdue) {
                $this->matchService->validateOverdueRequest($userId, $levelId, $password, $type, [
                    'password' => $password,
                    'level_id' => $levelId,
                    'timestamp' => $data['timestamp'] ?? '',
                    'type' => $type,
                ], $sign);
            } else {
                $this->levelService->checkPassword($password, $levelId, $type, -2);
                ApiSignHelper::verify([
                    'password' => $password,
                    'level_id' => $levelId,
                    'timestamp' => $data['timestamp'] ?? '',
                    'type' => $type,
                ], $sign);
            }
        } catch (FinanceProductMatchException $e) {
            $this->storeMatchPayload($userId, $levelId, FinanceProductMatchResult::error($e->matchCode, $e->getMessage()));
            self::releaseMatchLock($userId, $levelId);
            return [];
        } catch (\app\exception\BusinessException $e) {
            $this->storeMatchPayload($userId, $levelId, FinanceProductMatchResult::error(-1, $e->getMessage()));
            self::releaseMatchLock($userId, $levelId);
            return [];
        }

        if (Redis::get(self::matchLockKey($userId, $levelId))) {
            throw new \app\exception\BusinessException(\app\support\ErrorCode::VALIDATION_FAILED, '匹配进行中，请稍候');
        }

        Client::send(FinanceProductMatchConsumer::QUEUE_NAME, [
            'user_id' => $userId,
            'level_id' => $levelId,
            'password' => $password,
            'type' => $type,
            'match_type' => $matchType,
        ]);
        Redis::setex(self::matchLockKey($userId, $levelId), 120, '1');

        return [];
    }

    public function pullMatchStatus(int $userId, int $levelId): ?array
    {
        $raw = Redis::get(self::matchStatusKey($userId, $levelId));
        if (!$raw) {
            return null;
        }
        Redis::del(self::matchStatusKey($userId, $levelId));
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    public function storeMatchPayload(int $userId, int $levelId, array $payload): void
    {
        Redis::setex(self::matchStatusKey($userId, $levelId), 300, json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    public static function matchStatusKey(int $userId, int $levelId): string
    {
        return 'maestro:' . $userId . '_' . $levelId . '_match_status';
    }

    public static function matchLockKey(int $userId, int $levelId): string
    {
        return self::MATCH_LOCK_PREFIX . $userId . ':' . $levelId;
    }

    public static function releaseMatchLock(int $userId, int $levelId): void
    {
        Redis::del(self::matchLockKey($userId, $levelId));
    }

    public function overdueConfig(int $userId): array
    {
        $global = $this->settingService->getValue('finance_product_overdue', [
            'countdown' => 60,
            'button_countdown' => 30,
            'finance_product_button_count_down' => 30,
            'finance_product_yes_can_click' => 1,
            'finance_product_no_can_click' => 1,
            'finance_product_dialog_count_down' => 10,
            'finance_product_dialog_btn_cancel' => 5,
        ]);

        $dialogCountDown = (int) ($global['finance_product_dialog_count_down'] ?? $global['countdown'] ?? 10);

        $userConfig = (new MemberAdCountdownService())->resolveForUser($userId);
        if ($userConfig) {
            return [
                'finance_product_no_can_click' => (int) $userConfig->can_click_on_no,
                'finance_product_yes_can_click' => (int) $userConfig->can_click_on_yes,
                'finance_product_dialog_count_down' => $dialogCountDown,
                'finance_product_button_count_down' => (int) $userConfig->button_duration,
                'finance_product_dialog_btn_cancel' => (int) ($userConfig->cancel_duration ?: 3),
            ];
        }

        return [
            'finance_product_no_can_click' => (int) ($global['finance_product_no_can_click'] ?? 1),
            'finance_product_yes_can_click' => (int) ($global['finance_product_yes_can_click'] ?? 1),
            'finance_product_dialog_count_down' => $dialogCountDown,
            'finance_product_button_count_down' => (int) ($global['finance_product_button_count_down'] ?? $global['button_countdown'] ?? 30),
            'finance_product_dialog_btn_cancel' => (int) ($global['finance_product_dialog_btn_cancel'] ?? 5),
        ];
    }
}
