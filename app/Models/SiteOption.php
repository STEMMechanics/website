<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class SiteOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'value',
    ];

    public function getValueToHtmlAttribute(): HtmlString
    {
        return new HtmlString(nl2br(e((string) ($this->value ?? ''))));
    }

    public static function value(string $name, ?string $default = null): ?string
    {
        $value = static::query()
            ->where('name', $name)
            ->value('value');

        return $value ?? $default;
    }

    public static function valueToHtml(string $name, ?string $default = null): HtmlString
    {
        return new HtmlString(nl2br(e((string) static::value($name, $default))));
    }
}
