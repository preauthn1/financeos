<?php

declare(strict_types=1);

namespace app\service\api;

use app\model\member\UserMessageModel;
use app\service\BannerService;
use app\service\CustomerLinkService;
use app\service\NoticeService;
use app\service\SettingService;

use app\service\api\ApiVideoService;
use app\support\Locale;

class ApiIndexService
{
    public function __construct(
        private ?SettingService $settingService = null,
        private ?NoticeService $noticeService = null,
        private ?BannerService $bannerService = null,
        private ?CustomerLinkService $customerLinkService = null,
        private ?ApiUserService $userService = null,
        private ?MemberAuthService $authService = null,
        private ?ApiVideoService $videoService = null,
    ) {
        $this->settingService = $settingService ?? new SettingService();
        $this->noticeService = $noticeService ?? new NoticeService();
        $this->bannerService = $bannerService ?? new BannerService();
        $this->customerLinkService = $customerLinkService ?? new CustomerLinkService();
        $this->userService = $userService ?? new ApiUserService();
        $this->authService = $authService ?? new MemberAuthService();
        $this->videoService = $videoService ?? new ApiVideoService();
    }

    public function index(int $userId = 0, string $lang = 'zh-cn'): array
    {
        $lang = Locale::normalize($lang);
        $notices = $this->noticeService->publicPreview(5, $lang);
        $banner = $this->bannerService->publicList('index', $lang);
        $indexVideoUrl = (string) $this->settingService->getValue('index_video_url', '');
        $data = [
            'index' => [
                'new_message_count' => 0,
                'new_pop_message_count' => 0,
            ],
            'notice' => $notices,
            'slide' => $banner,
            'system' => [
                'index_video_is_show' => (int) $this->settingService->getValue('index_video_is_show', 0),
                'index_video_url' => $indexVideoUrl,
                'is_show_cate' => (int) $this->settingService->getValue('is_show_cate', 0),
                'is_show_fake_data' => (int) $this->settingService->getValue('is_show_fake_data', 0),
            ],
            'task_reset_time' => (string) $this->settingService->getValue('task_reset_time', '12:00'),
            'video' => $this->videoService->indexVideoList($lang),
        ];

        if ($userId > 0) {
            $user = \app\model\member\UserModel::find($userId);
            if ($user) {
                $data['index'] = [
                    'new_message_count' => $this->userService->unreadMessageCount($userId),
                    'new_pop_message_count' => UserMessageModel::where('user_id', $userId)
                        ->where('is_popup', 1)->where('is_read', 0)->count(),
                    'user' => $this->authService->transformUser($user),
                ];
            }
        }

        return $data;
    }

    public function init(): array
    {
        $customer = $this->customerLinkService->publicList();
        $register = $this->settingService->getValue('register_config', []);
        $site = $this->settingService->getValue('site_config', []);
        $langService = new \app\service\LangService();

        // site_config 为历史聚合包；后台「参数配置」以独立 key 为准，需覆盖合并
        $config = is_array($site) ? $site : [];
        foreach ([
            'sys_currency_type',
            'fiat_currency_unit',
            'fiat_currency_symbol',
            'fiat_currency_rate',
            'name',
            'logo',
            'admin_icon',
            'ios_url',
            'android_url',
            'mt_android_url',
            'mt_ios_url',
            'app_version',
            'official_website_url',
            'customer_logo',
            'customer_logo_status',
            'salesmartly_src',
            'index_force_notice',
            'index_video_is_show',
            'index_video_url',
            'system_default_lang',
            'checkout_language',
        ] as $key) {
            $value = $this->settingScalar($key);
            if ($value !== null) {
                $config[$key] = $value;
            }
        }

        $setting = $config;
        $setting['system_default_lang'] = (string) ($config['system_default_lang'] ?? \app\support\Locale::defaultApiLocale());
        $setting['checkout_language'] = (int) ($config['checkout_language'] ?? 1);

        // 对齐参考项目：虚拟币模式固定 USDT / 汇率 1；货币模式使用后台配置汇率
        $currencyType = (string) ($config['sys_currency_type'] ?? '1');
        if ($currencyType === '1') {
            $setting['sys_currency_type'] = '1';
            $setting['fiat_currency_unit'] = 'USDT';
            $setting['fiat_currency_symbol'] = (string) ($config['fiat_currency_symbol'] ?? '$');
            $setting['fiat_currency_rate'] = 1;
        } else {
            $setting['sys_currency_type'] = '2';
            $setting['fiat_currency_unit'] = (string) ($config['fiat_currency_unit'] ?? 'USD');
            $setting['fiat_currency_symbol'] = (string) ($config['fiat_currency_symbol'] ?? '$');
            $rate = (float) ($config['fiat_currency_rate'] ?? 0);
            $setting['fiat_currency_rate'] = $rate > 0 ? $rate : 1;
        }

        return [
            'customer' => $customer,
            'register_info' => array_merge([
                'invitation_code_require' => 1,
                'is_repeat_password' => 1,
                'register_account_status' => 0,
            ], is_array($register) ? $register : []),
            'setting' => $setting,
            'site_info' => [
                'app_ios_download_url' => (string) ($config['ios_url'] ?? ''),
                'app_android_download_url' => (string) ($config['android_url'] ?? ''),
                'app_mt_android_download_url' => (string) ($config['mt_android_url'] ?? ''),
                'app_mt_ios_download_url' => (string) ($config['mt_ios_url'] ?? ''),
                'is_show_user_protocol' => $config['is_show_user_protocol'] ?? '',
                'official_website_url' => (string) ($config['official_website_url'] ?? ''),
                'site_logo' => (string) ($config['logo'] ?? ''),
                'site_icon' => (string) ($config['admin_icon'] ?? ''),
                'site_name' => (string) ($config['name'] ?? ''),
            ],
            'version' => (string) ($config['app_version'] ?? ''),
            'langs' => $langService->publicList(),
        ];
    }

    /**
     * 读取后台独立配置项的标量值（sy_settings.value 经 array cast 后可能是 string/number）。
     */
    private function settingScalar(string $key): mixed
    {
        $value = $this->settingService->getValue($key, null);
        if ($value === null) {
            return null;
        }
        if (is_array($value)) {
            if ($value === []) {
                return null;
            }
            // 兼容异常包裹
            if (array_key_exists('value', $value)) {
                $value = $value['value'];
            } elseif (array_is_list($value) && count($value) === 1) {
                $value = $value[0];
            } else {
                return null;
            }
        }
        if (is_string($value) && trim($value) === '') {
            return null;
        }

        return $value;
    }

    public function banner(string $region = 'index', string $lang = 'zh-cn'): array
    {
        return $this->bannerService->publicList($region, $lang);
    }

    public function vipList(): array
    {
        return $this->settingService->getValue('vip_leaderboard', []);
    }
}
