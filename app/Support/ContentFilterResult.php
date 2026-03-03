<?php

namespace App\Support;

class ContentFilterResult
{
    public function __construct(
        public readonly bool $blocked,
        public readonly ?string $rule = null,
        public readonly ?string $message = null,
    ) {
    }

    public static function allow(): self
    {
        return new self(false);
    }

    public static function block(string $rule, string $message): self
    {
        return new self(true, $rule, $message);
    }
}
