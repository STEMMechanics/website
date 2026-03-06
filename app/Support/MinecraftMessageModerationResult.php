<?php

namespace App\Support;

class MinecraftMessageModerationResult
{
    public function __construct(
        public readonly bool $pass,
        public readonly ?string $reason = null,
        public readonly ?string $reasonLabel = null,
        public readonly ?string $reasonDetail = null,
        public readonly ?string $filteredMessage = null,
    ) {}

    public static function allow(): self
    {
        return new self(true);
    }

    public static function block(
        string $reason,
        ?string $reasonLabel = null,
        ?string $reasonDetail = null,
        ?string $filteredMessage = null,
    ): self {
        return new self(false, $reason, $reasonLabel, $reasonDetail, $filteredMessage);
    }
}
