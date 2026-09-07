<?php

namespace App\Support;

use Closure;

/** Reuse lookups within a request, without carrying user data between requests. */
class RequestMemo
{
    private array $values = [];

    public function remember(string $key, Closure $resolve): mixed
    {
        if (! array_key_exists($key, $this->values)) {
            $this->values[$key] = $resolve();
        }

        return $this->values[$key];
    }

    public function clear(): void
    {
        $this->values = [];
    }
}
