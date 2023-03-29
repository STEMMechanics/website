<?php

namespace App\Http\Controllers\Api;

use App\Conductors\MediaConductor;
use App\Conductors\PostConductor;
use App\Enum\HttpResponseCodes;
use App\Http\Requests\PostRequest;
use App\Models\Media;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\InvalidCastException;
use Illuminate\Database\Eloquent\MassAssignmentException;
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
            true,
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
                false,
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

    /**
     * Get a list of attachments related to this model.
     * 
     * @param Request $request The user request.
     * @param Post    $post    The post model.
     * @return JsonResponse Returns the post attachments.
     * @throws InvalidFormatException 
     * @throws BindingResolutionException 
     * @throws InvalidCastException 
     */
    public function getAttachments(Request $request, Post $post)
    {
        if (PostConductor::viewable($post) === true) {
            $medium = $post->attachments->map(function ($attachment) {
                return $attachment->media;
            });

            return $this->respondAsResource(MediaConductor::collection($request, $medium), true, null, 'attachment');
        }

        return $this->respondForbidden();
    }

    /**
     * Store an attachment related to this model.
     * 
     * @param Request $request The user request.
     * @param Post    $post    The post model.
     * @return JsonResponse The response.
     * @throws BindingResolutionException 
     * @throws MassAssignmentException 
     */
    public function storeAttachment(Request $request, Post $post)
    {
        if (PostConductor::updatable($post) === true) {
            if($request->has("medium") && Media::find($request->medium)) {
                $post->attachments()->create(['media_id' => $request->medium]);
                return $this->respondCreated();
            }

            return $this->respondWithErrors(['media' => 'The media ID was not found']);
        }

        return $this->respondForbidden();
    }

    /**
     * Update/replace attachments related to this model.
     * 
     * @param Request $request The user request.
     * @param Post    $post    The related model.
     * @return JsonResponse
     * @throws BindingResolutionException 
     * @throws MassAssignmentException 
     */
    public function updateAttachments(Request $request, Post $post)
    {
        if (PostConductor::updatable($post) === true) {
            $mediaIds = $request->attachments;
            if(is_array($mediaIds) === false) {
                $mediaIds = explode(',', $request->attachments);
            }
            
            $mediaIds = array_map('trim', $mediaIds); // trim each media ID
            $attachments = $post->attachments;
    
            // Delete attachments that are not in $mediaIds
            foreach ($attachments as $attachment) {
                if (!in_array($attachment->media_id, $mediaIds)) {
                    $attachment->delete();
                }
            }
    
            // Create new attachments for media IDs that are not already in $post->attachments()
            foreach ($mediaIds as $mediaId) {
                $found = false;
    
                foreach ($attachments as $attachment) {
                    if ($attachment->media_id == $mediaId) {
                        $found = true;
                        break;
                    }
                }
    
                if (!$found) {
                    $post->attachments()->create(['media_id' => $mediaId]);
                }
            }
    
            return $this->respondNoContent();
        }
    
        return $this->respondForbidden();
    }

    /**
     * Delete a specific related attachment.
     * @param Request $request The user request.
     * @param Post $post The model.
     * @param Media $medium The attachment medium.
     * @return JsonResponse 
     * @throws BindingResolutionException 
     */
    public function deleteAttachment(Request $request, Post $post, Media $medium)
    {
        if (PostConductor::updatable($post) === true) {
            $attachments = $post->attachments;
            $deleted = false;
    
            foreach ($attachments as $attachment) {
                if ($attachment->media_id === $medium->id) {
                    $attachment->delete();
                    $deleted = true;
                    break;
                }
            }
    
            if ($deleted) {
                // Attachment was deleted successfully
                return $this->respondNoContent();
            } else {
                // Attachment with matching media ID was not found
                return $this->respondNotFound();
            }
        }

        return $this->respondForbidden();
    }
}
