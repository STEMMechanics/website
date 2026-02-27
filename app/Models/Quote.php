<?php

namespace App\Models;

use App\Traits\HasFiles;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quote extends Model
{
    use HasFactory, HasFiles;

    protected $fillable = [
        'quote_number',
        'user_id',
        'quote_date',
        'purchase_order_number',
        'title',
        'description',
        'line_items',
        'subtotal_amount',
        'gst_amount',
        'total_amount',
        'notes',
    ];

    protected $casts = [
        'quote_date' => 'date',
        'line_items' => 'array',
        'subtotal_amount' => 'decimal:2',
        'gst_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return MorphToMany<FinanceFile, $this>
     */
    public function financeFiles(): MorphToMany
    {
        return $this->morphToMany(FinanceFile::class, 'fileable', 'finance_fileables')
            ->withPivot('collection')
            ->withTimestamps();
    }

    /**
     * @return MorphToMany<FinanceFile, $this>
     */
    public function privateFinanceFiles(): MorphToMany
    {
        return $this->financeFiles()->wherePivot('collection', 'private');
    }

    public function syncPrivateFinanceFiles(array $fileIds): void
    {
        $normalizedIds = collect($fileIds)
            ->map(fn ($id) => is_numeric($id) ? (int) $id : 0)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        $normalizedIds = FinanceFile::query()
            ->whereIn('id', $normalizedIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $now = now();
        $currentIds = DB::table('finance_fileables')
            ->where('fileable_type', self::class)
            ->where('fileable_id', (string) $this->getKey())
            ->where('collection', 'private')
            ->pluck('finance_file_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $detachIds = array_values(array_diff($currentIds, $normalizedIds));
        $attachIds = array_values(array_diff($normalizedIds, $currentIds));

        if ($detachIds !== []) {
            DB::table('finance_fileables')
                ->where('fileable_type', self::class)
                ->where('fileable_id', (string) $this->getKey())
                ->where('collection', 'private')
                ->whereIn('finance_file_id', $detachIds)
                ->delete();
        }

        if ($attachIds !== []) {
            $rows = array_map(fn (int $fileId) => [
                'finance_file_id' => $fileId,
                'fileable_id' => (string) $this->getKey(),
                'fileable_type' => self::class,
                'collection' => 'private',
                'created_at' => $now,
                'updated_at' => $now,
            ], $attachIds);

            DB::table('finance_fileables')->insert($rows);
        }
    }

    public function getRouteKeyName(): string
    {
        return 'quote_number';
    }
}
