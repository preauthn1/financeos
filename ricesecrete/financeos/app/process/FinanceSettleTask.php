<?php

declare(strict_types=1);

namespace app\process;

use app\service\FinanceSettleJobService;
use Workerman\Crontab\Crontab;

class FinanceSettleTask
{
    public function onWorkerStart(): void
    {
        // 每 5 分钟执行一次广告位/AI托管到期结算
        new Crontab('0 */5 * * * *', function (): void {
            (new FinanceSettleJobService())->runWithLock();
        });
    }
}
