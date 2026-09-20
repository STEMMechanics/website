<?php

namespace App\Http\Controllers;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use League\Flysystem\FilesystemException;

class WellKnownController extends Controller
{
    // Only static verification formats; no paths, dotfiles or executable suffixes.
    private const NAME_PATTERN = '/\A[a-zA-Z0-9][a-zA-Z0-9_-]*(?:\.(?:txt|json|xml))?\z/';

    private function disk(): FilesystemAdapter
    {
        return Storage::disk('well_known');
    }

    private function existing(string $filename): void
    {
        abort_unless(preg_match(self::NAME_PATTERN, $filename) && strlen($filename) <= 200, 404);
        abort_if(is_link($this->disk()->path($filename)), 404);
        abort_unless($this->disk()->exists($filename), 404);
    }

    public function index()
    {
        $files = collect($this->disk()->files())->filter(fn ($name) => preg_match(self::NAME_PATTERN, $name) && ! is_link($this->disk()->path($name)))
            ->sort()->map(fn ($name) => ['name' => $name, 'size' => $this->disk()->size($name)])->values();

        return view('admin.well-known.index', compact('files'));
    }

    public function create()
    {
        return view('admin.well-known.edit', ['filename' => null, 'content' => '', 'canEditText' => true]);
    }

    public function edit(string $filename)
    {
        $this->existing($filename);
        $canEditText = $this->disk()->size($filename) <= 1024 * 1024;
        $content = $canEditText ? $this->disk()->get($filename) : '';
        $canEditText = $canEditText && mb_check_encoding($content, 'UTF-8') && ! str_contains($content, "\0");

        return view('admin.well-known.edit', ['filename' => $filename, 'content' => $canEditText ? $content : '', 'canEditText' => $canEditText]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['filename' => ['required', 'string', 'max:200', 'regex:'.self::NAME_PATTERN]]);
        $filename = $data['filename'];
        if ($this->disk()->exists($filename) || is_link($this->disk()->path($filename))) {
            throw ValidationException::withMessages(['filename' => 'This file already exists. Open it from the list to edit or replace it.']);
        }

        return $this->save($request, $filename);
    }

    public function update(Request $request, string $filename)
    {
        $this->existing($filename);

        return $this->save($request, $filename);
    }

    private function save(Request $request, string $filename)
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(['text', 'upload'])],
            'verification_text' => ['exclude_unless:mode,text', 'required', 'string', 'max:1048576'],
            'document' => ['exclude_unless:mode,upload', 'required', 'file', 'max:1024'],
        ]);
        $content = $data['mode'] === 'upload' ? $request->file('document')->getContent() : $data['verification_text'];
        try {
            $this->disk()->put($filename, $content, ['visibility' => 'public']);
        } catch (FilesystemException $exception) {
            report($exception);
            throw ValidationException::withMessages(['filename' => 'The server could not save this file. Check that the website can write to public/.well-known and the existing file.']);
        }

        return redirect()->route('admin.well-known.edit', $filename)->with('message', 'Verification file saved.')->with('message-type', 'success');
    }

    public function destroy(string $filename)
    {
        $this->existing($filename);
        $this->disk()->delete($filename);

        return redirect()->route('admin.well-known.index')->with('message', 'Verification file deleted.')->with('message-type', 'success');
    }
}
