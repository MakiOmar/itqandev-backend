<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;

class SkillController extends Controller
{
    public function index()
    {
        // Note: Authorization check would go here if SkillPolicy exists
        // For now, assuming same pattern as categories

        return response()->json(
            Cache::remember('skills:list', 3600, function () {
                return Skill::withCount('projects')
                    ->with('media')
                    ->orderBy('name')
                    ->get();
            })
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:skills,slug'],
            'description' => ['nullable', 'string', 'max:1024'],
            'icon_hint' => ['nullable', 'string', 'max:255'],
        ]);

        $skill = Skill::create($data);
        // Cache invalidation handled by InvalidatesCache trait

        return response()->json($skill, 201);
    }

    public function show(Skill $skill)
    {
        $skill->load([
            'projects:id,title',
            'media' => function ($query) {
                $query->where('collection_name', 'icon');
            }
        ]);

        return response()->json($skill);
    }

    public function update(Request $request, Skill $skill)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('skills')->ignore($skill->id)],
            'description' => ['nullable', 'string', 'max:1024'],
            'icon_hint' => ['nullable', 'string', 'max:255'],
        ]);

        $skill->update($data);
        // Cache invalidation handled by InvalidatesCache trait

        return response()->json($skill);
    }

    public function destroy(Skill $skill)
    {
        $skill->delete();
        // Cache invalidation handled by InvalidatesCache trait

        return response()->noContent();
    }

    public function bulkDelete(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:skills,id'],
        ]);

        $count = Skill::whereIn('id', $data['ids'])->delete();
        // Cache invalidation handled by InvalidatesCache trait on model events

        return response()->json([
            'deleted' => $count,
            'message' => 'Deleted ' . $count . ' skills',
        ]);
    }
}
