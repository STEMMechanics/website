<?php

namespace App\Http\Controllers;

use App\Helpers;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PostController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Post::query();

        $query->where('status', 'published');

        if($request->has('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
            $query->orWhere('content', 'like', '%' . $request->search . '%');
        }

        $posts = $query->orderBy('created_at', 'desc')->paginate(12)->onEachSide(1);

        return view('post.index', [
            'posts' => $posts
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function admin_index(Request $request)
    {
        $query = Post::query();

        if($request->has('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
            $query->orWhere('content', 'like', '%' . $request->search . '%');
        }

        $posts = $query->orderBy('created_at', 'desc')->paginate(12)->onEachSide(1);

        return view('admin.post.index', [
            'posts' => $posts
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function admin_create()
    {
        return view('admin.post.edit');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function admin_store(Request $request)
    {
        $request->validate([
            'title' => 'required',
            'content' => 'required',
            'hero_media_name' => 'required|exists:media,name',
        ], [
            'title.required' => __('validation.custom_messages.title_required'),
            'content.required' => __('validation.custom_messages.content_required'),
            'hero_media_name.required' => __('validation.custom_messages.hero_media_name_required'),
        ]);

        $postData = $request->all();
        $postData['user_id'] = auth()->user()->id;

        $post = Post::create($postData);
        $post->updateFiles($request->input('files'));
        $post->updateFiles($request->input('gallery'), 'gallery');

        session()->flash('message', 'Post has been created');
        session()->flash('message-title', 'Post created');
        session()->flash('message-type', 'success');
        return redirect()->route('admin.post.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(Post $post)
    {
        return view('post.show', ['post' => $post]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function admin_edit(Post $post)
    {
        $fileNameList = $post->files->pluck('name')->toArray();
        $post->files = $fileNameList;

        return view('admin.post.edit', ['post' => $post]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function admin_update(Request $request, Post $post)
    {
        $request->validate([
            'title' => 'required',
            'content' => 'required',
            'hero_media_name' => 'required|exists:media,name',
        ], [
            'title.required' => __('validation.custom_messages.title_required'),
            'content.required' => __('validation.custom_messages.content_required'),
            'hero_media_name.required' => __('validation.custom_messages.hero_media_name_required'),
        ]);

        $postData = $request->all();
        $post->update($postData);
        $post->updateFiles($request->input('files'));
        $post->updateFiles($request->input('gallery'), 'gallery');

        session()->flash('message', 'Post has been updated');
        session()->flash('message-title', 'Post updated');
        session()->flash('message-type', 'success');
        return redirect()->route('admin.post.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function admin_destroy(Post $post)
    {
        $post->delete();
        session()->flash('message', 'Post has been deleted');
        session()->flash('message-title', 'Post deleted');
        session()->flash('message-type', 'danger');

        return redirect()->route('admin.post.index');
    }
}
