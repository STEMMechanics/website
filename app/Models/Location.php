<?php

namespace App\Models;

use App\Traits\UUID;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory, UUID;

    public const STEMCRAFT_NAME = 'STEMCraft';

    protected $fillable = ['name', 'address', 'address_url', 'url'];

    public function isStemcraft(): bool
    {
        return strcasecmp(trim((string) $this->name), self::STEMCRAFT_NAME) === 0;
    }
}
