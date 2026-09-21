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
}
