<?php

declare(strict_types=1);

namespace app\api\controller;

use app\service\api\ApiPddService;
use support\Request;

class PddController extends BaseController
{
    public function __construct(private ?ApiPddService $service = null)
    {
        $this->service = $service ?? new ApiPddService();
    }

    public function getTurntable(Request $request)
    {
        return success($this->service->getTurntable($this->userId($request)));
    }

    public function draw(Request $request)
    {
        return success($this->service->draw($this->userId($request), (int) $request->post('turntable_id', $request->get('id', 0))));
    }

    public function updateBigPrizeProgress(Request $request)
    {
        return success($this->service->updateBigPrizeProgress(
            $this->userId($request),
            (int) $request->post('record_id', $request->get('record_id', 0)),
            (float) $request->post('progress', $request->get('progress', 0))
        ));
    }

    public function receiveBigPrize(Request $request)
    {
        $this->service->receiveBigPrize(
            $this->userId($request),
            (int) $request->post('record_id', $request->get('record_id', 0))
        );
        return success(null, '领取成功');
    }

    public function receiveRecord(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        return success($this->service->records($this->userId($request), $page, $limit, 'receive'));
    }

    public function drawRecord(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        return success($this->service->records($this->userId($request), $page, $limit, 'draw'));
    }

    public function invitedRecord(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        return success($this->service->invitedRecords($this->userId($request), $page, $limit));
    }

    public function financeProductRecord(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        return success($this->service->financeProductRecords($this->userId($request), $page, $limit));
    }
}
