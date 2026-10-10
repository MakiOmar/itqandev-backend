<?php

use App\Services\ContentTypes\Definitions\BlogPostContentType;
use App\Services\ContentTypes\Definitions\PageContentType;
use App\Services\ContentTypes\Definitions\ProjectContentType;
use App\Services\ContentTypes\Definitions\ServiceContentType;

/*
|--------------------------------------------------------------------------
| Content types
|--------------------------------------------------------------------------
|
| Public content types known to the Theme Builder: routing, theme conditions,
| chrome type defaults, loop grid sources and dynamic tags all read this list.
| Add a type by extending App\Services\ContentTypes\BaseContentType and listing
| it here. Types whose feature module is off (config/features.php) are skipped.
|
*/

return [
    'types' => [
        PageContentType::class,
        ProjectContentType::class,
        BlogPostContentType::class,
        ServiceContentType::class,
    ],
];
