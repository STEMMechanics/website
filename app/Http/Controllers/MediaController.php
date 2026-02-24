<?php

namespace App\Http\Controllers;

use App\Exceptions\FileInvalidException;
use App\Exceptions\FileTooLargeException;
use App\Helpers;
use App\Jobs\Media\GenerateVariants;
use App\Models\Media;
use Illuminate\Bus\Batch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MediaController extends Controller
{
    private const REGENERATE_MISSING_BATCH_CACHE_KEY = 'media:regenerate-missing:active-batch-id';

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if(!$request->wantsJson()) {
            abort(404);
        }

        $media = $this->getMedia($request);
        $media->setCollection(
            $media->getCollection()->map(fn (Media $item) => $this->publicMediaPayload($item))
        );

        return response()->json($media);
    }

    public function admin_index(Request $request)
    {
        $media = $this->getMedia($request);

        return view('admin.media.index', [
            'media' => $media,
            'missingVariantRegeneration' => $this->missingVariantRegenerationPayload(),
        ]);

    }

    public function getMedia(Request $request)
    {
        $query = Media::query();
        $perPage = $request->input('per_page', 25);
        $isAdmin = (bool) (Auth::user()?->isAdmin() ?? false);

        if (! $isAdmin) {
            $query->whereNotExists(function ($builder) {
                $builder->selectRaw('1')
                    ->from('mediables')
                    ->whereColumn('mediables.media_name', 'media.name')
                    ->where('mediables.collection', 'private');
            });
        }

        if(!empty($request->get('search'))) {
            $query->where(function($query) use ($request) {
                $query->where('title', 'like', '%' . $request->search . '%');
                $query->orWhere('name', 'like', '%' . $request->search . '%');
            });
        }

        if($request->has('mime_type')) {
            $mime_types = explode(',', $request->mime_type);
            $query->where(function ($query) use ($mime_types) {
                foreach ($mime_types as $mime_type) {
                    $mime_type = str_replace('*', '%', $mime_type);
                    $query->orWhere('mime_type', 'like', $mime_type);
                }
            });
        }

        $media = $query->orderBy('created_at', 'desc');

        if($request->wantsJson() && !(empty($request->input('selected'))) && empty($request->get('search')) && !$request->has('page')) {
            $selected = $request->input('selected')[0];
            $selectedMedia = $media->get();
            $selectedMediaIndex = $selectedMedia->search(function ($item) use ($selected) {
                return $item->name == $selected;
            });

            if ($selectedMediaIndex !== false) {
                $page = intdiv($selectedMediaIndex, $perPage) + 1;
                $request->merge(['page' => $page]);
            }
        }

        $media = $media->paginate($perPage)->onEachSide(1);

        // Transform the 'password' field of each item in the collection
        $media->getCollection()->transform(function ($item) {
            $item->password = $item->password ? 'yes' : null;
            return $item;
        });

        return $media;
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Media $media)
    {
        if(!$request->wantsJson()) {
            abort(404);
        }

        $this->authorizeMediaAccess($media);

        return response()->json($this->publicMediaPayload($media));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function admin_create()
    {
        return view('admin.media.edit');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function admin_store(Request $request)
    {
        $file = null;
        $cleanupPath = null;

        // Check if the endpoint received a file...
        if($request->hasFile('file')) {
            try {
                $file = $this->upload($request);

                if(is_array($file) && !empty($file['chunk'])) {
                    return response()->json([
                        'message' => 'Chunk stored',
                        'upload_token' => $file['token'] ?? null,
                    ]);
                } else if(!$file) {
                    return response()->json([
                        'message' => 'An error occurred processing the file.',
                        'errors' => [
                            'file' => 'An error occurred processing the file.'
                        ]
                    ], 422);
                }

                if(!$request->has('title')) {
                    $chunkUploads = session()->get('chunk_uploads', []);
                    $deferredToken = bin2hex(random_bytes(16));
                    $sourcePath = $file->getRealPath();
                    $deferredPath = tempnam(sys_get_temp_dir(), 'media-deferred-');
                    if($sourcePath === false || !is_string($deferredPath) || !@copy($sourcePath, $deferredPath)) {
                        return response()->json([
                            'message' => 'Could not persist uploaded file for form submission.',
                            'errors' => [
                                'file' => 'Could not persist uploaded file for form submission.'
                            ]
                        ], 500);
                    }

                    $chunkUploads[$deferredToken] = $deferredPath;
                    session()->put('chunk_uploads', $chunkUploads);

                    return response()->json([
                        'message' => 'The file ' . $file->getClientOriginalName() . ' has been uploaded',
                        'upload_token' => $deferredToken,
                        'filename' => $file->getClientOriginalName(),
                        'file' => [
                            'name' => $file->getClientOriginalName(),
                            'size' => $file->getSize(),
                            'mime_type' => $file->getMimeType()
                        ]
                    ]);
                }
            } catch(\Exception $e) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => [
                        'file' => $e->getMessage()
                    ]
                ], 422);
            }

        // else check if it received a file name of a previous upload...
        } else if($request->has('upload_token') || $request->has('file')) {
            $uploadToken = $request->input('upload_token', $request->input('file'));
            $chunkUploads = session()->get('chunk_uploads', []);

            if(!is_string($uploadToken) || !isset($chunkUploads[$uploadToken])) {
                return response()->json([
                    'message' => 'Could not find the referenced file on the server.',
                    'errors' => [
                        'file' => 'Could not find the referenced file on the server.'
                    ]
                ], 422);
            }

            $tempFileName = $chunkUploads[$uploadToken];
            if(!file_exists($tempFileName)) {
                return response()->json([
                    'message' => 'Could not find the referenced file on the server.',
                    'errors' => [
                        'file' => 'Could not find the referenced file on the server ('.$tempFileName.').'
                    ]
                ], 422);
            }

            $fileMime = mime_content_type($tempFileName);
            if($fileMime === false) {
                $fileMime = 'application/octet-stream';
            }
            $fileName = $request->input('filename', $request->input('file_original_filename', 'upload'));
            $fileName = Helpers::cleanFileName($fileName);
            if ($fileName === '') {
                $fileName = 'upload';
            }

            $file = new UploadedFile($tempFileName, $fileName, $fileMime, null, true);
            $cleanupPath = $tempFileName;
            unset($chunkUploads[$uploadToken]);
            session()->put('chunk_uploads', $chunkUploads);
        }

        // Check there is an actual file
        if(!$file) {
            return response()->json([
                'message' => 'A file is required.',
                'errors' => [
                    'file' => 'A file is required.'
                ]
            ], 422);
        }

        $fileName = $file->getClientOriginalName();
        $name = pathinfo($fileName, PATHINFO_FILENAME);
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $name = Helpers::cleanFileName($name);

        if(Media::find($name . '.' . $extension) !== null) {
            $increment = 1;
            $name = preg_replace('/-\d+$/', '', $name);

            while(Media::find($name . '-' . $increment . '.' . $extension) !== null) {
                $increment++;
            }

            $fileName = $name . '-' . $increment . '.' . $extension;
        }

        $hash = hash_file('sha256', $file->path());

        $storage = Storage::disk('media');
        $exists = $storage->exists($hash);
        if(!$exists) {
            if($file->storeAs('/', $hash, 'media') === false) {
                if($request->wantsJson()) {
                    return response()->json([
                        'message' => 'A server error occurred uploading the file.',
                    ], 500);
                } else {
                    session()->flash('message', 'A server error occurred uploading the file.');
                    session()->flash('message-title', 'Upload failed');
                    session()->flash('message-type', 'danger');
                    return redirect()->back();
                }
            }
        }

        $media = Media::Create([
            'title' => $request->get('title', Helpers::filenameToTitle($fileName)),
            'user_id' => auth()->id(),
            'name' => $fileName,
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'hash' => $hash
        ]);

        if(!$exists) {
            $media->generateVariants(false);
        } else {
            // find media with the same hash that also has variants and copy them
            $mediaWithVariants = Media::where('hash', $hash)->where('variants', '!=', '')->orderBy('created_at')->first();
            if($mediaWithVariants) {
                $media->variants = $mediaWithVariants->variants;
                $media->save();
            }
        }

        if(is_string($cleanupPath)) {
            $realPath = realpath($cleanupPath);
            $tempDir = realpath(sys_get_temp_dir());
            if($realPath !== false && $tempDir !== false && str_starts_with($realPath, $tempDir . DIRECTORY_SEPARATOR)) {
                @unlink($realPath);
            }
        }

        if($request->wantsJson()) {
            return response()->json([
                'message' => 'File has been uploaded',
                'name' => $media->name,
                'size' => $media->size,
                'mime_type' => $media->mime_type
            ]);
        } else {
            session()->flash('message', 'Media has been uploaded');
            session()->flash('message-title', 'Media uploaded');
            session()->flash('message-type', 'success');
            return redirect()->route('admin.media.index');
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function admin_edit(Media $media)
    {
        $mediaFilesInfo = [];
        try {
            $mediaFilesInfo = $this->mediaFilesInfo($media);
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->view('admin.media.edit', [
            'medium' => $media,
            'mediaFilesInfo' => $mediaFilesInfo,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Update the specified resource in storage.
     */
    public function admin_update(Request $request, Media $media)
    {
        $max_size = Helpers::getMaxUploadSize();

        $validator = Validator::make($request->all(), [
            'title' => 'required',
//            'file' => 'nullable|file|max:' . (max(round($max_size / 1024),0)),
        ], [
            'title.required' => __('validation.custom_messages.title_required'),
//            'file.required' => __('validation.custom_messages.file_required'),
//            'file.file' => __('validation.custom_messages.file_file'),
//            'file.max' => __('validation.custom_messages.file_max', ['max' => Helpers::bytesToString($max_size)])
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $mediaData = $request->all();

//        $file = null;
//        if($request->has('file')) {
//            $file = $request->file('file');
//
//            $name = $file->getClientOriginalName();
//            $name = Helpers::cleanFileName($name);
//            if ($name !== $media->name) {
//                if (Media::find($name) !== null) {
//                    $increment = 2;
//                    while (Media::find($name . '-' . $increment) !== null) {
//                        $increment++;
//                    }
//
//                    $name = $name . '-' . $increment;
//                }
//            }
//
//            $hash = hash_file('sha256', $file->path());
//
//            $storage = Storage::disk('media');
//            if (!$storage->exists($hash)) {
//                if ($file->storeAs('/', $hash, 'media') === false) {
//                    session()->flash('message', 'A server error occurred uploading the file.');
//                    session()->flash('message-title', 'Upload failed');
//                    session()->flash('message-type', 'danger');
//                    return redirect()->back();
//                }
//            }
//
//            $mediaData['name'] = $name;
//            $mediaData['size'] = $file->getSize();
//            $mediaData['mime_type'] = $file->getMimeType();
//            $mediaData['hash'] = $hash;
//        }

        if($request->get('password_clear') === 'on') {
            $mediaData['password'] = null;
        } else {
            $password = $request->get('password');

            if($password !== null && $password !== '') {
                $mediaData['password'] = password_hash($request->get('password'), PASSWORD_DEFAULT);
            } else {
                unset($mediaData['password']);
            }
        }

        $media->update($mediaData);

//        if($file) {
//            $media->generateVariants(false);
//            unlink($file);
//        }

        session()->flash('message', 'Media has been updated');
        session()->flash('message-title', 'Media updated');
        session()->flash('message-type', 'success');
        return redirect()->route('admin.media.index');
    }

    public function admin_regenerate_variants(Media $media): RedirectResponse
    {
        try {
            if ($this->isMediaVariantRegenerationLocked($media)) {
                session()->flash('message', 'Variant regeneration is already running for this media. Please wait and refresh.');
                session()->flash('message-title', 'Regeneration already running');
                session()->flash('message-type', 'warning');
                return redirect()->back();
            }

            $media->generateVariants(true);

            session()->flash('message', 'Variant regeneration has been queued. Try refreshing the page in a few minutes.');
            session()->flash('message-title', 'Variants regenerating');
            session()->flash('message-type', 'success');
        } catch (\Throwable $e) {
            report($e);

            session()->flash('message', 'Could not regenerate variants: '.$e->getMessage());
            session()->flash('message-title', 'Regenerate failed');
            session()->flash('message-type', 'danger');
        }

        return redirect()->back();
    }

    private function isMediaVariantRegenerationLocked(Media $media): bool
    {
        $job = new GenerateVariants($media, true);
        $middleware = (new WithoutOverlapping($media->name))->dontRelease();
        $lockKey = $middleware->getLockKey($job);

        $lock = Cache::lock($lockKey, 5);
        if ($lock->get()) {
            $lock->release();
            return false;
        }

        return true;
    }

    public function admin_regenerate_missing_variants(): RedirectResponse|JsonResponse
    {
        try {
            $runningBatch = $this->activeMissingVariantRegenerationBatch();
            if ($runningBatch !== null && !$runningBatch->finished() && !$runningBatch->cancelled()) {
                $message = 'Missing variant regeneration is already running.';

                if (request()->expectsJson()) {
                    return response()->json([
                        'ok' => true,
                        'message' => $message,
                        'regeneration' => $this->missingVariantRegenerationPayload(),
                    ]);
                }

                session()->flash('message', $message);
                session()->flash('message-title', 'Already running');
                session()->flash('message-type', 'warning');
                return redirect()->back();
            }

            $jobBuffer = [];
            $queuedCount = 0;
            $batch = null;
            Media::query()->chunkById(200, function ($mediaBatch) use (&$jobBuffer, &$queuedCount, &$batch): void {
                foreach ($mediaBatch as $media) {
                    $variantTypes = $media->getVariantTypes();
                    if ($variantTypes === []) {
                        continue;
                    }

                    $hasMissingVariant = false;
                    foreach (array_keys($variantTypes) as $variantName) {
                        if (!$media->hasVariant($variantName)) {
                            $hasMissingVariant = true;
                            break;
                        }
                    }

                    if (!$hasMissingVariant) {
                        continue;
                    }

                    if ($batch === null) {
                        $batch = Bus::batch([])
                            ->name('Regenerate Missing Media Variants')
                            ->allowFailures()
                            ->onQueue('media')
                            ->dispatch();

                        Cache::forever(self::REGENERATE_MISSING_BATCH_CACHE_KEY, $batch->id);
                    }

                    $jobBuffer[] = new GenerateVariants($media, false);
                    $queuedCount++;

                    if (count($jobBuffer) >= 200) {
                        $batch->add($jobBuffer);
                        $jobBuffer = [];
                    }
                }
            }, 'name');

            if ($batch !== null && $jobBuffer !== []) {
                $batch->add($jobBuffer);
            }

            if ($queuedCount === 0) {
                Cache::forget(self::REGENERATE_MISSING_BATCH_CACHE_KEY);
                $message = 'No missing variants were found.';

                if (request()->expectsJson()) {
                    return response()->json([
                        'ok' => true,
                        'message' => $message,
                        'regeneration' => ['running' => false],
                    ]);
                }

                session()->flash('message', $message);
                session()->flash('message-title', 'Nothing to regenerate');
                session()->flash('message-type', 'info');
                return redirect()->back();
            }

            session()->flash('message', 'Missing variant regeneration has been queued.');
            session()->flash('message-title', 'Regeneration queued');
            session()->flash('message-type', 'success');

            if (request()->expectsJson()) {
                return response()->json([
                    'ok' => true,
                    'message' => 'Missing variant regeneration has started.',
                    'queued' => $queuedCount,
                    'regeneration' => $this->missingVariantRegenerationPayload(),
                ]);
            }
        } catch (\Throwable $e) {
            report($e);

            $message = 'Could not queue missing variant regeneration: '.$e->getMessage();
            session()->flash('message', $message);
            session()->flash('message-title', 'Queue failed');
            session()->flash('message-type', 'danger');

            if (request()->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => $message,
                ], 500);
            }
        }

        return redirect()->back();
    }

    public function admin_regenerate_missing_variants_status(): JsonResponse
    {
        return response()->json($this->missingVariantRegenerationPayload());
    }

    private function missingVariantRegenerationPayload(): array
    {
        $batch = $this->activeMissingVariantRegenerationBatch();
        if ($batch === null) {
            return [
                'running' => false,
            ];
        }

        return [
            'running' => !$batch->finished() && !$batch->cancelled(),
            'id' => $batch->id,
            'name' => $batch->name,
            'total_jobs' => $batch->totalJobs,
            'pending_jobs' => $batch->pendingJobs,
            'processed_jobs' => $batch->processedJobs(),
            'failed_jobs' => $batch->failedJobs,
            'progress' => $batch->progress(),
            'cancelled' => $batch->cancelled(),
            'finished' => $batch->finished(),
            'created_at' => $batch->createdAt->toDateTimeString(),
            'finished_at' => $batch->finishedAt?->toDateTimeString(),
        ];
    }

    private function activeMissingVariantRegenerationBatch(): ?Batch
    {
        $batchId = Cache::get(self::REGENERATE_MISSING_BATCH_CACHE_KEY);
        if (!is_string($batchId) || trim($batchId) === '') {
            return null;
        }

        $batch = Bus::findBatch($batchId);
        if ($batch === null) {
            Cache::forget(self::REGENERATE_MISSING_BATCH_CACHE_KEY);
            return null;
        }

        // Recover from previously-created empty batches that never transition state.
        if ($batch->totalJobs === 0 && $batch->pendingJobs === 0 && !$batch->finished() && !$batch->cancelled()) {
            Cache::forget(self::REGENERATE_MISSING_BATCH_CACHE_KEY);
            return null;
        }

        if ($batch->finished() || $batch->cancelled()) {
            Cache::forget(self::REGENERATE_MISSING_BATCH_CACHE_KEY);
        }

        return $batch;
    }

    private function mediaFilesInfo(Media $media): array
    {
        $files = [];

        $originalPath = $media->path();
        $files[] = $this->buildMediaFileInfo(
            label: 'Original',
            variant: '',
            storageKey: (string) ($media->hash ?? ''),
            filePath: is_string($originalPath) ? $originalPath : null,
            mimeType: (string) ($media->mime_type ?? ''),
            fallbackSize: isset($media->size) ? (int) $media->size : null,
            url: (string) ($media->url ?? '')
        );

        $variants = is_array($media->variants ?? null) ? $media->variants : [];
        foreach ($variants as $variantName => $variant) {
            $variantName = strtolower(trim((string) $variantName));
            if ($variantName === '') {
                continue;
            }

            $files[] = $this->buildMediaFileInfo(
                label: $variantName,
                variant: $variantName,
                storageKey: trim((string) ($media->hash ?? '')).'-'.$variantName,
                filePath: $this->variantFilePath($media, $variantName),
                mimeType: (string) ($variant['mime_type'] ?? ''),
                fallbackSize: null,
                url: (string) $media->url($variantName, true)
            );
        }

        return $files;
    }

    private function buildMediaFileInfo(
        string $label,
        string $variant,
        string $storageKey,
        ?string $filePath,
        string $mimeType,
        ?int $fallbackSize,
        string $url
    ): array {
        $exists = is_string($filePath) && $filePath !== '' && is_file($filePath);
        $sizeBytes = $exists ? (int) filesize($filePath) : ($fallbackSize !== null ? max(0, $fallbackSize) : null);
        $dimensions = $this->imageDimensions($filePath, $mimeType);

        return [
            'label' => $label,
            'variant' => $variant,
            'exists' => $exists,
            'format' => $this->formatLabel($mimeType, $filePath),
            'mime_type' => $mimeType !== '' ? $mimeType : '-',
            'dimensions' => $dimensions,
            'size_bytes' => $sizeBytes,
            'size_human' => $sizeBytes !== null ? Helpers::bytesToString($sizeBytes) : '-',
            'storage_key' => $storageKey !== '' ? $storageKey : '-',
            'path' => $filePath ?: '-',
            'url' => $url !== '' ? $url : '-',
        ];
    }

    private function variantFilePath(Media $media, string $variant): ?string
    {
        $path = $media->path();
        if (! is_string($path) || $path === '') {
            return null;
        }

        $variantPath = $path.'-'.$variant;

        return is_file($variantPath) ? $variantPath : null;
    }

    private function imageDimensions(?string $filePath, string $mimeType): string
    {
        if (! is_string($filePath) || $filePath === '' || ! is_file($filePath)) {
            return '-';
        }

        if (! str_starts_with(strtolower($mimeType), 'image/')) {
            return '-';
        }

        $size = @getimagesize($filePath);
        if (! is_array($size)) {
            return '-';
        }

        return ((int) $size[0]).' x '.((int) $size[1]);
    }

    private function formatLabel(string $mimeType, ?string $filePath): string
    {
        $mimeType = trim($mimeType);
        if ($mimeType !== '' && str_contains($mimeType, '/')) {
            $parts = explode('/', $mimeType, 2);
            $subtype = strtoupper(trim((string) ($parts[1] ?? '')));
            if ($subtype !== '') {
                return $subtype;
            }
        }

        if (is_string($filePath) && $filePath !== '') {
            $extension = strtoupper(trim((string) pathinfo($filePath, PATHINFO_EXTENSION)));
            if ($extension !== '') {
                return $extension;
            }
        }

        return '-';
    }

    /**
     * Remove the specified resource from storage.
     */
    public function admin_destroy(Request $request, Media $media)
    {
        $media->delete();
        session()->flash('message', 'Media has been deleted');
        session()->flash('message-title', 'Media deleted');
        session()->flash('message-type', 'danger');

        if($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'redirect' => route('admin.media.index'),
            ]);
        }

        return redirect()->route('admin.media.index');
    }


    /**
     * @throws FileInvalidException
     * @throws FileTooLargeException
     */
    private function upload(Request $request)
    {
        $max_size = Helpers::getMaxUploadSize();

        $file = $request->file('file');
        if(!$file->isValid()) {
            throw new FileInvalidException('The file is invalid');
        }

        $fileName = $request->input('filename', $file->getClientOriginalName());
        $fileName = Helpers::cleanFileName($fileName);
        if ($fileName === '') {
            $extension = strtolower($file->getClientOriginalExtension());
            $fileName = 'upload' . ($extension !== '' ? '.' . $extension : '');
        }

        if(($request->has('filestart') || $request->has('fileappend')) && $request->has('filesize')) {
            $fileSize = $request->get('filesize');

            if($fileSize > $max_size) {
                throw new FileTooLargeException('The file is larger than the maximum size allowed of ' . Helpers::bytesToString($max_size));
            }

            $chunkUploads = session()->get('chunk_uploads', []);
            $uploadToken = $request->input('upload_token');

            if($request->has('filestart')) {
                $uploadToken = bin2hex(random_bytes(16));
                $tempFilePath = tempnam(sys_get_temp_dir(), 'chunk-' . Auth::id() . '-');
                if($tempFilePath === false) {
                    throw new FileInvalidException('Unable to create a temporary upload file.');
                }

                $chunkUploads[$uploadToken] = $tempFilePath;
                session()->put('chunk_uploads', $chunkUploads);
            } else {
                if(!is_string($uploadToken) || !isset($chunkUploads[$uploadToken])) {
                    throw new FileInvalidException('Invalid upload token.');
                }

                $tempFilePath = $chunkUploads[$uploadToken];
            }

            $filemode = 'a';
            if($request->has('filestart')) {
                $filemode = 'w';
            }

            // Append the chunk to the temporary file
            $fp = fopen($tempFilePath, $filemode);
            if ($fp) {
                fwrite($fp, file_get_contents($file->getRealPath()));
                fclose($fp);
            }

            // Check if the upload is complete
            if (filesize($tempFilePath) >= $fileSize) {
                $fileMime = mime_content_type($tempFilePath);
                if($fileMime === false) {
                    $fileMime = 'application/octet-stream';
                }

                if(isset($chunkUploads[$uploadToken])) {
                    unset($chunkUploads[$uploadToken]);
                    session()->put('chunk_uploads', $chunkUploads);
                }

                return new UploadedFile($tempFilePath, $fileName, $fileMime, null, true);
            } else {
                return [
                    'chunk' => true,
                    'token' => $uploadToken,
                ];
            }
        }

        return $file;
    }


    public function download(Request $request, Media $media)
    {
        $this->authorizeMediaAccess($media);

        $file = $media->path();
        if($file === null) {
            abort(404, 'File not found');
        }

        if($media->password !== null && !Auth::user()?->isAdmin()) {
            if(!$request->has('password')) {
                return view('media-password');
            } else {
                $password = $request->get('password');

                if($password === '' || $password === null) {
                    return view('media-password', [
                        'error' => 'Password is required',
                    ]);
                }

                if(!password_verify(base64_decode($password), $media->password)) {
                    return view('media-password', [
                        'error' => 'Password is incorrect',
                    ]);
                }
            }
        }

        $variant = '';
        $download = false;
        $variants = array_keys($media->getVariantTypes());
        $query = $request->getQueryString();
        if($query !== '') {
            $queryList = explode('&', $query);
            foreach($queryList as $queryItem) {
                $parts = explode('=', $queryItem);
                if($variant === '' && in_array($parts[0], $variants) && ($parts[1] === '' || filter_var($parts[1], FILTER_VALIDATE_BOOLEAN))) {
                    $variant = $parts[0];
                }

                if($parts[0] === 'download' && ($parts[1] === '' || filter_var($parts[1], FILTER_VALIDATE_BOOLEAN))) {
                    $download = true;
                }
            }
        }

        $mime_type = $media->mime_type;
        $name = $media->name;

        if($variant !== '') {
            $variantFile = $media->getClosestVariant($variant);
            if (($variantFile['variant'] ?? null) === null || !is_string($variantFile['file'] ?? null) || !is_file((string) $variantFile['file'])) {
                abort(404, 'Requested variant file not found. Please regenerate variants.');
            }
            $file = $variantFile['file'];
            $mime_type = $variantFile['mime_type'];
            $name = $variantFile['name'];
        }

        $headers = [
            'Content-Type' => $mime_type,
            'Content-Disposition' => ($download ? 'attachment; ' : '') . 'filename="' . $name . '"',
        ];

        return response()->file($file, $headers);
    }

    private function publicMediaPayload(Media $media): array
    {
        return [
            'name' => (string) $media->name,
            'title' => (string) $media->title,
            'mime_type' => (string) $media->mime_type,
            'size' => (int) $media->size,
            'status' => (string) ($media->status ?? ''),
            'url' => (string) $media->url,
            'thumbnail' => (string) $media->thumbnail,
            'file_type' => (string) $media->file_type,
            'password' => Auth::user()?->isAdmin() ? ($media->password ? 'yes' : null) : null,
        ];
    }

    private function authorizeMediaAccess(Media $media): void
    {
        $isAdmin = (bool) (Auth::user()?->isAdmin() ?? false);
        if (! $isAdmin && $this->isPrivateAdminMedia($media)) {
            abort(403, 'You are not authorized to access this file.');
        }
    }

    private function isPrivateAdminMedia(Media $media): bool
    {
        return DB::table('mediables')
            ->where('media_name', (string) $media->name)
            ->where('collection', 'private')
            ->exists();
    }
}
