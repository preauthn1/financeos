<?php

declare(strict_types=1);

namespace app\service\api;

use app\service\SettingService;

class ApiScaleService
{
    public function __construct(private ?SettingService $settingService = null)
    {
        $this->settingService = $settingService ?? new SettingService();
    }

    public function index(): array
    {
        return $this->settingService->getValue('platform_scale_data', [
            'daily_impressions' => 0,
            'daily_data_volume' => 0,
            'office_locations' => 0,
            'advertiser_bid_amount' => 0,
            'direct_publishers' => 0,
            'data_centers' => 0,
        ]);
    }
}
