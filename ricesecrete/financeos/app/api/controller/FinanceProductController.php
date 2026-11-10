<?php

declare(strict_types=1);

namespace app\api\controller;

use app\service\api\ApiFinanceProductService;
use support\Request;

class FinanceProductController extends BaseController
{
    public function __construct(private ?ApiFinanceProductService $service = null)
    {
        $this->service = $service ?? new ApiFinanceProductService();
    }

    public function index(Request $request)
    {
        return success($this->service->index($this->userId($request), (int) $request->get('level_id', 0)));
    }

    public function orderList(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        $status = $request->get('status');
        return success($this->service->orderList(
            $this->userId($request), $page, $limit,
            $status !== null && $status !== '' ? (int) $status : null
        ));
    }

    public function orderCount(Request $request)
    {
        return success($this->service->orderStats($this->userId($request)));
    }

    public function details(Request $request)
    {
        return success($this->service->details((int) $request->get('level_id', 0)));
    }

    public function overdueConfig(Request $request)
    {
        return success($this->service->overdueConfig($this->userId($request)));
    }

    public function match(Request $request)
    {
        $this->service->match($this->userId($request), $this->matchParams($request));
        return success(null);
    }

    public function overdueMatch(Request $request)
    {
        $this->service->overdueMatch($this->userId($request), $this->matchParams($request));
        return success(null);
    }

    public function getFinanceProductOrder(Request $request)
    {
        $levelId = (int) $request->get('level_id', 0);
        $result = $this->service->pullMatchStatus($this->userId($request), $levelId);
        if ($result === null) {
            return success(null);
        }
        return json_response($result);
    }

    private function matchParams(Request $request): array
    {
        return array_merge($request->post(), $request->get());
    }
}
