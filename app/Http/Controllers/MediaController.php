<?php

namespace App\Http\Controllers;

use App\Exceptions\FileInvalidException;
use App\Exceptions\FileTooLargeException;
use App\Helpers;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MediaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if(!$request->wantsJson()) {
            abort(404);
        }

        $media = $this->getMedia($request);
        return response()->json($media);
    }

    public function admin_index(Request $request)
    {
        $media = $this->getMedia($request);

        return view('admin.media.index', [
            'media' => $media,
        ]);

    }

    public function getMedia(Request $request)
    {
        $query = Media::query();
        $perPage = $request->input('per_page', 25);

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

        return response()->json($media);
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
                    return response()->json([
                        'message' => 'The file ' . $file->getClientOriginalName() . ' has been uploaded',
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
            $fileName = $request->input('filename', 'upload');
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

        if(!$request->has('title')) {
            return response()->json([
                'message' => 'A title is required',
                'errors' => [
                    'title' => 'A title is required'
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
        return view('admin.media.edit', ['medium' => $media]);
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

                if(is_string($uploadToken) && isset($chunkUploads[$uploadToken])) {
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
}
