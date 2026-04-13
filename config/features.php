<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Whitelabel / module toggles (config & .env only)
    |--------------------------------------------------------------------------
    |
    | These flags are not stored in project-settings.json and cannot be changed
    | from the admin UI. Forks set them per deployment via FEATURE_* in .env
    | or by editing values under `defaults` below when the env var is omitted.
    |
    | projects — testimonial forms may link to portfolio projects (loads /v1/projects).
    |
    */

    'defaults' => [
        'projects' => true,
    ],

    /*
    | When set to true, false, 0, 1, yes, no, on, off (case-insensitive), this
    | overrides `defaults.projects`. Leave unset or empty to use the default.
    */
    'projects' => env('FEATURE_PROJECTS'),

];
