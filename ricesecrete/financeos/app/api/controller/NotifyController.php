<?php

declare(strict_types=1);

namespace app\api\controller;

use app\service\PaymentNotifyService;
use support\Request;

/**
 * 支付/代付回调（无需登录）
 */
class NotifyController
{
    public function __construct(private ?PaymentNotifyService $service = null)
    {
        $this->service = $service ?? new PaymentNotifyService();
    }

    public function recharge(Request $request)
    {
        $result = $this->service->handleRecharge($this->parsePayload($request));

        return json_response($result);
    }

    public function withdraw(Request $request)
    {
        $result = $this->service->handleWithdraw($this->parsePayload($request));

        return json_response($result);
    }

    /**
     * 通用回调入口（按 type 分发）
     */
    public function index(Request $request, string $type = 'recharge')
    {
        return match ($type) {
            'withdraw', 'withdrawal' => $this->withdraw($request),
            default => $this->recharge($request),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function parsePayload(Request $request): array
    {
        $payload = array_merge($request->get(), $request->post());
        $raw = trim((string) $request->rawBody());
        if ($raw !== '' && str_starts_with($raw, '{')) {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $payload = array_merge($payload, $json);
            }
        }

        return $payload;
    }
}
