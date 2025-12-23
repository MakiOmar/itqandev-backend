<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SeoMetaController extends Controller
{
    public function update(Request $request, string $type, int $id)
    {
        $model = $this->resolveModel($type, $id);

        $data = $request->validate([
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:512'],
            'canonical_url' => ['nullable', 'url'],
            'og_title' => ['nullable', 'string', 'max:255'],
            'og_description' => ['nullable', 'string', 'max:512'],
            'og_image' => ['nullable', 'url'],
            'twitter_card' => ['nullable', 'string', 'max:50'],
            'schema' => ['nullable', 'array'],
        ]);

        $meta = $model->seoMeta()->updateOrCreate([], $data);

        return response()->json($meta);
    }

    protected function resolveModel(string $type, int $id): Model
    {
        return match ($type) {
            'project' => Project::findOrFail($id),
            'category' => Category::findOrFail($id),
            default => throw ValidationException::withMessages(['type' => 'نوع غير مدعوم']),
        };
    }
}

