<?php

declare(strict_types=1);

namespace app\model\Concerns;

/**
 * 追加型日志表分区配置（按月 RANGE）。
 *
 * @return array<string, mixed>
 */
trait PartitionedLogTable
{
    protected static function monthlyPartition(string $column = 'created_at', string $valueType = 'timestamp'): array
    {
        return [
            'enabled' => true,
            'column' => $column,
            'valueType' => $valueType,
            'granularity' => 'month',
            'primary' => ['id', $column],
        ];
    }
}
