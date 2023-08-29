<?php

namespace App\Http\Controllers\Api;

use App\Conductors\MediaConductor;
use App\Enum\HttpResponseCodes;
use App\Http\Requests\MediaRequest;
use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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
            ['isCollection' => true,
                'appendData' => ['total' => $total]
            ],
            function ($options) {
                return $options['total'] === 0;
            }
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
        $temporaryFilePath = '';
        $transformData = [];

        if (MediaConductor::creatable() === false) {
            return $this->respondForbidden();
        }

        $file = $request->file('file');
        if ($file === null) {
            return $this->respondWithErrors(['file' => 'The browser did not upload the file correctly to the server.']);
        }

        $jsonResult = $this->validateFileItem($file);
        if ($jsonResult !== null) {
            return $jsonResult;
        }

        if($request->has('chunk') === true && $request->has('transform') === true) {
            return $this->respondWithErrors(['transform' => 'Transforms cannot be applied when uploading a file in chunks']);
        }

        if($request->has('chunk') === false || $request->get('chunk') === '1') {
            $request->merge([
                'title' => $request->get('title', ''),
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'status' => 'Processing Media',
            ]);

            // We store images by default locally
            if ($request->get('storage') === null) {
                if (strpos($file->getMimeType(), 'image/') === 0) {
                    $request->merge([
                        'storage' => 'local',
                    ]);
                } else {
                    $request->merge([
                        'storage' => 'cdn',
                    ]);
                }
            }

            $mediaItem = $request->user()->media()->create($request->except(['file','transform']));

            $temporaryFilePath = generateTempFilePath(pathinfo($mediaItem->name, PATHINFO_EXTENSION), $request->get('chunk', ''));
            copy($file->path(), $temporaryFilePath);

            $transformData = ['file' => [
                'path' => $temporaryFilePath,
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
            ]
            ];

            if ($request->has('transform') === true) {
                $transformData = array_merge($transformData, array_map('trim', explode(',', $request->get('transform'))));
            }
        } else {
            if($request->has('id')) {
                $mediaItem = Media::find($request->get('id'));
                if($mediaItem && $mediaItem->exists()) {
                    $temporaryFilePath = generateTempFilePath(pathinfo($mediaItem->name, PATHINFO_EXTENSION), $request->get('chunk', ''));
                    copy($file->path(), $temporaryFilePath);
                } else {
                    return $this->respondNotFound();
                }
            } else {
                return $this->respondWithErrors(['id' => 'Media ID is required']);
            }
        }

        $finalize = true;

        if($request->has('chunk') == true) {
            if($temporaryFilePath === '') {
                return response()->json([
                    'message' => 'A server error occurred. Please try again later - temporaryFilePath'
                ], 500);
            }

            $tempInfo = tempFileInfo($temporaryFilePath);

            for($i = 1; $i <= intval($request->get('chunk_count', '1')); $i++) {
                if(tempFileExists($tempInfo['dirname'], $tempInfo['filename'], $tempInfo['extension'], $i) === false) {
                    $finalize = false;
                    break;
                }
            }

            if($finalize === true) {
                $newTempFile = generateTempFilePath($tempInfo['extension']);

                for($i = 1; $i <= intval($request->get('chunk_count', '1')); $i++) {
                    $tempFileName = constructTempFileName($tempInfo['dirname'], $tempInfo['filename'], $tempInfo['extension'], $i);
                    if(file_exists($tempFileName) === false) {
                        return response()->json([
                            'message' => 'A server error occurred. Please try again later - tempFileName no exist'
                        ], 500);
                    }

                    $chunkContents = file_get_contents($tempFileName);
                    if ($chunkContents === false) {
                        return response()->json([
                            'message' => 'A server error occurred. Please try again later - chunkContents empty'
                        ], 500);
                    }

                    file_put_contents($newTempFile, $chunkContents, FILE_APPEND);
                    unlink($tempFileName);
                }

                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $newTempFile);
                finfo_close($finfo);

                $transformData = ['file' => [
                    'path' => $newTempFile,
                    'size' => filesize($newTempFile),
                    'mime_type' => $mime,
                ]
                ];
            }
        }
        
        if($finalize === true) {
            $mediaItem->transform($transformData);
        }

        return $this->respondAsResource(
            MediaConductor::model($request, $mediaItem),
            ['respondCode' => HttpResponseCodes::HTTP_ACCEPTED]
        );
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
        if (MediaConductor::updatable($medium) === false) {
            return $this->respondForbidden();
        }

        $file = $request->file('file');
        if ($file !== null) {
            $jsonResult = $this->validateFileItem($file);
            if ($jsonResult !== null) {
                return $jsonResult;
            }
        }

        $medium->status('Updating Media');
        $medium->update($request->except(['file','transform']));

        $transformData = [];
        if ($file !== null) {
            $temporaryFilePath = generateTempFilePath();
            copy($file->path(), $temporaryFilePath);

            $transformData = array_merge($transformData, ['file' => [
                'path' => $temporaryFilePath,
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
            ]
            ]);
        }

        if ($request->has('transform') === true) {
            $transformData = array_merge($transformData, array_map('trim', explode(',', $request->get('transform'))));
        }

        if (count($transformData) > 0) {
            $medium->transform($transformData);
        } else {
            $medium->ok();
        }

        return $this->respondAsResource(MediaConductor::model($request, $medium));
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

    /**
     * Validate a File item in a request is valid
     *
     * @param UploadedFile $file     The file to validate.
     * @param string       $errorKey The error key to use.
     * @return JsonResponse|null
     */
    private function validateFileItem(UploadedFile $file, string $errorKey = 'file'): JsonResponse|null
    {
        if ($file->isValid() !== true) {
            switch ($file->getError()) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    return $this->respondTooLarge();
                case UPLOAD_ERR_PARTIAL:
                    return $this->respondWithErrors([$errorKey => 'The file upload was interrupted.']);
                default:
                    return $this->respondWithErrors([$errorKey => 'An error occurred uploading the file to the server.']);
            }
        }

        if ($file->getSize() > Media::getMaxUploadSize()) {
            return $this->respondTooLarge();
        }

        return null;
    }
}
