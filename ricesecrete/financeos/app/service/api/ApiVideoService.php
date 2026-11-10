<?php

declare(strict_types=1);

namespace app\service\api;

use app\exception\BusinessException;
use app\model\content\VideoModel;
use app\model\member\UserModel;
use app\service\MemberWalletService;
use app\service\SettingService;
use app\support\ContentCache;
use app\support\ErrorCode;
use support\Redis;

class ApiVideoService
{
    /** @var list<int> */
    private const INDEX_VIDEO_CATES = [
        VideoModel::CATE_AD,
        VideoModel::CATE_INTRODUCTION,
    ];

    public function __construct(
        private ?SettingService $settingService = null,
        private ?MemberWalletService $walletService = null,
    ) {
        $this->settingService = $settingService ?? new SettingService();
        $this->walletService = $walletService ?? new MemberWalletService();
    }

    /** @return list<array<string,mixed>> */
    public function indexVideoList(string $lang = 'zh-cn'): array
    {
        $cacheKey = ContentCache::indexVideoKey($lang);
        $cached = Redis::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            $decoded = json_decode($cached, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $items = $this->queryByLang($lang)
            ->whereIn('cate', self::INDEX_VIDEO_CATES)
            ->orderBy('sort')
            ->orderByDesc('id')
            ->get()
            ->map(fn (VideoModel $row) => $this->transformPublic($row))
            ->values()
            ->all();

        Redis::setex($cacheKey, 3600, json_encode($items, JSON_UNESCAPED_UNICODE));
        return $items;
    }

    public function videoList(int $page, int $limit, ?int $cate = null, bool $random = false): array
    {
        $query = VideoModel::where('status', 1);
        if ($cate) {
            $query->where('cate', $cate);
        }
        if ($random) {
            $query->orderByRaw('RAND()');
        } else {
            $query->orderByDesc('sort')->orderByDesc('id');
        }
        $total = (clone $query)->count();
        $items = $query->offset(($page - 1) * $limit)->limit($limit)->get();
        return ['total' => $total, 'items' => $items];
    }

    public function videoDetail(int $id): array
    {
        $video = VideoModel::where('id', $id)->where('status', 1)->first();
        if (!$video) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '视频不存在');
        }
        VideoModel::where('id', $id)->increment('watch_times');
        return $video->toArray();
    }

    public function recommendList(int $excludeId, int $limit = 6): array
    {
        return VideoModel::where('status', 1)->where('id', '<>', $excludeId)
            ->orderByRaw('RAND()')->limit($limit)->get()->toArray();
    }

    public function addVideoTask(int $userId, int $levelId, int $videoId): void
    {
        $video = VideoModel::where('id', $videoId)->where('status', 1)->first();
        if (!$video) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '视频不存在');
        }

        $key = 'video_task:' . $userId . ':' . date('Ymd') . ':' . $videoId;
        if (Redis::get($key)) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '今日已完成该视频任务');
        }

        $reward = (string) $this->settingService->getValue('video_task_reward', '0.5');
        if (bccomp($reward, '0', 4) > 0) {
            $this->walletService->changeBalance(
                $userId, $reward, MemberWalletService::WALLET_BASE, MemberWalletService::DIRECTION_IN, '视频任务奖励', 3
            );
        }

        Redis::setex($key, 86400, '1');
        UserModel::where('id', $userId)->increment('points', 1);
    }

    public function taskList(int $userId): array
    {
        $todayCount = 0;
        $keys = Redis::keys('video_task:' . $userId . ':' . date('Ymd') . ':*') ?: [];
        $todayCount = count($keys);
        $user = UserModel::find($userId);
        return [
            'task_count' => ['day_task_all' => 10, 'day_task_ing' => max(0, 10 - $todayCount), 'reset_time' => strtotime('tomorrow')],
            'all_user_money' => bcadd((string) ($user?->balance ?? 0), (string) ($user?->brokerage_balance ?? 0), 2),
        ];
    }

    public function recReward(int $userId): array
    {
        $activity = \app\model\activity\ActivityModel::where('type', \app\model\activity\ActivityModel::TYPE_INVITE_VALID)
            ->where('status', 1)->orderByDesc('id')->first();
        if (!$activity) {
            return ['status' => -1, 'reward' => '0', 'invite_count' => 0];
        }

        $pending = \app\model\activity\ActivityRecordModel::where('user_id', $userId)
            ->where('activity_id', $activity->id)
            ->where('status', 0)->where('reward', '>', 0)->get();

        $total = '0';
        $multiplier = 1.0;
        foreach ($pending as $r) {
            $mult = (float) ($r->reward_multiplier ?: 1);
            $multiplier = max($multiplier, $mult);
            $total = bcadd($total, bcmul((string) $r->reward, (string) $mult, 4), 4);
        }

        return [
            'status' => $pending->isEmpty() ? -1 : 0,
            'reward' => $total,
            'multiplier' => $multiplier,
            'invite_count' => $pending->count(),
        ];
    }

    public function getRecReward(int $userId): array
    {
        $activity = \app\model\activity\ActivityModel::where('type', \app\model\activity\ActivityModel::TYPE_INVITE_VALID)
            ->where('status', 1)->orderByDesc('id')->first();
        if (!$activity) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '活动不存在');
        }

        $result = (new ApiActivityService())->getReward($userId, (int) $activity->id);

        return array_merge($result, [
            'multiplier' => $result['multiplier'] ?? 1,
            'display_reward' => isset($result['multiplier'])
                ? bcmul((string) ($activity->reward ?? 0), (string) $result['multiplier'], 2)
                : ($result['reward'] ?? '0'),
        ]);
    }

    private function queryByLang(string $lang)
    {
        $query = VideoModel::where('status', 1)->where('lang', $lang);
        if ((clone $query)->exists()) {
            return $query;
        }

        return VideoModel::where('status', 1)->where('lang', 'zh-cn');
    }

    /** @return array<string,mixed> */
    private function transformPublic(VideoModel $row): array
    {
        $duration = (int) $row->duration;
        return [
            'id' => (int) $row->id,
            'lang' => (string) $row->lang,
            'cate' => (int) $row->cate,
            'cate_str' => VideoModel::cateLabel((int) $row->cate),
            'level_id' => (int) $row->level_id,
            'sub_name' => (string) $row->sub_name,
            'name' => (string) $row->name,
            'image_url' => (string) $row->image_url,
            'video_url' => (string) $row->video_url,
            'duration' => $duration,
            'duration_time' => $this->formatDuration($duration),
            'director' => (string) $row->director,
            'description' => (string) $row->description,
            'watch_times' => (int) $row->watch_times,
            'sort' => (int) $row->sort,
            'status' => (int) $row->status,
        ];
    }

    private function formatDuration(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;
        if ($hours === 0) {
            return sprintf('%02d:%02d', $minutes, $secs);
        }

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
    }
}
