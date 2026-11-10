<?php

declare(strict_types=1);

namespace app\support\Schema;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;

class SchemaBlueprintBuilder
{
    public static function apply(Blueprint $table, array $schema, bool $creating = true): void
    {
        if ($creating && !empty($schema['comment'])) {
            $table->comment($schema['comment']);
        }

        if ($creating) {
            foreach ($schema['columns'] ?? [] as $name => $definition) {
                self::addColumn($table, $name, $definition);
            }

            if (!empty($schema['primary'])) {
                $table->primary($schema['primary']);
            }

            if (!empty($schema['timestamps'])) {
                self::addTimestampColumns($table, $schema);
            }

            if (!empty($schema['softDeletes'])) {
                self::addSoftDeleteColumn($table, $schema);
            }
        } else {
            $builder = DB::schema();
            foreach ($schema['columns'] ?? [] as $name => $definition) {
                if (!$builder->hasColumn($schema['table'], $name)) {
                    self::addColumn($table, $name, $definition);
                }
            }
        }

        self::applyIndexes($table, $schema, $creating);
        self::applyUniques($table, $schema, $creating);

        if ($creating) {
            self::applyForeignKeys($table, $schema);
        }
    }

    public static function addColumn(Blueprint $table, string $name, array $definition): ColumnDefinition
    {
        $type = $definition['type'] ?? 'string';
        $column = match ($type) {
            'increments' => $table->increments($name),
            'bigIncrements' => $table->bigIncrements($name),
            'unsignedInteger' => $table->unsignedInteger($name),
            'unsignedBigInteger' => $table->unsignedBigInteger($name),
            'integer' => $table->integer($name),
            'tinyInteger' => $table->tinyInteger($name),
            'bigInteger' => $table->bigInteger($name),
            'boolean' => $table->boolean($name),
            'decimal' => $table->decimal($name, $definition['precision'] ?? 10, $definition['scale'] ?? 2),
            'string' => $table->string($name, $definition['length'] ?? 255),
            'char' => $table->char($name, $definition['length'] ?? 4),
            'text' => $table->text($name),
            'mediumText' => $table->mediumText($name),
            'longText' => $table->longText($name),
            'json' => $table->json($name),
            'enum' => $table->enum($name, $definition['values'] ?? []),
            'timestamp' => $table->timestamp($name),
            'dateTime' => $table->dateTime($name),
            default => $table->string($name),
        };

        if (!empty($definition['unsigned'])) {
            $column->unsigned();
        }

        if (array_key_exists('nullable', $definition) && $definition['nullable']) {
            $column->nullable();
        }

        if (array_key_exists('default', $definition)) {
            $column->default($definition['default']);
        }

        if (!empty($definition['useCurrent'])) {
            $column->useCurrent();
        }

        if (!empty($definition['unique'])) {
            $column->unique();
        }

        if (!empty($definition['comment'])) {
            $column->comment($definition['comment']);
        }

        return $column;
    }

    private static function addTimestampColumns(Blueprint $table, array $schema): void
    {
        $comments = $schema['columnComments'] ?? [];
        $timestampType = $schema['timestampType'] ?? 'timestamp';

        if ($timestampType === 'unsignedInteger') {
            $created = $table->unsignedInteger('created_at')->default(0);
            $updated = $table->unsignedInteger('updated_at')->default(0);
        } else {
            $created = $table->timestamp('created_at')->nullable();
            $updated = $table->timestamp('updated_at')->nullable();
        }

        if (!empty($comments['created_at'])) {
            $created->comment($comments['created_at']);
        }
        if (!empty($comments['updated_at'])) {
            $updated->comment($comments['updated_at']);
        }
    }

    private static function addSoftDeleteColumn(Blueprint $table, array $schema): void
    {
        $comments = $schema['columnComments'] ?? [];
        $deleted = $table->timestamp('deleted_at')->nullable();
        if (!empty($comments['deleted_at'])) {
            $deleted->comment($comments['deleted_at']);
        }
    }

    public static function applyIndexes(Blueprint $table, array $schema, bool $creating): void
    {
        $builder = DB::schema();
        $tableName = $schema['table'];

        foreach ($schema['indexes'] ?? [] as $index) {
            $columns = $index['columns'] ?? [];
            if ($columns === []) {
                continue;
            }

            $name = $index['name'] ?? self::indexName($tableName, $columns);
            if (!$creating && $builder->hasIndex($tableName, $name)) {
                continue;
            }

            $table->index($columns, $name);
        }
    }

    public static function applyUniques(Blueprint $table, array $schema, bool $creating): void
    {
        $builder = DB::schema();
        $tableName = $schema['table'];

        foreach ($schema['unique'] ?? [] as $unique) {
            $columns = $unique['columns'] ?? [];
            if ($columns === []) {
                continue;
            }

            $name = $unique['name'] ?? self::uniqueName($tableName, $columns);
            if (!$creating && $builder->hasIndex($tableName, $name)) {
                continue;
            }

            $table->unique($columns, $name);
        }
    }

    public static function applyForeignKeys(Blueprint $table, array $schema): void
    {
        foreach ($schema['foreignKeys'] ?? [] as $foreignKey) {
            $column = $foreignKey['column'] ?? '';
            $references = $foreignKey['references'] ?? '';
            $on = $foreignKey['on'] ?? 'id';
            if ($column === '' || $references === '') {
                continue;
            }

            $fk = $table->foreign($column)->references($on)->on($references);

            if (!empty($foreignKey['cascadeOnDelete'])) {
                $fk->cascadeOnDelete();
            } elseif (!empty($foreignKey['nullOnDelete'])) {
                $fk->nullOnDelete();
            } elseif (!empty($foreignKey['onDelete'])) {
                $fk->onDelete($foreignKey['onDelete']);
            }
        }
    }

    public static function indexName(string $table, array $columns): string
    {
        return $table . '_' . implode('_', $columns) . '_index';
    }

    public static function uniqueName(string $table, array $columns): string
    {
        return $table . '_' . implode('_', $columns) . '_unique';
    }
}
