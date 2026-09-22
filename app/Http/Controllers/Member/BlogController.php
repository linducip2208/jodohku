<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function index(Request $request)
    {
        $posts = BlogPost::published()->with('author')->orderByDesc('published_at')->paginate(12);

        return $request->wantsJson()
            ? response()->json($posts)
            : view('member.blog.index', ['posts' => $posts]);
    }

    public function show(Request $request, string $slug)
    {
        $post = BlogPost::published()->where('slug', $slug)->firstOrFail();
        $post->recordView();

        return $request->wantsJson()
            ? response()->json($post->fresh())
            : view('member.blog.show', ['post' => $post->fresh()]);
    }

    public function popular(Request $request)
    {
        $posts = BlogPost::published()->with('author')
            ->orderByDesc('view_count')->orderByDesc('published_at')->limit(10)->get();

        return response()->json($posts);
    }

    public function related(Request $request, string $slug)
    {
        $post = BlogPost::published()->where('slug', $slug)->firstOrFail();
        $related = BlogPost::published()->with('author')
            ->where('id', '!=', $post->id)
            ->where('user_id', $post->user_id)
            ->orderByDesc('published_at')->limit(5)->get();
        if ($related->isEmpty()) {
            $tokens = preg_split('/[\s\-]+/', strtolower((string) $post->title)) ?: [];
            $related = BlogPost::published()->with('author')
                ->where('id', '!=', $post->id)
                ->where(function ($q) use ($tokens) {
                    foreach (array_slice($tokens, 0, 4) as $t) {
                        $q->orWhere('title', 'like', "%{$t}%");
                    }
                })
                ->orderByDesc('published_at')->limit(5)->get();
        }

        return response()->json($related);
    }

    public function search(Request $request)
    {
        $request->validate(['q' => ['required', 'string', 'max:255']]);
        $q = $request->string('q');
        $posts = BlogPost::published()->with('author')
            ->where(function ($query) use ($q) {
                $query->where('title', 'like', "%{$q}%")
                    ->orWhere('excerpt', 'like', "%{$q}%")
                    ->orWhere('body', 'like', "%{$q}%");
            })
            ->orderByDesc('published_at')->paginate(20);

        return response()->json($posts);
    }
}
