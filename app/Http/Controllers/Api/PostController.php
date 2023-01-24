<?php

namespace App\Http\Controllers\Api;

use App\Enum\HttpResponseCodes;
use App\Filters\PostFilter;
use App\Http\Requests\PostStoreRequest;
use App\Http\Requests\PostUpdateRequest;
use App\Models\Post;
use Illuminate\Http\Request;

class PostController extends ApiController
{
    /**
     * ApplicationController constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum')
            ->only([
                'store',
                'update',
                'delete'
            ]);
    }

    /**
     * Display a listing of the resource.
     *
     * @param \App\Filters\PostFilter $filter Post filter request.
     * @return \Illuminate\Http\Response
     */
    public function index(PostFilter $filter)
    {
        return $this->respondAsResource(
            $filter->filter(),
            ['total' => $filter->foundTotal()]
        );
    }

    /**
     * Display the specified resource.
     *
     * @param  PostFilter       $filter The filter request.
     * @param  \App\Models\Post $post   The post model.
     * @return \Illuminate\Http\Response
     */
    public function show(PostFilter $filter, Post $post)
    {
        return $this->respondAsResource($filter->filter($post));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  PostStoreRequest $request The post store request.
     * @return \Illuminate\Http\Response
     */
    public function store(PostStoreRequest $request)
    {
        $post = Post::create($request->all());
        return $this->respondAsResource(
            (new PostFilter($request))->filter($post),
            null,
            HttpResponseCodes::HTTP_CREATED
        );
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  PostUpdateRequest $request The post update request.
     * @param  \App\Models\Post  $post    The specified post.
     * @return \Illuminate\Http\Response
     */
    public function update(PostUpdateRequest $request, Post $post)
    {
        $post->update($request->all());
        return $this->respondAsResource((new PostFilter($request))->filter($post));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Post $post The specified post.
     * @return \Illuminate\Http\Response
     */
    public function destroy(Post $post)
    {
        $post->delete();
        return $this->respondNoContent();
    }
}
