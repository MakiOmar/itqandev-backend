<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchBuilderLinksRequest;
use App\Services\ContentTypes\ContentTypeRegistry;
use Illuminate\Http\JsonResponse;

/**
 * Builder URL fields: find published records of every enabled content type and return their public paths.
 */
class BuilderLinkSearchController extends Controller
{
    private const PER_TYPE = 8;

    public function __construct(private readonly ContentTypeRegistry $contentTypes) {}

    public function __invoke(SearchBuilderLinksRequest $request): JsonResponse
    {
        $query = (string) $request->validated('q', '');
        $only = $request->validated('type');

        $results = [];
        foreach ($this->contentTypes->all() as $key => $definition) {
            if ($only !== null && $only !== $key) {
                continue;
            }
            foreach ($definition->searchLinks($query, self::PER_TYPE) as $link) {
                $results[] = ['type' => $key, 'type_label' => $definition->pluralLabel()] + $link;
            }
        }

        return response()->json(['success' => true, 'data' => $results]);
    }
}
