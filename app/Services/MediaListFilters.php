<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MediaListFilters
{
    public const SORTS = ['title' => 'Name', 'name' => 'Filename', 'mime_type' => 'Type', 'size' => 'Size', 'visibility' => 'Visibility', 'created_at' => 'Uploaded'];
    public const TYPES = ['image' => 'Images', 'video' => 'Videos', 'audio' => 'Audio', 'pdf' => 'PDFs', 'other' => 'Other files'];

    public function validate(Request $request): void
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'workshop' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'string', 'max:255'],
            'mime_type' => ['nullable', 'string', 'max:255'],
            'name_pattern' => ['nullable', 'string', 'max:255'],
            'tags_include' => ['nullable', 'string', 'max:255'],
            'tags_exclude' => ['nullable', 'string', 'max:255'],
            'usage' => ['nullable', Rule::in(['used', 'unused'])],
            'preset' => ['nullable', Rule::in(['all', 'images', 'unused'])],
            'type' => ['nullable', Rule::in(array_keys(self::TYPES))],
            'sort' => ['nullable', Rule::in(array_keys(self::SORTS))],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', Rule::in([25, 50, 100])],
            'visibility' => ['nullable', Rule::in(['public', 'protected', 'private'])],
            'storage_disk' => ['nullable', Rule::in(['media', 'archive'])],
            'size_min' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'size_max' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'uploaded_from' => ['nullable', 'date_format:Y-m-d'],
            'uploaded_to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        // Old bookmarks become ordinary, removable filters.
        if ($request->query('preset') === 'images' && !$request->filled('type')) $request->query->set('type', 'image');
        if ($request->query('preset') === 'unused' || $request->boolean('unused_only')) $request->query->set('usage', 'unused');
        $request->query->remove('preset');
        $request->query->remove('unused_only');
    }

    private function pattern(string $value): string
    {
        return strtr(mb_strtolower($value), ['!' => '!!', '%' => '!%', '_' => '!_', '*' => '%', '?' => '_']);
    }


    public function apply(Builder $query, Request $request): void
    {
        if ($request->filled('name_pattern')) {
            $pattern = $this->pattern(trim($request->query('name_pattern')));
            $query->where(fn ($q) => $q->whereRaw("LOWER(title) LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern]));
        }
        if ($request->filled('mime_type')) {
            $patterns = array_filter(array_map('trim', explode(',', $request->query('mime_type'))));
            if ($patterns) $query->where(function ($q) use ($patterns) {
                foreach ($patterns as $pattern) $q->orWhereRaw("LOWER(mime_type) LIKE ? ESCAPE '!'", [$this->pattern($pattern)]);
            });
        }
        // Normalize whitespace around comma-separated whole tags, retaining spaces within tag names.
        $tags = "LOWER(COALESCE(tags, ''))";
        foreach ([9, 10, 13] as $char) $tags = "REPLACE($tags, CHAR($char), ' ')";
        for ($i = 0; $i < 8; $i++) $tags = "REPLACE($tags, '  ', ' ')";
        $tags = "TRIM(REPLACE(REPLACE($tags, ', ', ','), ' ,', ','))";
        $tags = $query->getModel()->getConnection()->getDriverName() === 'sqlite' ? "(',' || $tags || ',')" : "CONCAT(',', $tags, ',')";
        foreach (['tags_include' => 'LIKE', 'tags_exclude' => 'NOT LIKE'] as $field => $operator) {
            foreach (array_unique(array_filter(array_map('trim', explode(',', (string) $request->query($field, ''))))) as $tag) {
                $tag = mb_strtolower(preg_replace('/\s+/u', ' ', $tag));
                $escaped = strtr($tag, ['!' => '!!', '%' => '!%', '_' => '!_']);
                $query->whereRaw("$tags $operator ? ESCAPE '!'", ['%,'.$escaped.',%']);
            }
        }
        $type = $request->query('type');
        if (in_array($type, ['image', 'video', 'audio'], true)) $query->where('mime_type', 'like', $type.'/%');
        if ($type === 'pdf') $query->where('mime_type', 'application/pdf');
        if ($type === 'other') {
            foreach (['image/%', 'video/%', 'audio/%'] as $mime) $query->where('mime_type', 'not like', $mime);
            $query->where('mime_type', '!=', 'application/pdf');
        }
        if ($request->filled('storage_disk')) $query->where('storage_disk', $request->query('storage_disk'));
        if ($request->filled('size_min')) $query->where('size', '>=', (float) $request->query('size_min') * 1048576);
        if ($request->filled('size_max')) $query->where('size', '<=', (float) $request->query('size_max') * 1048576);
        if ($request->filled('uploaded_from')) $query->where('created_at', '>=', $request->query('uploaded_from').' 00:00:00');
        if ($request->filled('uploaded_to')) $query->where('created_at', '<', \Carbon\Carbon::parse($request->query('uploaded_to'))->addDay()->startOfDay());
        $query->orderBy($request->query('sort') ?: 'created_at', $request->query('direction') ?: 'desc')->orderBy('name');
    }
}
