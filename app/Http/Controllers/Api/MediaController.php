<?php

namespace App\Http\Controllers\Api;

use App\Conductors\MediaConductor;
use App\Enum\HttpResponseCodes;
use App\Http\Requests\MediaRequest;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
     * @param \Illuminate\Http\Request $request The endpoint request.
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        list($collection, $total) = MediaConductor::request($request);

        return $this->respondAsResource(
            $collection,
            ['total' => $total]
        );
    }

    /**
     * Display the specified resource.
     *
     * @param \Illuminate\Http\Request $request The endpoint request.
     * @param  \App\Models\Media        $medium  The request media.
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, Media $medium)
    {
        if (MediaConductor::viewable($medium) === true) {
            return $this->respondAsResource(MediaConductor::model($request, $medium));
        }

        return $this->respondForbidden();
    }

    /**
     * Store a new media resource
     *
     * @param  \App\Http\Requests\MediaRequest $request The uploaded media.
     * @return \Illuminate\Http\Response
     */
    public function store(MediaRequest $request)
    {
        if (MediaConductor::creatable() === true) {
            $file = $request->file('file');
            if ($file === null) {
                return $this->respondWithErrors(['file' => 'The browser did not upload the file correctly to the server.']);
            }

            if ($file->isValid() !== true) {
                switch ($file->getError()) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        return $this->respondTooLarge();
                    case UPLOAD_ERR_PARTIAL:
                        return $this->respondWithErrors(['file' => 'The file upload was interrupted.']);
                    default:
                        return $this->respondWithErrors(['file' => 'An error occurred uploading the file to the server.']);
                }
            }

            if ($file->getSize() > Media::maxUploadSize()) {
                return $this->respondTooLarge();
            }

            $title = $file->getClientOriginalName();
            $mime = $file->getMimeType();
            $fileInfo = Media::store($file, empty($request->input('permission')));
            if ($fileInfo === null) {
                return $this->respondWithErrors(
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
            return $this->respondAsResource(
                MediaConductor::model($request, $media),
                null,
                HttpResponseCodes::HTTP_CREATED
            );
        }//end if

        return $this->respondForbidden();
    }

    /**
     * Update the media resource in storage.
     *
     * @param  \App\Http\Requests\MediaRequest $request The update request.
     * @param  \App\Models\Media               $medium  The specified media.
     * @return \Illuminate\Http\Response
     */
    public function update(MediaRequest $request, Media $medium)
    {
        if (MediaConductor::updatable($medium) === true) {
            $file = $request->file('file');
            if ($file !== null) {
                if ($file->getSize() > Media::maxUploadSize()) {
                    return $this->respondTooLarge();
                }

                $oldPath = $medium->path();
                $fileInfo = Media::store($file, empty($request->input('permission')));
                if ($fileInfo === null) {
                    return $this->respondWithErrors(
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
            return $this->respondAsResource(MediaConductor::model($request, $medium));
        }//end if

        return $this->respondForbidden();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Media $medium Specified media file.
     * @return \Illuminate\Http\Response
     */
    public function destroy(Media $medium)
    {
        if (MediaConductor::destroyable($medium) === true) {
            if (file_exists($medium->path()) === true) {
                unlink($medium->path());
            }

            $medium->delete();
            return $this->respondNoContent();
        }

        return $this->respondForbidden();
    }

    /**
     * Display the specified resource.
     *
     * @param \Illuminate\Http\Request $request The endpoint request.
     * @param  \App\Models\Media        $medium  Specified media.
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
