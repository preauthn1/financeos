<?php

declare(strict_types=1);

namespace app\api\controller;

use app\service\api\ApiAdvertisService;
use support\Request;

class AdvertisController extends BaseController
{
    public function __construct(private ?ApiAdvertisService $service = null)
    {
        $this->service = $service ?? new ApiAdvertisService();
    }

    public function index(Request $request)
    {
        return success($this->service->index());
    }

    public function detail(Request $request)
    {
        return success($this->service->detail(
            (string) $request->get('type', ''),
            (string) $request->get('cycle', '1')
        ));
    }
}
