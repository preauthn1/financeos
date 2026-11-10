<?php

declare(strict_types=1);

namespace app\api\controller;

use app\service\api\ApiIndexService;
use app\support\Locale;
use support\Request;

class IndexController
{
    public function __construct(private ?ApiIndexService $service = null)
    {
        $this->service = $service ?? new ApiIndexService();
    }

    public function index(Request $request)
    {
        $lang = (string) ($request->locale ?? $request->get('lang', Locale::current()));
        return success($this->service->index((int) ($request->user_id ?? 0), $lang));
    }

    public function init(Request $request)
    {
        return success($this->service->init());
    }

    public function banner(Request $request, string $region = 'index')
    {
        $region = (string) $request->get('region', $region);
        $lang = (string) $request->get('lang', 'zh-cn');
        return success($this->service->banner($region, $lang));
    }

    public function vipList(Request $request)
    {
        return success($this->service->vipList());
    }
}
