<?php

namespace App\Models;

use App\Traits\UUID;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    use HasFactory, UUID;

    protected $fillable = [
        'name', 'address', 'suburb', 'state', 'postcode', 'latitude', 'longitude', 'address_url', 'url',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function isOnline(): bool
    {
        return strcasecmp(trim((string) $this->name), 'Online') === 0;
    }

    public function formattedAddress(): string
    {
        $address = trim((string) $this->address);
        $suburb = trim((string) $this->suburb);
        $state = trim((string) $this->state);
        $postcode = trim((string) $this->postcode);
        $parts = $address !== '' ? [$address] : [];

        if ($suburb !== '' && ! $this->containsAddressValue($address, $suburb)) {
            $parts[] = $suburb;
        }

        $region = collect([$state, $postcode])
            ->filter(fn (string $value): bool => $value !== '' && ! $this->containsAddressValue($address, $value))
            ->implode(' ');

        if ($region !== '') {
            $parts[] = $region;
        }

        return implode(', ', $parts);
    }

    public function suburbIsContainedInNameOrAddress(): bool
    {
        $suburb = trim((string) $this->suburb);
        if ($suburb === '') {
            return false;
        }

        return $this->containsAddressValue(
            implode(' ', array_filter([
                trim((string) $this->name),
                trim((string) $this->address),
            ])),
            $suburb,
        );
    }

    private function containsAddressValue(string $haystack, string $needle): bool
    {
        return preg_match(
            '/(?<![\p{L}\p{N}])'.preg_quote($needle, '/').'(?![\p{L}\p{N}])/iu',
            $haystack,
        ) === 1;
    }

    public function workshops(): HasMany
    {
        return $this->hasMany(Workshop::class);
    }
}
