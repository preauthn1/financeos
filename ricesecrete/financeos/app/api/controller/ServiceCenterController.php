<?php

declare(strict_types=1);

namespace app\api\controller;

use app\service\ServiceCenterService;
use support\Request;

class ServiceCenterController extends BaseController
{
    public function __construct(private ?ServiceCenterService $service = null)
    {
        $this->service = $service ?? new ServiceCenterService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);

        return success($this->service->list($page, $limit));
    }

    public function show(Request $request, int $id)
    {
        return success($this->service->show($id));
    }

    public function apply(Request $request, int $id)
    {
        return success(
            $this->service->apply($this->userId($request), $id, $request->post()),
            '提交成功'
        );
    }
}
