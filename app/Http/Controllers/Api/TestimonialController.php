<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TestimonialController extends Controller
{
    public function index(Request $request)
    {
        $query = Testimonial::with('project:id,title');

        if ($request->has('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->has('approved')) {
            $query->where('approved', $request->boolean('approved'));
        }

        return $query->orderBy('created_at', 'desc')->paginate(20);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'client_name' => ['required', 'string', 'max:255'],
            'client_role' => ['nullable', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'content' => ['required', 'string'],
            'video_url' => ['nullable', 'url'],
            'approved' => ['boolean'],
        ]);

        $testimonial = Testimonial::create($data);

        return response()->json($testimonial->load('project:id,title'), 201);
    }

    public function show(Testimonial $testimonial)
    {
        return response()->json($testimonial->load('project:id,title'));
    }

    public function update(Request $request, Testimonial $testimonial)
    {
        $data = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'client_name' => ['sometimes', 'required', 'string', 'max:255'],
            'client_role' => ['nullable', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'rating' => ['sometimes', 'required', 'integer', 'min:1', 'max:5'],
            'content' => ['sometimes', 'required', 'string'],
            'video_url' => ['nullable', 'url'],
            'approved' => ['boolean'],
        ]);

        $testimonial->update($data);

        return response()->json($testimonial->load('project:id,title'));
    }

    public function destroy(Testimonial $testimonial)
    {
        $testimonial->delete();

        return response()->json(null, 204);
    }

    public function bulkDelete(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:testimonials,id'],
        ]);

        $count = Testimonial::whereIn('id', $data['ids'])->delete();

        return response()->json([
            'deleted' => $count,
            'message' => 'Deleted ' . $count . ' testimonials',
        ]);
    }
}

