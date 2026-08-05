<?php

namespace App\Services\System;

use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Search and replace across string columns of selected database tables.
 */
class SearchReplaceService
{
    public function confirmPhrase(): string
    {
        return (string) config('search-replace.confirm_phrase', 'CONFIRM');
    }

    /**
     * @return list<array{name: string, string_column_count: int}>
     */
    public function listTables(): array
    {
        $connection = DB::connection();
        $names = $this->tableNames($connection);
        $items = [];

        foreach ($names as $table) {
            if ($this->isExcludedTable($table)) {
                continue;
            }
            $columns = $this->stringColumns($connection, $table, ignoreSlugs: false);
            $items[] = [
                'name' => $table,
                'string_column_count' => count($columns),
            ];
        }

        usort($items, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $items;
    }

    /**
     * @param  list<string>  $tables
     * @return array{
     *   match_count: int,
     *   replaced_count: int,
     *   tables: list<array{table: string, match_count: int, replaced_count: int, columns_scanned: int}>,
     *   samples: list<array{table: string, column: string, pk: string|null, before: string, after: string|null}>
     * }
     */
    public function preview(
        array $tables,
        string $find,
        bool $caseSensitive,
        bool $ignoreSlugs,
    ): array {
        return $this->run($tables, $find, null, $caseSensitive, $ignoreSlugs, apply: false);
    }

    /**
     * @param  list<string>  $tables
     * @return array{
     *   match_count: int,
     *   replaced_count: int,
     *   tables: list<array{table: string, match_count: int, replaced_count: int, columns_scanned: int}>,
     *   samples: list<array{table: string, column: string, pk: string|null, before: string, after: string|null}>
     * }
     */
    public function apply(
        array $tables,
        string $find,
        string $replace,
        bool $caseSensitive,
        bool $ignoreSlugs,
    ): array {
        if ($find === '') {
            throw new RuntimeException('Find text must not be empty.');
        }

        return $this->run($tables, $find, $replace, $caseSensitive, $ignoreSlugs, apply: true);
    }

    /**
     * @param  list<string>  $tables
     * @return array{
     *   match_count: int,
     *   replaced_count: int,
     *   tables: list<array{table: string, match_count: int, replaced_count: int, columns_scanned: int}>,
     *   samples: list<array{table: string, column: string, pk: string|null, before: string, after: string|null}>
     * }
     */
    private function run(
        array $tables,
        string $find,
        ?string $replace,
        bool $caseSensitive,
        bool $ignoreSlugs,
        bool $apply,
    ): array {
        if ($find === '') {
            throw new RuntimeException('Find text must not be empty.');
        }

        $tables = array_values(array_unique(array_filter(array_map(
            static fn ($t) => is_string($t) ? trim($t) : '',
            $tables
        ))));

        if ($tables === []) {
            throw new RuntimeException('Select at least one table.');
        }

        $connection = DB::connection();
        $allowed = array_fill_keys(
            array_map(
                static fn (array $row): string => $row['name'],
                $this->listTables()
            ),
            true
        );

        foreach ($tables as $table) {
            if (! isset($allowed[$table])) {
                throw new RuntimeException("Table [{$table}] is not available for search/replace.");
            }
        }

        $sampleLimit = (int) config('search-replace.sample_limit', 50);
        $snippetMax = (int) config('search-replace.snippet_max_chars', 160);
        $driver = $connection->getDriverName();

        $matchCount = 0;
        $replacedCount = 0;
        $tableStats = [];
        $samples = [];

        $callback = function () use (
            $tables,
            $find,
            $replace,
            $caseSensitive,
            $ignoreSlugs,
            $apply,
            $connection,
            $driver,
            $sampleLimit,
            $snippetMax,
            &$matchCount,
            &$replacedCount,
            &$tableStats,
            &$samples
        ): void {
            foreach ($tables as $table) {
                $columns = $this->stringColumns($connection, $table, $ignoreSlugs);
                $pk = $this->primaryKeyColumn($connection, $table);
                $tableMatches = 0;
                $tableReplaced = 0;

                foreach ($columns as $column) {
                    $whereSql = $this->matchWhereSql($driver, $column, $caseSensitive);
                    $bindings = $this->matchBindings($find, $caseSensitive, $driver);

                    try {
                        $count = (int) $connection->table($table)
                            ->whereRaw($whereSql, $bindings)
                            ->count();
                    } catch (QueryException) {
                        // Skip columns that cannot be queried as text on this driver.
                        continue;
                    }

                    if ($count === 0) {
                        continue;
                    }

                    $tableMatches += $count;
                    $matchCount += $count;

                    if (count($samples) < $sampleLimit) {
                        $select = [$column];
                        if ($pk !== null) {
                            $select[] = $pk;
                        }
                        $rows = $connection->table($table)
                            ->select($select)
                            ->whereRaw($whereSql, $bindings)
                            ->limit(max(1, $sampleLimit - count($samples)))
                            ->get();

                        foreach ($rows as $row) {
                            if (count($samples) >= $sampleLimit) {
                                break;
                            }
                            $raw = (string) ($row->{$column} ?? '');
                            $after = $apply
                                ? $this->replaceInString($raw, $find, (string) $replace, $caseSensitive)
                                : null;
                            $samples[] = [
                                'table' => $table,
                                'column' => $column,
                                'pk' => $pk !== null ? (string) ($row->{$pk} ?? '') : null,
                                'before' => $this->snippet($raw, $find, $snippetMax, $caseSensitive),
                                'after' => $after !== null
                                    ? $this->snippet($after, (string) $replace !== '' ? (string) $replace : $find, $snippetMax, $caseSensitive)
                                    : null,
                            ];
                        }
                    }

                    if ($apply) {
                        $affected = $this->updateColumn(
                            $connection,
                            $driver,
                            $table,
                            $column,
                            $find,
                            (string) $replace,
                            $caseSensitive,
                        );
                        $tableReplaced += $affected;
                        $replacedCount += $affected;
                    }
                }

                $tableStats[] = [
                    'table' => $table,
                    'match_count' => $tableMatches,
                    'replaced_count' => $tableReplaced,
                    'columns_scanned' => count($columns),
                ];
            }
        };

        if ($apply) {
            $connection->transaction($callback);
        } else {
            $callback();
        }

        return [
            'match_count' => $matchCount,
            'replaced_count' => $replacedCount,
            'tables' => $tableStats,
            'samples' => $samples,
        ];
    }

    /**
     * @return list<string>
     */
    private function tableNames(Connection $connection): array
    {
        $schema = Schema::connection($connection->getName());
        $raw = [];
        if (method_exists($schema, 'getTableListing')) {
            // Without an explicit schema, MySQL lists tables from *every* database on the server.
            // Scope to this connection's database so Search & Replace only sees project tables.
            $schemaNames = $this->connectionSchemaNames($connection);
            /** @var list<string> $listing */
            $listing = $schemaNames === null
                ? $schema->getTableListing(null, false)
                : $schema->getTableListing($schemaNames, false);
            $raw = array_values($listing);
        } else {
            $driver = $connection->getDriverName();
            if ($driver === 'sqlite') {
                $rows = $connection->select(
                    "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
                );
                $raw = array_map(static fn ($r) => (string) $r->name, $rows);
            } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
                $rows = $connection->select('SHOW TABLES');
                foreach ($rows as $row) {
                    $vals = array_values((array) $row);
                    $raw[] = (string) ($vals[0] ?? '');
                }
            } else {
                throw new RuntimeException("Unsupported database driver [{$connection->getDriverName()}] for search/replace.");
            }
        }

        $out = [];
        foreach ($raw as $name) {
            $name = $this->normalizeTableName((string) $name);
            if ($name !== '') {
                $out[] = $name;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Schema/database names to pass to Schema::getTableListing(), or null when the driver
     * has a single implicit schema (e.g. SQLite file).
     *
     * @return list<string>|null
     */
    private function connectionSchemaNames(Connection $connection): ?array
    {
        $driver = $connection->getDriverName();
        if ($driver === 'sqlite') {
            return null;
        }

        $database = trim((string) $connection->getDatabaseName());
        if ($database === '') {
            throw new RuntimeException('Database name is not configured for search/replace.');
        }

        return [$database];
    }

    private function normalizeTableName(string $table): string
    {
        $table = trim($table);
        // SQLite Schema::getTableListing() may return "main.users".
        if (str_contains($table, '.')) {
            $table = (string) substr($table, strrpos($table, '.') + 1);
        }

        return $table;
    }

    private function isExcludedTable(string $table): bool
    {
        $exact = array_map('strtolower', (array) config('search-replace.excluded_tables', []));
        if (in_array(strtolower($table), $exact, true)) {
            return true;
        }

        foreach ((array) config('search-replace.excluded_table_prefixes', []) as $prefix) {
            $prefix = (string) $prefix;
            if ($prefix !== '' && str_starts_with(strtolower($table), strtolower($prefix))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function stringColumns(Connection $connection, string $table, bool $ignoreSlugs): array
    {
        $schema = Schema::connection($connection->getName());
        $columns = $schema->getColumns($table);
        $pk = $this->primaryKeyColumn($connection, $table);
        $driver = $connection->getDriverName();
        $out = [];

        foreach ($columns as $column) {
            $name = (string) ($column['name'] ?? '');
            if ($name === '' || ($pk !== null && $name === $pk)) {
                continue;
            }

            $type = strtolower((string) ($column['type_name'] ?? $column['type'] ?? ''));
            if (! $this->isStringType($driver, $type, (string) ($column['type'] ?? ''))) {
                continue;
            }

            if ($ignoreSlugs && $this->isSlugOrUrlColumn($name)) {
                continue;
            }

            $out[] = $name;
        }

        return $out;
    }

    private function isStringType(string $driver, string $typeName, string $fullType): bool
    {
        $t = strtolower($typeName.' '.$fullType);

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            foreach (['char', 'varchar', 'text', 'mediumtext', 'longtext', 'tinytext', 'json'] as $needle) {
                if (str_contains($t, $needle)) {
                    return true;
                }
            }

            return false;
        }

        if ($driver === 'sqlite') {
            // SQLite affinity: TEXT types; avoid integer/real/blob-only.
            if (str_contains($t, 'int') || str_contains($t, 'real') || str_contains($t, 'floa') || str_contains($t, 'doub')) {
                return false;
            }
            if (str_contains($t, 'blob')) {
                return false;
            }

            return true;
        }

        return str_contains($t, 'char') || str_contains($t, 'text') || str_contains($t, 'json');
    }

    private function isSlugOrUrlColumn(string $name): bool
    {
        $lower = strtolower($name);
        foreach ((array) config('search-replace.slug_url_exact', []) as $exact) {
            if ($lower === strtolower((string) $exact)) {
                return true;
            }
        }
        foreach ((array) config('search-replace.slug_url_suffixes', []) as $suffix) {
            $suffix = strtolower((string) $suffix);
            if ($suffix !== '' && str_ends_with($lower, $suffix)) {
                return true;
            }
        }

        return false;
    }

    private function primaryKeyColumn(Connection $connection, string $table): ?string
    {
        $schema = Schema::connection($connection->getName());
        try {
            $indexes = $schema->getIndexes($table);
        } catch (\Throwable) {
            return null;
        }

        foreach ($indexes as $index) {
            if (! empty($index['primary']) && ! empty($index['columns'][0])) {
                return (string) $index['columns'][0];
            }
        }

        return null;
    }

    private function matchWhereSql(string $driver, string $column, bool $caseSensitive): string
    {
        $wrapped = $this->wrap($driver, $column);

        if ($caseSensitive) {
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                return "{$wrapped} LIKE ? COLLATE utf8mb4_bin";
            }

            // SQLite LIKE is case-insensitive for ASCII; use instr for sensitive match.
            return "instr({$wrapped}, ?) > 0";
        }

        if ($driver === 'sqlite') {
            return "{$wrapped} LIKE ? ESCAPE '\\'";
        }

        return "{$wrapped} LIKE ?";
    }

    /**
     * @return list<string>
     */
    private function matchBindings(string $find, bool $caseSensitive, string $driver): array
    {
        if ($caseSensitive && $driver === 'sqlite') {
            return [$find];
        }

        return ['%'.$this->escapeLike($find).'%'];
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private function wrap(string $driver, string $identifier): string
    {
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return '`'.str_replace('`', '``', $identifier).'`';
        }

        return '"'.str_replace('"', '""', $identifier).'"';
    }

    private function updateColumn(
        Connection $connection,
        string $driver,
        string $table,
        string $column,
        string $find,
        string $replace,
        bool $caseSensitive,
    ): int {
        $wrappedCol = $this->wrap($driver, $column);
        $wrappedTable = $this->wrap($driver, $table);
        $whereSql = $this->matchWhereSql($driver, $column, $caseSensitive);
        $whereBindings = $this->matchBindings($find, $caseSensitive, $driver);

        // SQL REPLACE() is case-sensitive on MySQL and SQLite.
        if ($caseSensitive) {
            $sql = "UPDATE {$wrappedTable} SET {$wrappedCol} = REPLACE({$wrappedCol}, ?, ?) WHERE {$whereSql}";

            return $connection->affectingStatement($sql, array_merge([$find, $replace], $whereBindings));
        }

        // Case-insensitive replace: update matched rows in PHP.
        $pk = $this->primaryKeyColumn($connection, $table);
        $affected = 0;
        $orderColumn = $pk ?? $column;

        $connection->table($table)
            ->whereRaw($whereSql, $whereBindings)
            ->orderBy($orderColumn)
            ->chunk(100, function ($rows) use (
                $connection,
                $table,
                $column,
                $find,
                $replace,
                $pk,
                &$affected
            ): void {
                foreach ($rows as $row) {
                    $before = (string) ($row->{$column} ?? '');
                    $after = $this->replaceInString($before, $find, $replace, caseSensitive: false);
                    if ($after === $before) {
                        continue;
                    }
                    if ($pk !== null) {
                        $connection->table($table)
                            ->where($pk, $row->{$pk})
                            ->update([$column => $after]);
                    } else {
                        $connection->table($table)
                            ->where($column, $before)
                            ->limit(1)
                            ->update([$column => $after]);
                    }
                    $affected++;
                }
            });

        return $affected;
    }

    private function replaceInString(string $haystack, string $find, string $replace, bool $caseSensitive): string
    {
        if ($find === '') {
            return $haystack;
        }

        if ($caseSensitive) {
            return str_replace($find, $replace, $haystack);
        }

        return (string) preg_replace(
            '/'.preg_quote($find, '/').'/iu',
            str_replace(['\\', '$'], ['\\\\', '\\$'], $replace),
            $haystack
        );
    }

    private function snippet(string $value, string $needle, int $max, bool $caseSensitive): string
    {
        $flat = preg_replace('/\s+/u', ' ', $value) ?? $value;
        if (mb_strlen($flat) <= $max) {
            return $flat;
        }

        $pos = $caseSensitive
            ? mb_strpos($flat, $needle)
            : mb_stripos($flat, $needle);

        if ($pos === false) {
            return mb_substr($flat, 0, $max - 1).'…';
        }

        $half = (int) floor(($max - mb_strlen($needle)) / 2);
        $start = max(0, $pos - $half);
        $chunk = mb_substr($flat, $start, $max);
        $prefix = $start > 0 ? '…' : '';
        $suffix = ($start + mb_strlen($chunk)) < mb_strlen($flat) ? '…' : '';

        return $prefix.$chunk.$suffix;
    }
}
