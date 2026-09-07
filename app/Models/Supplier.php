<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $table = 'finance_supplier_rules';

    protected $fillable = ['name', 'supplier', 'category_id', 'mode', 'splits'];

    protected $casts = ['splits' => 'array', 'category_id' => 'integer'];

    /** @return HasMany<Expense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public static function forName(string $name): self
    {
        $name = trim($name);

        return self::query()->firstOrCreate(['supplier' => mb_strtolower($name)], ['name' => $name, 'mode' => 'default', 'splits' => []]);
    }
}
