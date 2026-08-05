<?php

namespace App\Services\System;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Create, list, download, delete, and restore SQL database dumps for MySQL/MariaDB and SQLite.
 */
class DatabaseBackupService
{
    public function directory(): string
    {
        $path = (string) config('database-backup.path');
        if ($path === '') {
            $path = storage_path('app/private/backups');
        }

        if (! File::isDirectory($path)) {
            File::makeDirectory($path, 0750, true);
        }

        return $path;
    }

    /**
     * @return list<array{filename: string, size: int, created_at: string}>
     */
    public function list(): array
    {
        $dir = $this->directory();
        $files = File::files($dir);
        $items = [];

        foreach ($files as $file) {
            $name = $file->getFilename();
            if (! $this->isAllowedFilename($name)) {
                continue;
            }
            $items[] = [
                'filename' => $name,
                'size' => $file->getSize(),
                'created_at' => date('c', $file->getMTime()),
            ];
        }

        usort($items, static fn (array $a, array $b): int => strcmp($b['created_at'], $a['created_at']));

        return $items;
    }

    /**
     * @return array{filename: string, size: int, created_at: string, path: string}
     */
    public function create(): array
    {
        $driver = (string) config('database.default');
        $connection = config("database.connections.{$driver}");
        if (! is_array($connection)) {
            throw new RuntimeException('Database connection is not configured.');
        }

        $engine = (string) ($connection['driver'] ?? '');
        $stamp = now()->format('Y-m-d_His');
        $app = Str::slug((string) config('app.name', 'app')) ?: 'app';
        $filename = "{$app}_{$engine}_{$stamp}.sql";
        $path = $this->directory().DIRECTORY_SEPARATOR.$filename;

        if ($engine === 'mysql' || $engine === 'mariadb') {
            $this->createMysqlDump($connection, $path);
        } elseif ($engine === 'sqlite') {
            $this->createSqliteDump($connection, $path);
        } else {
            throw new RuntimeException("Backup is not supported for database driver [{$engine}].");
        }

        if (! File::isFile($path) || File::size($path) < 1) {
            throw new RuntimeException('Backup file was not created.');
        }

        $this->pruneOldFiles();

        return [
            'filename' => $filename,
            'size' => File::size($path),
            'created_at' => date('c', File::lastModified($path)),
            'path' => $path,
        ];
    }

    public function absolutePath(string $filename): string
    {
        if (! $this->isAllowedFilename($filename)) {
            throw new RuntimeException('Invalid backup filename.');
        }

        $path = $this->directory().DIRECTORY_SEPARATOR.$filename;
        $realBase = realpath($this->directory());
        $realFile = realpath($path);

        if ($realBase === false || $realFile === false || ! str_starts_with($realFile, $realBase)) {
            throw new RuntimeException('Backup file not found.');
        }

        if (! is_file($realFile)) {
            throw new RuntimeException('Backup file not found.');
        }

        return $realFile;
    }

    public function delete(string $filename): void
    {
        File::delete($this->absolutePath($filename));
    }

    public function restoreFromStoredFile(string $filename): void
    {
        $this->restoreFromPath($this->absolutePath($filename));
    }

