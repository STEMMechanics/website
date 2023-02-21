<?php

namespace App\Http\Controllers\Api;

use App\Enum\HttpResponseCodes;
use App\Filters\MediaFilter;
use App\Http\Requests\MediaStoreRequest;
use App\Http\Requests\MediaUpdateRequest;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;

class MediaController extends ApiController
{
    /**
     * ApplicationController constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum')
        ->only(['store','update','destroy']);
    }

    /**
     * Display a listing of the resource.
     *
     * @param \App\Filters\MediaFilter $filter Created filter object.
     * @return \Illuminate\Http\Response
     */
    public function index(MediaFilter $filter)
    {
        return $this->respondAsResource(
            $filter->filter(),
            ['total' => $filter->foundTotal()]
        );
    }

    /**
     * Display the specified resource.
     *
     * @param  MediaFilter $filter The request filter.
     * @param  Media       $medium The request media.
     * @return \Illuminate\Http\Response
     */
    public function show(MediaFilter $filter, Media $medium)
    {
        return $this->respondAsResource($filter->filter($medium));
    }

    /**
     * Store a new media resource
     *
     * @param  MediaStoreRequest $request The uploaded media.
     * @return \Illuminate\Http\Response
     */
    public function store(MediaStoreRequest $request)
    {
        $file = $request->file('file');
        if ($file === null) {
            return $this->respondError(['file' => 'An error occurred uploading the file to the server.']);
        }

        if ($file->isValid() !== true) {
            switch ($file->getError()) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    return $this->respondTooLarge();
                case UPLOAD_ERR_PARTIAL:
                    return $this->respondError(['file' => 'The file upload was interrupted.']);
                default:
                    return $this->respondError(['file' => 'An error occurred uploading the file to the server.']);
            }
        }

        if ($file->getSize() > Media::maxUploadSize()) {
            return $this->respondTooLarge();
        }

        $title = $file->getClientOriginalName();
        $mime = $file->getMimeType();
        $fileInfo = Media::store($file, empty($request->input('permission')));
        if ($fileInfo === null) {
            return $this->respondError(
                ['file' => 'The file could not be stored on the server'],
                HttpResponseCodes::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        $request->merge([
            'title' => $title,
            'mime' => $mime,
            'name' => $fileInfo['name'],
            'size' => filesize($fileInfo['path'])
        ]);

        $media = $request->user()->media()->create($request->all());
        return $this->respondAsResource((new MediaFilter($request))->filter($media));
    }

    /**
     * Update the media resource in storage.
     *
     * @param  MediaUpdateRequest $request The update request.
     * @param  \App\Models\Media  $medium  The specified media.
     * @return \Illuminate\Http\Response
     */
    public function update(MediaUpdateRequest $request, Media $medium)
    {
        if ((new MediaFilter($request))->filter($medium) === null) {
            return $this->respondNotFound();
        }

        $file = $request->file('file');
        if ($file !== null) {
            if ($file->getSize() > Media::maxUploadSize()) {
                return $this->respondTooLarge();
            }

            $oldPath = $medium->path();
            $fileInfo = Media::store($file, empty($request->input('permission')));
            if ($fileInfo === null) {
                return $this->respondError(
                    ['file' => 'The file could not be stored on the server'],
                    HttpResponseCodes::HTTP_INTERNAL_SERVER_ERROR
                );
            }

            if (file_exists($oldPath) === true) {
                unlink($oldPath);
            }

            $request->merge([
                'title' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType(),
                'name' => $fileInfo['name'],
                'size' => filesize($fileInfo['path'])
            ]);
        }//end if

        $medium->update($request->all());
        return $this->respondWithTransformer($file);
    }



    /**
     * Remove the specified resource from storage.
     *
     * @param Request           $request Request instance.
     * @param  \App\Models\Media $medium  Specified media file.
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, Media $medium)
    {
        if ((new MediaFilter($request))->filter($medium) !== null) {
            if (file_exists($medium->path()) === true) {
                unlink($medium->path());
            }

            $medium->delete();
            return $this->respondNoContent();
        }

        return $this->respondNotFound();
    }

    /**
     * Display the specified resource.
     *
     * @param  Request           $request Request instance.
     * @param  \App\Models\Media $medium  Specified media.
     * @return \Illuminate\Http\Response
     */
    public function download(Request $request, Media $medium)
    {
        $respondJson = in_array('application/json', explode(',', $request->header('Accept', 'application/json')));

        $headers = [];
        $path = $medium->path();

        /* File exists */
        if (file_exists($path) === false) {
            if ($respondJson === false) {
                return redirect('/not-found');
            } else {
                return $this->respondNotFound();
            }
        }

        $updated_at = Carbon::parse(filemtime($path));

        $headerPragma = 'no-cache';
        $headerCacheControl = 'max-age=0, must-revalidate';
        $headerExpires = $updated_at->toRfc2822String();

        if (empty($medium->permission) === true) {
            if ($request->user() === null && $request->has('token') === true) {
                $accessToken = PersonalAccessToken::findToken(urldecode($request->input('token')));

                if (
                    $accessToken !== null && (config('sanctum.expiration') === null ||
                    $accessToken->created_at->lte(now()->subMinutes(config('sanctum.expiration'))) === false)
                ) {
                    $user = $accessToken->tokenable;
                }
            }
            if ($request->user() === null || $user->hasPermission($medium->permission) === false) {
                if ($respondJson === false) {
                    return redirect('/login?redirect=' . $request->path());
                } else {
                    return $this->respondForbidden();
                }
            }
        } else {
            $headerPragma = 'public';
            $headerExpires = $updated_at->addMonth()->toRfc2822String();
        }//end if

        // deepcode ignore InsecureHash: Browsers expect Etag to be a md5 hash
        $headerEtag = md5($updated_at->format('U'));
        $headerLastModified = $updated_at->toRfc2822String();

        $headers = [
            'Cache-Control' => $headerCacheControl,
            'Content-Disposition' => sprintf('inline; filename="%s"', basename($path)),
            'Etag' => $headerEtag,
            'Expires' => $headerExpires,
            'Last-Modified' => $headerLastModified,
            'Pragma' => $headerPragma,
        ];

        $server = request()->server;

        $requestModifiedSince = $server->has('HTTP_IF_MODIFIED_SINCE') &&
        $server->get('HTTP_IF_MODIFIED_SINCE') === $headerLastModified;

        $requestNoneMatch = $server->has('HTTP_IF_NONE_MATCH') &&
        $server->get('HTTP_IF_NONE_MATCH') === $headerEtag;

        if ($requestModifiedSince === true || $requestNoneMatch === true) {
            return response()->make('', 304, $headers);
        }

        return response()->file($path, $headers);
    }
}
