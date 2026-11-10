<?php

declare(strict_types=1);

namespace app\queue\redis;

use app\exception\FinanceProductMatchException;
use app\service\api\ApiFinanceProductService;
use app\service\api\FinanceProductMatchService;
use app\support\FinanceProductMatchResult;
use Webman\RedisQueue\Consumer;

class FinanceProductMatchConsumer implements Consumer
{
    public const QUEUE_NAME = 'finance_product_match';

    public $queue = self::QUEUE_NAME;

    public $connection = 'default';

    public function consume($data): void
    {
        $userId = (int) ($data['user_id'] ?? 0);
        $levelId = (int) ($data['level_id'] ?? 0);
        $password = (string) ($data['password'] ?? '');
        $type = (int) ($data['type'] ?? 0);
        $matchType = (int) ($data['match_type'] ?? FinanceProductMatchService::MATCH_TYPE_NORMAL);

        $apiService = new ApiFinanceProductService();

        try {
            $result = (new FinanceProductMatchService())->execute($userId, $levelId, $password, $matchType, $type);
            $apiService->storeMatchPayload($userId, $levelId, $result);
        } catch (FinanceProductMatchException $e) {
            $apiService->storeMatchPayload($userId, $levelId, FinanceProductMatchResult::error($e->matchCode, $e->getMessage()));
        } catch (\Throwable $e) {
            $apiService->storeMatchPayload($userId, $levelId, FinanceProductMatchResult::error(-1, '匹配异常，请稍后重试'));
        } finally {
            ApiFinanceProductService::releaseMatchLock($userId, $levelId);
        }
    }

    public function onConsumeFailure(\Throwable $e, $package): void
    {
        $data = is_array($package['data'] ?? null) ? $package['data'] : [];
        $userId = (int) ($data['user_id'] ?? 0);
        $levelId = (int) ($data['level_id'] ?? 0);
        // level_id=0 为体验区，不能用 >0 判断
        if ($userId > 0 && $levelId >= 0) {
            (new ApiFinanceProductService())->storeMatchPayload(
                $userId,
                $levelId,
                FinanceProductMatchResult::error(-1, $e->getMessage())
            );
            ApiFinanceProductService::releaseMatchLock($userId, $levelId);
        }
    }
}
