<?php

namespace App\Models;

use App\Helpers;
use App\Traits\UUID;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForumPostAttachment extends Model
{
    use HasFactory;
    use UUID;

    protected $fillable = [
        'forum_post_id',
        'uploaded_by_user_id',
        'original_filename',
        'storage_path',
        'mime_type',
        'size_bytes',
        'sort_order',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * @return BelongsTo<ForumPost, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(ForumPost::class, 'forum_post_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function displayName(): string
    {
        $name = trim((string) $this->original_filename);

        return $name !== '' ? $name : 'Attachment';
    }

    public function downloadFileName(): string
    {
        $name = trim((string) $this->original_filename);
        $name = preg_replace('/[\x00-\x1F\x7F]+/u', '', $name) ?: '';
        $name = trim($name);

        return $name !== '' ? $name : 'attachment';
    }

    public function sizeHuman(): string
    {
        return Helpers::bytesToString((int) $this->size_bytes);
    }

    public function iconClass(): string
    {
        $mimeType = strtolower(trim((string) $this->mime_type));

        if (str_starts_with($mimeType, 'image/')) {
            return 'fa-regular fa-image';
        }

        if (str_contains($mimeType, 'pdf')) {
            return 'fa-regular fa-file-pdf';
        }

        if (str_contains($mimeType, 'zip') || str_contains($mimeType, 'compressed')) {
            return 'fa-regular fa-file-zipper';
        }

        if (str_starts_with($mimeType, 'text/')) {
            return 'fa-regular fa-file-lines';
        }

        return 'fa-regular fa-file';
    }
}
