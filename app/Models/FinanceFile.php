<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Storage;

class FinanceFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'path',
        'original_name',
        'mime_type',
        'size',
        'user_id',
    ];

    protected static function booted(): void
    {
        static::deleting(function (FinanceFile $file): void {
            $path = trim((string) $file->path);
            if ($path !== '' && Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
        });
    }

    /**
     * @return MorphToMany<Invoice, $this>
     */
    public function invoices(): MorphToMany
    {
        return $this->morphedByMany(Invoice::class, 'fileable', 'finance_fileables')
            ->withPivot('collection')
            ->withTimestamps();
    }

    /**
     * @return MorphToMany<Quote, $this>
     */
    public function quotes(): MorphToMany
    {
        return $this->morphedByMany(Quote::class, 'fileable', 'finance_fileables')
            ->withPivot('collection')
            ->withTimestamps();
    }
}