    public function restoreFromPath(string $path): void
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('Backup file is not readable.');
        }

        $driver = (string) config('database.default');
        $connection = config("database.connections.{$driver}");
        if (! is_array($connection)) {
            throw new RuntimeException('Database connection is not configured.');
        }

        $engine = (string) ($connection['driver'] ?? '');
        $sql = File::get($path);
        if (trim($sql) === '') {
            throw new RuntimeException('Backup file is empty.');
        }

        if ($engine === 'mysql' || $engine === 'mariadb') {
            $this->restoreMysql($connection, $path, $sql);
        } elseif ($engine === 'sqlite') {
            $this->restoreSqlite($connection, $sql);
        } else {
            throw new RuntimeException("Restore is not supported for database driver [{$engine}].");
        }
    }

    public function confirmPhrase(): string
    {
        return (string) config('database-backup.confirm_phrase', 'CONFIRM');
    }

    public function isAllowedFilename(string $filename): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,180}\.sql$/', $filename);
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    private function createMysqlDump(array $connection, string $path): void
    {
        $cli = trim((string) config('database-backup.mysqldump_path', ''));
        if ($cli !== '' && is_executable($cli)) {
            $this->runMysqldumpCli($cli, $connection, $path);

            return;
        }

        // Prefer mysqldump from PATH when available (portable installs).
        $which = $this->resolveBinaryOnPath('mysqldump');
        if ($which !== null) {
            $this->runMysqldumpCli($which, $connection, $path);

            return;
        }

        $this->createMysqlDumpWithPhp($connection, $path);
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    private function runMysqldumpCli(string $binary, array $connection, string $path): void
    {
        $host = (string) ($connection['host'] ?? '127.0.0.1');
        $port = (string) ($connection['port'] ?? 3306);
        $database = (string) ($connection['database'] ?? '');
        $username = (string) ($connection['username'] ?? '');
        $password = (string) ($connection['password'] ?? '');

        $command = [
            $binary,
            '--host='.$host,
            '--port='.$port,
            '--user='.$username,
            '--single-transaction',
            '--routines',
            '--triggers',
            '--hex-blob',
            '--default-character-set=utf8mb4',
            '--result-file='.$path,
            $database,
        ];

        $process = new Process($command);
        $process->setTimeout(600);
        if ($password !== '') {
            $process->setEnv(array_merge($_ENV, $_SERVER, [
                'MYSQL_PWD' => $password,
            ]));
        }
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('mysqldump failed: '.$process->getErrorOutput());
        }
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    private function createMysqlDumpWithPhp(array $connection, string $path): void
    {
        $pdo = DB::connection()->getPdo();
        $database = (string) ($connection['database'] ?? '');
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Unable to open backup file for writing.');
        }

        try {
            fwrite($handle, "-- Credocode MySQL dump\n");
            fwrite($handle, '-- Generated at '.now()->toIso8601String()."\n");
            fwrite($handle, "SET NAMES utf8mb4;\n");
            fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
            fwrite($handle, "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

            $tables = $pdo->query('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'')->fetchAll(\PDO::FETCH_NUM);
            foreach ($tables as $row) {
                $table = (string) $row[0];
                $create = $pdo->query('SHOW CREATE TABLE `'.str_replace('`', '``', $table).'`')->fetch(\PDO::FETCH_ASSOC) ?: [];
                $createSql = '';
                foreach ($create as $key => $value) {
                    if (is_string($key) && str_starts_with(strtolower($key), 'create') && is_string($value) && $value !== '') {
                        $createSql = $value;
                        break;
                    }
                }
                if ($createSql === '') {
                    continue;
                }

                fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");
                fwrite($handle, $createSql.";\n\n");

                $stmt = $pdo->query('SELECT * FROM `'.str_replace('`', '``', $table).'`');
                if ($stmt === false) {
                    continue;
                }

                while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $cols = [];
                    $vals = [];
                    foreach ($data as $col => $value) {
                        $cols[] = '`'.str_replace('`', '``', (string) $col).'`';
                        if ($value === null) {
                            $vals[] = 'NULL';
                        } else {
                            $vals[] = $pdo->quote((string) $value);
                        }
                    }
                    fwrite(
                        $handle,
                        'INSERT INTO `'.$table.'` ('.implode(', ', $cols).') VALUES ('.implode(', ', $vals).");\n"
                    );
                }
                fwrite($handle, "\n");
            }

            fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        } finally {
            fclose($handle);
        }

        unset($database);
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    private function createSqliteDump(array $connection, string $path): void
    {
        $cli = trim((string) config('database-backup.sqlite3_path', ''));
        $dbFile = (string) ($connection['database'] ?? '');
        if ($cli !== '' && is_executable($cli) && $dbFile !== '' && is_file($dbFile)) {
            $process = new Process([$cli, $dbFile, '.dump']);
            $process->setTimeout(600);
            $process->run();
            if ($process->isSuccessful()) {
                File::put($path, $process->getOutput());

                return;
            }
        }

        $pdo = DB::connection()->getPdo();
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Unable to open backup file for writing.');
        }

        try {
            fwrite($handle, "-- Credocode SQLite dump\n");
            fwrite($handle, '-- Generated at '.now()->toIso8601String()."\n");
            fwrite($handle, "PRAGMA foreign_keys=OFF;\n");
            fwrite($handle, "BEGIN TRANSACTION;\n\n");

            $objects = $pdo->query(
                "SELECT type, name, sql FROM sqlite_master WHERE sql IS NOT NULL AND name NOT LIKE 'sqlite_%' ORDER BY CASE type WHEN 'table' THEN 0 WHEN 'index' THEN 1 ELSE 2 END, name"
            )->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($objects as $object) {
                $type = (string) ($object['type'] ?? '');
                $name = (string) ($object['name'] ?? '');
                $sql = trim((string) ($object['sql'] ?? ''));
                if ($sql === '' || $name === '') {
                    continue;
                }

                if ($type === 'table') {
                    fwrite($handle, "DROP TABLE IF EXISTS \"{$name}\";\n");
                }

                fwrite($handle, $sql.";\n\n");

                if ($type !== 'table') {
                    continue;
                }

                $stmt = $pdo->query('SELECT * FROM "'.str_replace('"', '""', $name).'"');
                if ($stmt === false) {
                    continue;
                }

                while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $cols = [];
                    $vals = [];
                    foreach ($data as $col => $value) {
                        $cols[] = '"'.str_replace('"', '""', (string) $col).'"';
                        if ($value === null) {
                            $vals[] = 'NULL';
                        } else {
                            $vals[] = $pdo->quote((string) $value);
                        }
                    }
                    fwrite(
                        $handle,
                        'INSERT INTO "'.$name.'" ('.implode(', ', $cols).') VALUES ('.implode(', ', $vals).");\n"
                    );
                }
                fwrite($handle, "\n");
            }

            fwrite($handle, "COMMIT;\n");
            fwrite($handle, "PRAGMA foreign_keys=ON;\n");
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    private function restoreMysql(array $connection, string $path, string $sql): void
    {
        $cli = trim((string) config('database-backup.mysql_cli_path', ''));
        if ($cli === '' || ! is_executable($cli)) {
            $cli = $this->resolveBinaryOnPath('mysql') ?? '';
        }

        if ($cli !== '') {
            $host = (string) ($connection['host'] ?? '127.0.0.1');
            $port = (string) ($connection['port'] ?? 3306);
            $database = (string) ($connection['database'] ?? '');
            $username = (string) ($connection['username'] ?? '');
            $password = (string) ($connection['password'] ?? '');

            $command = [
                $cli,
                '--host='.$host,
                '--port='.$port,
                '--user='.$username,
                '--default-character-set=utf8mb4',
                $database,
            ];

            $process = new Process($command);
            $process->setTimeout(600);
            $process->setInput(File::get($path));
            if ($password !== '') {
                $process->setEnv(array_merge($_ENV, $_SERVER, [
                    'MYSQL_PWD' => $password,
                ]));
            }
            $process->run();
            if ($process->isSuccessful()) {
                return;
            }
        }

        $this->executeSqlStatements($sql);
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    private function restoreSqlite(array $connection, string $sql): void
    {
        unset($connection);
        // Do not purge(): with SQLite :memory: that would open a brand-new empty database.
        $this->executeSqlStatements($sql);
    }

    private function executeSqlStatements(string $sql): void
    {
        $pdo = DB::connection()->getPdo();
        $statements = $this->splitSqlStatements($sql);

        try {
            // Keep restores statement-by-statement so a dump's BEGIN/COMMIT does not nest oddly.
            foreach ($statements as $statement) {
                $trimmed = trim($statement);
                if ($trimmed === '' || str_starts_with($trimmed, '--')) {
                    continue;
                }
                // VACUUM cannot run inside an open transaction / some managed connections.
                if (preg_match('/^(VACUUM|ANALYZE|BEGIN|COMMIT|END|ROLLBACK)\b/i', $trimmed) === 1) {
                    continue;
                }
                $pdo->exec($trimmed);
            }
        } catch (Throwable $e) {
            throw new RuntimeException('Restore failed: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * @return list<string>
     */
    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $length = strlen($sql);
        $inSingle = false;
        $inDouble = false;
        $inBacktick = false;

        for ($i = 0; $i < $length; $i++) {
            $ch = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            // Line comments
            if (! $inSingle && ! $inDouble && ! $inBacktick && $ch === '-' && $next === '-') {
                while ($i < $length && $sql[$i] !== "\n") {
                    $buffer .= $sql[$i];
                    $i++;
                }
                if ($i < $length) {
                    $buffer .= "\n";
                }
                continue;
            }

            if (! $inDouble && ! $inBacktick && $ch === "'" && ! $inSingle) {
                $inSingle = true;
                $buffer .= $ch;
                continue;
            }
            if ($inSingle) {
                $buffer .= $ch;
                if ($ch === "'" && $next === "'") {
                    $buffer .= $next;
                    $i++;
                } elseif ($ch === "'") {
                    $inSingle = false;
                }
                continue;
            }

            if (! $inSingle && ! $inBacktick && $ch === '"' && ! $inDouble) {
                $inDouble = true;
                $buffer .= $ch;
                continue;
            }
            if ($inDouble) {
                $buffer .= $ch;
                if ($ch === '"' && $next === '"') {
                    $buffer .= $next;
                    $i++;
                } elseif ($ch === '"') {
                    $inDouble = false;
                }
                continue;
            }

            if (! $inSingle && ! $inDouble && $ch === '`' && ! $inBacktick) {
                $inBacktick = true;
                $buffer .= $ch;
                continue;
            }
            if ($inBacktick) {
                $buffer .= $ch;
                if ($ch === '`') {
                    $inBacktick = false;
                }
                continue;
            }

            if ($ch === ';') {
                $statements[] = $buffer;
                $buffer = '';
                continue;
            }

            $buffer .= $ch;
        }

        if (trim($buffer) !== '') {
            $statements[] = $buffer;
        }

        return $statements;
    }

    private function pruneOldFiles(): void
    {
        $max = (int) config('database-backup.max_files', 20);
        if ($max <= 0) {
            return;
        }

        $items = $this->list();
        if (count($items) <= $max) {
            return;
        }

        foreach (array_slice($items, $max) as $item) {
            try {
                $this->delete($item['filename']);
            } catch (Throwable) {
                // Ignore prune failures for individual files.
            }
        }
    }

    private function resolveBinaryOnPath(string $name): ?string
    {
        $isWindows = DIRECTORY_SEPARATOR === '\\';
        $candidates = $isWindows ? ["{$name}.exe", $name] : [$name];

        foreach ($candidates as $candidate) {
            $process = new Process($isWindows ? ['where', $candidate] : ['which', $candidate]);
            $process->setTimeout(5);
            $process->run();
            if (! $process->isSuccessful()) {
                continue;
            }
            $first = trim(explode("\n", str_replace("\r", '', $process->getOutput()))[0] ?? '');
            if ($first !== '' && is_executable($first)) {
                return $first;
            }
        }

        return null;
    }
}
