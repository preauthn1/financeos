<?php

declare(strict_types=1);

namespace app\api\controller;

use app\service\api\ApiScaleService;
use support\Request;

class ScaleController extends BaseController
{
    public function __construct(private ?ApiScaleService $service = null)
    {
        $this->service = $service ?? new ApiScaleService();
    }

    public function index(Request $request)
    {
        return success($this->service->index());
    }
}
