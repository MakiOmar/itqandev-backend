<?php

namespace App\Http\Controllers\Api;

use App\Models\BlogPost;
use App\Support\CacheKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BlogPostController extends Controller
{
    public function index(Request $request)
    {
        $cacheKey = CacheKey::for('blog_posts', ['list', $request->query()]);
        
        return Cache::remember($cacheKey, 3600, function () use ($request) {
            $query = BlogPost::with('author:id,name,email');
            
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            
            if ($request->has('featured')) {
                $query->where('featured', $request->boolean('featured'));
            }
            
            if ($request->has('author_id')) {
                $query->where('author_id', $request->author_id);
            }
            
            return $query->orderBy('created_at', 'desc')->paginate(20);
        });
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:blog_posts,slug'],
            'excerpt' => ['nullable', 'string', 'max:1024'],
            'content' => ['required', 'string'],
            'status' => ['required', 'string', 'in:draft,published,archived'],
            'featured' => ['boolean'],
            'published_at' => ['nullable', 'date'],
        ]);

        $data['author_id'] = $request->user()->id;

        $post = BlogPost::create($data);

        return response()->json($post->load('author:id,name,email'), 201);
    }

    public function show(BlogPost $blogPost)
    {
        $blogPost->load('author:id,name,email', 'seoMeta');

        return response()->json($blogPost);
    }

    public function update(Request $request, BlogPost $blogPost)
    {
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', \Illuminate\Validation\Rule::unique('blog_posts')->ignore($blogPost->id)],
            'excerpt' => ['nullable', 'string', 'max:1024'],
            'content' => ['sometimes', 'required', 'string'],
            'status' => ['sometimes', 'required', 'string', 'in:draft,published,archived'],
            'featured' => ['boolean'],
            'published_at' => ['nullable', 'date'],
        ]);

        $blogPost->update($data);

        return response()->json($blogPost->load('author:id,name,email'));
    }

    public function destroy(BlogPost $blogPost)
    {
        $blogPost->delete();

        return response()->noContent();
    }
}

