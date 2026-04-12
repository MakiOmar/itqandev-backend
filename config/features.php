<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Project module (case studies / portfolio) — testimonial linking
    |--------------------------------------------------------------------------
    |
    | When set to true, false, 0, 1, yes, no, on, off (case-insensitive), this
    | value overrides settings.features.projects for API responses (GET /settings).
    | Leave unset so only persisted project-settings.json / admin UI applies.
    |
    */

    'projects' => env('FEATURE_PROJECTS'),

];
