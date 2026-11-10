<?php

declare(strict_types=1);

namespace app\support\Schema;

use Illuminate\Database\Capsule\Manager as DB;
use RuntimeException;

/**
 * 按模型 tableSchema['partition'] 配置应用 MySQL RANGE 分区。
 */
final class TablePartitioner
{
    /**
     * @param array<string, mixed> $schema
     */
    public static function apply(array $schema, int $monthsAhead = 24, ?int $monthsBack = 3): array
    {
        $partition = $schema['partition'] ?? null;
        if (!is_array($partition) || empty($partition['enabled'])) {
            return ['status' => 'skipped', 'reason' => 'partition disabled'];
        }

        $table = (string) ($schema['table'] ?? '');
        $column = (string) ($partition['column'] ?? 'created_at');
        if ($table === '') {
            throw new RuntimeException('partition schema missing table name');
        }

        if (self::isPartitioned($table)) {
            return ['status' => 'skipped', 'reason' => 'already partitioned'];
        }

        $primary = $partition['primary'] ?? ['id', $column];
        if (!in_array($column, $primary, true)) {
            $primary[] = $column;
        }

        self::ensurePrimaryKey($table, $primary);

        $valueType = (string) ($partition['valueType'] ?? 'unix');
        $isTimestamp = $valueType === 'timestamp';

        $sql = self::buildPartitionSql($table, $column, $isTimestamp, $monthsAhead, $monthsBack);
        DB::statement($sql);

        return ['status' => 'applied', 'table' => $table, 'sql' => $sql];
    }

    public static function isPartitioned(string $table): bool
    {
        $row = DB::selectOne(
            'SELECT CREATE_OPTIONS FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        );

        return $row !== null && str_contains((string) ($row->CREATE_OPTIONS ?? ''), 'partitioned');
    }

    /**
     * @param list<string> $primary
     */
    private static function ensurePrimaryKey(string $table, array $primary): void
    {
        $current = self::currentPrimaryKeyColumns($table);
        if ($current === $primary) {
            return;
        }

        $cols = implode('`, `', $primary);
        DB::statement("ALTER TABLE `{$table}` DROP PRIMARY KEY, ADD PRIMARY KEY (`{$cols}`)");
    }

    /**
     * @return list<string>
     */
    private static function currentPrimaryKeyColumns(string $table): array
    {
        $rows = DB::select(
            "SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = 'PRIMARY'
             ORDER BY ORDINAL_POSITION",
            [$table]
        );

        return array_map(static fn ($r) => (string) $r->COLUMN_NAME, $rows);
    }

    private static function columnIsTimestamp(string $table, string $column): bool
    {
        $row = DB::selectOne(
            "SELECT DATA_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
            [$table, $column]
        );

        return in_array((string) ($row->DATA_TYPE ?? ''), ['timestamp', 'datetime'], true);
    }

    private static function buildPartitionSql(
        string $table,
        string $column,
        bool $isTimestamp,
        int $monthsAhead,
        ?int $monthsBack
    ): string {
        $start = new \DateTimeImmutable('first day of this month midnight');
        if ($monthsBack !== null && $monthsBack > 0) {
            $start = $start->modify("-{$monthsBack} months");
        }

        $cursor = $start;
        $total = $monthsBack + $monthsAhead + 1;
        $parts = [];

        for ($i = 0; $i < $total; $i++) {
            $name = 'p' . $cursor->format('Ym');
            $next = $cursor->modify('+1 month');

            if ($isTimestamp) {
                $parts[] = sprintf(
                    'PARTITION %s VALUES LESS THAN (%d)',
                    $name,
                    $next->getTimestamp()
                );
            } else {
                $parts[] = sprintf(
                    'PARTITION %s VALUES LESS THAN (%d)',
                    $name,
                    $next->getTimestamp()
                );
            }

            $cursor = $next;
        }

        $parts[] = 'PARTITION pmax VALUES LESS THAN MAXVALUE';

        if ($isTimestamp) {
            return sprintf(
                'ALTER TABLE `%s` PARTITION BY RANGE (UNIX_TIMESTAMP(`%s`)) (%s)',
                $table,
                $column,
                implode(', ', $parts)
            );
        }

        return sprintf(
            'ALTER TABLE `%s` PARTITION BY RANGE (`%s`) (%s)',
            $table,
            $column,
            implode(', ', $parts)
        );
    }
}
