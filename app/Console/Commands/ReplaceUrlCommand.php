<?php

namespace App\Console\Commands;

use App\Services\System\SearchReplaceService;
use Illuminate\Console\Command;
use RuntimeException;

class ReplaceUrlCommand extends Command
{
    protected $signature = 'app:replace-url
                            {from : URL prefix to find, e.g. http://127.0.0.1:8000}
                            {to : Replacement, e.g. https://base.example.com}
                            {--dry-run : Only report matching rows per table}
                            {--table=* : Limit to these tables (default: every table Search & Replace exposes)}';

    protected $description = 'Rewrite a stored URL prefix across all text/JSON columns (plain and JSON-escaped forms)';

    public function handle(SearchReplaceService $searchReplace): int
    {
        $from = rtrim(trim((string) $this->argument('from')), '/');
        $to = rtrim(trim((string) $this->argument('to')), '/');

        if ($from === '' || $to === '' || $from === $to) {
            $this->error('Provide two different, non-empty URLs.');

            return self::FAILURE;
        }

        $tables = array_values(array_filter((array) $this->option('table')))
            ?: array_column($searchReplace->listTables(), 'name');
        $dryRun = (bool) $this->option('dry-run');

        $perTable = [];
        try {
            foreach ($this->variants($from, $to) as $find => $replace) {
                $result = $dryRun
                    ? $searchReplace->preview($tables, $find, caseSensitive: true, ignoreSlugs: false)
                    : $searchReplace->apply($tables, $find, $replace, caseSensitive: true, ignoreSlugs: false);

                foreach ($result['tables'] as $row) {
                    $count = $dryRun ? $row['match_count'] : $row['replaced_count'];
                    if ($count > 0) {
                        $perTable[$row['table']] = ($perTable[$row['table']] ?? 0) + $count;
                    }
                }
            }
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($perTable === []) {
            $this->info("No stored values contain {$from}.");

            return self::SUCCESS;
        }

        ksort($perTable);
        $this->table(['Table', 'Rows'], array_map(null, array_keys($perTable), array_values($perTable)));

        $total = array_sum($perTable);
        $this->info($dryRun
            ? "Dry run: {$total} row match(es). Re-run without --dry-run to apply."
            : "Updated {$total} row(s). Run `php artisan cache:clear` so cached payloads are rebuilt.");

        return self::SUCCESS;
    }

    /**
     * JSON columns store `/` as `\/`, so search both spellings.
     *
     * @return array<string, string>
     */
    private function variants(string $from, string $to): array
    {
        $variants = [$from => $to];
        $escaped = str_replace('/', '\\/', $from);
        if ($escaped !== $from) {
            $variants[$escaped] = str_replace('/', '\\/', $to);
        }

        return $variants;
    }
}
