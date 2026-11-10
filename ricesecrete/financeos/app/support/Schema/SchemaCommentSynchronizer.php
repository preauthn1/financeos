<?php

declare(strict_types=1);

namespace app\support\Schema;

use Illuminate\Database\Capsule\Manager as DB;

class SchemaCommentSynchronizer
{
    public static function sync(array $schema): bool
    {
        $table = $schema['table'];
        if (!DB::schema()->hasTable($table)) {
            return false;
        }

        $changed = false;

        if (!empty($schema['comment'])) {
            $current = self::getTableComment($table);
            if ($current !== $schema['comment']) {
                DB::statement(
                    'ALTER TABLE `' . self::escapeIdentifier($table) . '` COMMENT = '
                    . DB::connection()->getPdo()->quote($schema['comment'])
                );
                $changed = true;
            }
        }

        foreach (self::collectColumnComments($schema) as $column => $comment) {
            if (!DB::schema()->hasColumn($table, $column)) {
                continue;
            }
            if (self::getColumnComment($table, $column) === $comment) {
                continue;
            }
            self::modifyColumnComment($table, $column, $comment);
            $changed = true;
        }

        return $changed;
    }

    public static function commentsNeedUpdate(array $schema): bool
    {
        $table = $schema['table'];
        if (!DB::schema()->hasTable($table)) {
            return false;
        }

        if (!empty($schema['comment']) && self::getTableComment($table) !== $schema['comment']) {
            return true;
        }

        foreach (self::collectColumnComments($schema) as $column => $comment) {
            if (!DB::schema()->hasColumn($table, $column)) {
                continue;
            }
            if (self::getColumnComment($table, $column) !== $comment) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, string> */
    public static function collectColumnComments(array $schema): array
    {
        $comments = [];

        foreach ($schema['columns'] ?? [] as $name => $definition) {
            if (!empty($definition['comment'])) {
                $comments[$name] = (string) $definition['comment'];
            }
        }

        foreach ($schema['columnComments'] ?? [] as $name => $comment) {
            $comments[$name] = (string) $comment;
        }

        return $comments;
    }

    private static function getTableComment(string $table): string
    {
        $row = DB::selectOne(
            'SELECT TABLE_COMMENT FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        );

        return (string) ($row->TABLE_COMMENT ?? '');
    }

    private static function getColumnComment(string $table, string $column): string
    {
        $row = DB::selectOne(
            'SELECT COLUMN_COMMENT FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );

        return (string) ($row->COLUMN_COMMENT ?? '');
    }

    private static function modifyColumnComment(string $table, string $column, string $comment): void
    {
        $row = DB::selectOne(
            'SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );
        if ($row === null) {
            return;
        }

        $nullable = $row->IS_NULLABLE === 'YES' ? 'NULL' : 'NOT NULL';
        $default = self::buildDefaultClause($row->COLUMN_DEFAULT, (string) $row->COLUMN_TYPE);
        $extra = self::normalizeExtra((string) $row->EXTRA, $default !== '');

        $sql = sprintf(
            'ALTER TABLE `%s` MODIFY COLUMN `%s` %s %s%s%s COMMENT %s',
            self::escapeIdentifier($table),
            self::escapeIdentifier($column),
            $row->COLUMN_TYPE,
            $nullable,
            $default,
            $extra !== '' ? ' ' . $extra : '',
            DB::connection()->getPdo()->quote($comment)
        );

        DB::statement($sql);
    }

    private static function normalizeExtra(string $extra, bool $hasDefaultClause): string
    {
        if ($extra === '') {
            return '';
        }

        $parts = preg_split('/\s+/', trim($extra)) ?: [];
        $normalized = [];

        foreach ($parts as $part) {
            if ($part === 'DEFAULT_GENERATED' && $hasDefaultClause) {
                continue;
            }
            $normalized[] = $part;
        }

        return implode(' ', $normalized);
    }

    private static function buildDefaultClause(mixed $default, string $columnType): string
    {
        if ($default === null) {
            return '';
        }

        if (strtoupper((string) $default) === 'NULL') {
            return ' DEFAULT NULL';
        }

        if (strtoupper((string) $default) === 'CURRENT_TIMESTAMP') {
            return ' DEFAULT CURRENT_TIMESTAMP';
        }

        if (is_numeric($default) && !str_contains(strtolower($columnType), 'char') && !str_contains(strtolower($columnType), 'text')) {
            return ' DEFAULT ' . $default;
        }

        return ' DEFAULT ' . DB::connection()->getPdo()->quote((string) $default);
    }

    private static function escapeIdentifier(string $identifier): string
    {
        return str_replace('`', '``', $identifier);
    }
}
