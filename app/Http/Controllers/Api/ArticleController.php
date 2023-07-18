<?php

namespace App\Http\Controllers\Api;

use App\Conductors\MediaConductor;
use App\Conductors\ArticleConductor;
use App\Enum\HttpResponseCodes;
use App\Http\Requests\ArticleRequest;
use App\Models\Media;
use App\Models\Article;
use App\Models\Gallery;
use App\Traits\HasGallery;
use Illuminate\Http\JsonResponse;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\InvalidCastException;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Http\Request;

class ArticleController extends ApiController
{
    use HasGallery;


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
        list($collection, $total) = ArticleConductor::request($request);

        return $this->respondAsResource(
            $collection,
            ['isCollection' => true,
                'appendData' => ['total' => $total]
            ]
        );
    }

    /**
     * Display the specified resource.
     *
     * @param \Illuminate\Http\Request $request The endpoint request.
     * @param  \App\Models\Article      $article The article model.
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, Article $article)
    {
        if (ArticleConductor::viewable($article) === true) {
            return $this->respondAsResource(ArticleConductor::model($request, $article));
        }

        return $this->respondForbidden();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \App\Http\Requests\ArticleRequest $request The user request.
     * @return \Illuminate\Http\Response
     */
    public function store(ArticleRequest $request)
    {
        if (ArticleConductor::creatable() === true) {
            $article = Article::create($request->except('gallery'));

            if ($request->has('gallery') === true) {
                $article->galleryAddMany($request->get('gallery'));
            }

            return $this->respondAsResource(
                ArticleConductor::model($request, $article),
                ['respondCode' => HttpResponseCodes::HTTP_CREATED]
            );
        } else {
            return $this->respondForbidden();
        }//end if
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\ArticleRequest $request The article update request.
     * @param  \App\Models\Article               $article The specified article.
     * @return \Illuminate\Http\Response
     */
    public function update(ArticleRequest $request, Article $article)
    {
        if (ArticleConductor::updatable($article) === true) {
            if ($request->has('gallery') === true) {
                $article->gallery()->delete();
                $article->galleryAddMany($request->get('gallery'));
            }

            $article->update($request->except('gallery'));
            return $this->respondAsResource(ArticleConductor::model($request, $article));
        }

        return $this->respondForbidden();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Article $article The specified article.
     * @return \Illuminate\Http\Response
     */
    public function destroy(Article $article)
    {
        if (ArticleConductor::destroyable($article) === true) {
            $article->delete();
            return $this->respondNoContent();
        } else {
            return $this->respondForbidden();
        }
    }

    /**
     * Get a list of attachments related to this model.
     *
     * @param Request $request The user request.
     * @param Article $article The article model.
     * @return JsonResponse Returns the article attachments.
     */
    public function attachmentIndex(Request $request, Article $article): JsonResponse
    {
        if (ArticleConductor::viewable($article) === true) {
            $medium = $article->attachments->map(function ($attachment) {
                return $attachment->media;
            });

            return $this->respondAsResource(MediaConductor::collection($request, $medium), ['isCollection' => true, 'resourceName' => 'attachment']);
        }

        return $this->respondForbidden();
    }

    /**
     * Store an attachment related to this model.
     *
     * @param Request $request The user request.
     * @param Article $article The article model.
     * @return JsonResponse The response.
     */
    public function attachmentStore(Request $request, Article $article): JsonResponse
    {
        if (ArticleConductor::updatable($article) === true) {
            if ($request->has("medium") === true && Media::find($request->medium) !== null) {
                $article->attachments()->create(['media_id' => $request->medium]);
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
     * @param Article $article The related model.
     * @return JsonResponse
     */
    public function attachmentUpdate(Request $request, Article $article): JsonResponse
    {
        if (ArticleConductor::updatable($article) === true) {
            $mediaIds = $request->attachments;
            if (is_array($mediaIds) === false) {
                $mediaIds = explode(',', $request->attachments);
            }

            $mediaIds = array_map('trim', $mediaIds); // trim each media ID
            $attachments = $article->attachments;

            // Delete attachments that are not in $mediaIds
            foreach ($attachments as $attachment) {
                if (in_array($attachment->media_id, $mediaIds) === false) {
                    $attachment->delete();
                }
            }

            // Create new attachments for media IDs that are not already in $article->attachments()
            foreach ($mediaIds as $mediaId) {
                $found = false;

                foreach ($attachments as $attachment) {
                    if ($attachment->media_id === $mediaId) {
                        $found = true;
                        break;
                    }
                }

                if ($found === false) {
                    $article->attachments()->create(['media_id' => $mediaId]);
                }
            }

            return $this->respondNoContent();
        }//end if

        return $this->respondForbidden();
    }

    /**
     * Delete a specific related attachment.
     * @param Request $request The user request.
     * @param Article $article The model.
     * @param Media   $medium  The attachment medium.
     * @return JsonResponse
     */
    public function attachmentDelete(Request $request, Article $article, Media $medium): JsonResponse
    {
        if (ArticleConductor::updatable($article) === true) {
            $attachments = $article->attachments;
            $deleted = false;

            foreach ($attachments as $attachment) {
                if ($attachment->media_id === $medium->id) {
                    $attachment->delete();
                    $deleted = true;
                    break;
                }
            }

            if ($deleted === true) {
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
