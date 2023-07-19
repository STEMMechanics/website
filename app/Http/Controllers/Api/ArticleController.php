<?php

namespace App\Http\Controllers\Api;

use App\Conductors\MediaConductor;
use App\Conductors\ArticleConductor;
use App\Enum\HttpResponseCodes;
use App\Http\Requests\ArticleRequest;
use App\Models\Media;
use App\Models\Article;
use App\Traits\HasAttachments;
use App\Traits\HasGallery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArticleController extends ApiController
{
    use HasAttachments;
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
            $article = Article::create($request->except(['attachments', 'gallery']));

            if ($request->has('attachments') === true) {
                $article->attachmentsAddMany($request->get('attachments'));
            }

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
            if ($request->has('attachments') === true) {
                $article->attachments()->delete();
                $article->attachmentsAddMany($request->get('attachments'));
            }

            if ($request->has('gallery') === true) {
                $article->gallery()->delete();
                $article->galleryAddMany($request->get('gallery'));
            }

            $article->update($request->except(['attachments', 'gallery']));
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
}
