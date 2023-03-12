<?php

namespace App\Http\Controllers\Api;

use App\Conductors\PostConductor;
use App\Enum\HttpResponseCodes;
use App\Http\Requests\PostRequest;
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
     * @param \Illuminate\Http\Request $request The endpoint request.
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        list($collection, $total) = PostConductor::request($request);

        return $this->respondAsResource(
            $collection,
            ['total' => $total]
        );
    }

    /**
     * Display the specified resource.
     *
     * @param \Illuminate\Http\Request $request The endpoint request.
     * @param  \App\Models\Post         $post    The post model.
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, Post $post)
    {
        if (PostConductor::viewable($post) === true) {
            return $this->respondAsResource(PostConductor::model($request, $post));
        }

        return $this->respondForbidden();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \App\Http\Requests\PostRequest $request The user request.
     * @return \Illuminate\Http\Response
     */
    public function store(PostRequest $request)
    {
        if (PostConductor::creatable() === true) {
            $post = Post::create($request->all());
            return $this->respondAsResource(
                PostConductor::model($request, $post),
                null,
                HttpResponseCodes::HTTP_CREATED
            );
        } else {
            return $this->respondForbidden();
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\PostRequest $request The post update request.
     * @param  \App\Models\Post               $post    The specified post.
     * @return \Illuminate\Http\Response
     */
    public function update(PostRequest $request, Post $post)
    {
        if (PostConductor::updatable($post) === true) {
            $post->update($request->all());
            return $this->respondAsResource(PostConductor::model($request, $post));
        }

        return $this->respondForbidden();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Post $post The specified post.
     * @return \Illuminate\Http\Response
     */
    public function destroy(Post $post)
    {
        if (PostConductor::destroyable($post) === true) {
            $post->delete();
            return $this->respondNoContent();
        } else {
            return $this->respondForbidden();
        }
    }
}
