<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\ChromeLayout;
use App\Models\Form;
use App\Models\Page;
use App\Models\Project;
use App\Models\Service;
use App\Models\Skill;
use App\Support\UniqueContentSlug;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ContentSlugSuggestionController extends Controller
{
    public function suggest(Request $request)
    {
        $data = $request->validate([
            'entity' => [
                'required',
                'string',
                'in:projects,blog_posts,services,categories,skills,pages,forms,chrome_headers,chrome_footers,chrome_bodies',
            ],
            'source' => ['required', 'string', 'max:255'],
            'ignore_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $chromeKind = match ($data['entity']) {
            'chrome_headers' => ChromeLayout::KIND_HEADER,
            'chrome_footers' => ChromeLayout::KIND_FOOTER,
            'chrome_bodies' => ChromeLayout::KIND_BODY,
            default => null,
        };

        $modelClass = null;
        if ($chromeKind !== null) {
            $this->authorize('manageSettings');
        } else {
            $modelClass = match ($data['entity']) {
                'projects' => Project::class,
                'blog_posts' => BlogPost::class,
                'services' => Service::class,
                'categories' => Category::class,
                'skills' => Skill::class,
                'pages' => Page::class,
                'forms' => Form::class,
            };
            $this->authorize('viewAny', $modelClass);
        }

        $base = Str::slug($data['source']);
        if ($base === '') {
            return response()->json([
                'message' => 'Could not derive a slug from the given text.',
            ], 422);
        }

        $ignoreId = isset($data['ignore_id']) ? (int) $data['ignore_id'] : null;

        if ($chromeKind !== null) {
            $slug = UniqueContentSlug::suggestFromQuery(
                ChromeLayout::query()->kind($chromeKind),
                $base,
                $ignoreId
            );
        } else {
            /** @var class-string<\Illuminate\Database\Eloquent\Model> $modelClass */
            $slug = UniqueContentSlug::fromSource($modelClass, $data['source'], $ignoreId);
        }

        return response()->json([
            'slug' => $slug,
            'base' => $base,
        ]);
    }
}
