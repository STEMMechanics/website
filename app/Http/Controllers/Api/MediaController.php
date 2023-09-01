<?php

namespace App\Http\Controllers\Api;

use App\Conductors\MediaConductor;
use App\Conductors\MediaJobConductor;
use App\Enum\HttpResponseCodes;
use App\Http\Requests\MediaRequest;
use App\Models\Media;
use App\Models\MediaJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
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
        // allowed to create a media item
        if (MediaConductor::creatable() === false) {
            return $this->respondForbidden();
        }

        // check for file
        $file = $request->file('file');
        if ($file === null) {
            return $this->respondWithErrors(['file' => 'The browser did not upload the file correctly to the server.']);
        }

        return $this->storeOrUpdate($request, null);
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
        // allowed to update a media item
        if (MediaConductor::updatable($medium) === false) {
            return $this->respondForbidden();
        }

        return $this->storeOrUpdate($request, $medium);
    }

    /**
     * Store a new media resource
     *
     * @param  \App\Http\Requests\MediaRequest $request The uploaded media.
     * @param  \App\Models\Media|null          $medium  The specified media.
     * @return \Illuminate\Http\Response
     */
    public function storeOrUpdate(MediaRequest $request, Media|null $medium)
    {
        $file = $request->file('file');
        if ($file !== null) {
            // validate file object
            if ($file->isValid() !== true) {
                switch ($file->getError()) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        return $this->respondTooLarge();
                    case UPLOAD_ERR_PARTIAL:
                        return $this->respondWithErrors([$file => 'The file upload was interrupted.']);
                    default:
                        return $this->respondWithErrors([$file => 'An error occurred uploading the file to the server.']);
                }
            }

            if ($file->getSize() > Media::getMaxUploadSize()) {
                return $this->respondTooLarge();
            }
        }

        // create/get media job
        $mediaJob = null;
        $data = [];

        if ($request->missing('job_id') === true) {
            /** @var \App\Models\User */
            $user = auth()->user();

            $mediaJob = new MediaJob();
            $mediaJob->user_id = $user->id;
            if ($medium !== null) {
                $mediaJob->media_id = $medium->id;
            }

            if ($request->has('title') === true || $file !== null) {
                $data['title'] = $request->get('title', '');
            }

            if ($request->has('name') === true || $file !== null) {
                $data['name'] = $request->has('chunk') === true ? $request->get('name', '') : $file->getClientOriginalName();
            }

            if ($file !== null) {
                $data['size'] = $request->has('chunk') === true ? 0 : $file->getSize();
                $data['mime_type'] = $request->has('chunk') === true ? '' : $file->getMimeType();
            }

            if ($request->has('storage') === true || $file !== null) {
                $data['storage'] = $request->get('storage', '');
            }

            if ($request->has('transform') === true) {
                $transform = [];

                foreach (explode(',', $request->get('transform', '')) as $value) {
                    if (is_string($value) === true) {
                        if (preg_match('/^rotate-(-?\d+)$/', $value, $matches) !== false) {
                            $transform['rotate'] = $matches[1];
                        } elseif (preg_match('/^flip-([vh]|vh|hv)$/', $value, $matches) !== false) {
                            $transform['flip'] = $matches[1];
                        } elseif (preg_match('/^crop-(\d+)-(\d+)$/', $value, $matches) !== false) {
                            $transform['crop'] = ['width' => $matches[1], 'height' => $matches[2]];
                        } elseif (preg_match('/^crop-(\d+)-(\d+)-(\d+)-(\d+)$/', $value, $matches) !== false) {
                            $transform['crop'] = ['width' => $matches[1], 'height' => $matches[2], 'x' => $matches[3], 'y' => $matches[4]];
                        }
                    }
                }

                if (count($transform) > 0) {
                    $data['transform'] = $transform;
                }
            }//end if

            $mediaJob->setStatusWaiting();
        } else {
            $mediaJob = MediaJob::find($request->get('job_id'));
            if ($mediaJob === null || $mediaJob->exists() === false) {
                $this->respondNotFound();
            }

            $data = json_decode($mediaJob->data, true);
            if ($data === null) {
                Log::error(`{$mediaJob->id} contains no data`);
                return $this->respondServerError();
            }

            if (array_key_exists('name', $data) === false) {
                Log::error(`{$mediaJob->id} data does not contain the name key`);
                return $this->respondServerError();
            }
        }//end if

        if ($mediaJob === null) {
            Log::error(`media job does not exist`);
            return $this->respondServerError();
        }

        // save uploaded file
        if ($file !== null) {
            if ($data['name'] === '') {
                Log::error(`filename does not exist`);
                return $this->respondServerError();
            }

            $temporaryFilePath = generateTempFilePath(pathinfo($data['name'], PATHINFO_EXTENSION), $request->get('chunk', ''));
            copy($file->path(), $temporaryFilePath);

            if ($request->has('chunk') === true) {
                $data['chunks'][$request->get('chunk', '1')] = $temporaryFilePath;
                $data['chunk_count'] = $request->get('chunk_count', 1);
            } else {
                $data['file'] = $temporaryFilePath;
            }
        }

        $mediaJob->data = json_encode($data, true);
        $mediaJob->save();
        $mediaJob->process();

        return $this->respondAsResource(
            MediaJobConductor::model($request, $mediaJob),
            ['resourceName' => 'media_job', 'respondCode' => HttpResponseCodes::HTTP_ACCEPTED]
        );
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
    private function validateFileObject(UploadedFile $file, string $errorKey = 'file'): JsonResponse|null
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
