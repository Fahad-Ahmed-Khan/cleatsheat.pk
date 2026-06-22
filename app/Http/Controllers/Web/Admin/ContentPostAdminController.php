<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreContentPostRequest;
use App\Http\Requests\Admin\UpdateContentPostRequest;
use App\Models\ContentPost;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ContentPostAdminController extends Controller
{
    public function index(): Response
    {
        $posts = ContentPost::query()->latest()->paginate(25);

        return Inertia::render('Admin/Content/Index', [
            'posts' => $posts,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Content/Create');
    }

    public function store(StoreContentPostRequest $request): RedirectResponse
    {
        ContentPost::query()->create($request->validated());

        return redirect()->route('admin.content-posts.index')->with('status', 'Article created.');
    }

    public function edit(ContentPost $content_post): Response
    {
        return Inertia::render('Admin/Content/Edit', [
            'post' => [
                'id' => $content_post->id,
                'slug' => $content_post->slug,
                'title' => $content_post->title,
                'meta_title' => $content_post->meta_title,
                'meta_description' => $content_post->meta_description,
                'excerpt' => $content_post->excerpt,
                'featured_image_url' => $content_post->featured_image_url,
                'body' => $content_post->body,
                'pillar_keyword' => $content_post->pillar_keyword,
                'is_published' => (bool) $content_post->is_published,
                'published_at_local' => $content_post->published_at?->timezone(config('app.timezone'))->format('Y-m-d\TH:i'),
            ],
        ]);
    }

    public function update(UpdateContentPostRequest $request, ContentPost $content_post): RedirectResponse
    {
        $content_post->update($request->validated());

        return redirect()->route('admin.content-posts.index')->with('status', 'Article updated.');
    }

    public function destroy(ContentPost $content_post): RedirectResponse
    {
        $content_post->delete();

        return redirect()->route('admin.content-posts.index')->with('status', 'Article deleted.');
    }
}
