<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Category;
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
            'entity' => ['required', 'string', 'in:projects,blog_posts,services,categories,skills'],
            'source' => ['required', 'string', 'max:255'],
            'ignore_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $modelClass = match ($data['entity']) {
            'projects' => Project::class,
            'blog_posts' => BlogPost::class,
            'services' => Service::class,
            'categories' => Category::class,
            'skills' => Skill::class,
        };

        $this->authorize('viewAny', $modelClass);

        $base = Str::slug($data['source']);
        if ($base === '') {
            return response()->json([
                'message' => 'Could not derive a slug from the given text.',
            ], 422);
        }

        $ignoreId = isset($data['ignore_id']) ? (int) $data['ignore_id'] : null;

        $slug = UniqueContentSlug::suggest($modelClass, $base, $ignoreId);

        return response()->json([
            'slug' => $slug,
            'base' => $base,
        ]);
    }
}
