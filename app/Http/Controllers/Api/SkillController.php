<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class SkillController extends Controller
{
    public function index()
    {
        $skills = Cache::remember(
            'skills:list',
            now()->addMinutes(30),
            fn () => Skill::withCount('projects')->orderBy('name')->get()
        );

        return response()->json($skills);
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

        return response()->json($skill, 201);
    }

    public function show(Skill $skill)
    {
        $skill->load('projects:id,title');

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

        return response()->json($skill);
    }

    public function destroy(Skill $skill)
    {
        $skill->delete();

        return response()->noContent();
    }

    public function bulkDelete(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:skills,id'],
        ]);

        $count = Skill::whereIn('id', $data['ids'])->delete();

        return response()->json([
            'deleted' => $count,
            'message' => 'Deleted ' . $count . ' skills',
        ]);
    }
}
