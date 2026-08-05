<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Confirm phrase for apply
    |--------------------------------------------------------------------------
    */
    'confirm_phrase' => env(
        'SEARCH_REPLACE_CONFIRM_PHRASE',
        env('DB_BACKUP_CONFIRM_PHRASE', 'CONFIRM')
    ),

    /*
    |--------------------------------------------------------------------------
    | Tables never exposed / searched
    |--------------------------------------------------------------------------
    */
    'excluded_tables' => [
        'migrations',
        'password_reset_tokens',
        'password_resets',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'personal_access_tokens',
        'pulse_aggregates',
        'pulse_entries',
        'pulse_values',
    ],

    /*
    |--------------------------------------------------------------------------
    | Excluded table name prefixes (e.g. telescope_*)
    |--------------------------------------------------------------------------
    */
    'excluded_table_prefixes' => [
        'telescope_',
    ],

    /*
    |--------------------------------------------------------------------------
    | Column names / patterns skipped when ignore_slugs is true
    | Exact names (case-insensitive) and suffix patterns (*_slug, *_url).
    |--------------------------------------------------------------------------
    */
    'slug_url_exact' => [
        'slug',
        'url',
        'path',
        'permalink',
        'canonical',
        'href',
        'link',
    ],

    'slug_url_suffixes' => [
        '_slug',
        '_url',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sample rows returned by preview/apply
    |--------------------------------------------------------------------------
    */
    'sample_limit' => max(1, (int) env('SEARCH_REPLACE_SAMPLE_LIMIT', 50)),

    'snippet_max_chars' => max(40, (int) env('SEARCH_REPLACE_SNIPPET_CHARS', 160)),

];
