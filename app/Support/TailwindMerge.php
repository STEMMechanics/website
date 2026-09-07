<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\View\ComponentAttributeBag;
use TalesFromADev\TailwindMerge\TailwindMerge as TailwindMergeEngine;
use TalesFromADev\TailwindMerge\TailwindMergeInterface;
use Traversable;

class TailwindMerge
{
    /** @var array<string, string> */
    private array $mergedClasses = [];

    public function __construct(
        private readonly TailwindMergeInterface $engine = new TailwindMergeEngine
    ) {}

    public function merge(...$args): string
    {
        $tokens = [];
        foreach ($args as $arg) {
            $tokens = array_merge($tokens, $this->flatten($arg));
        }

        if ($tokens === []) {
            return '';
        }

        $classes = implode(' ', $tokens);
        if (isset($this->mergedClasses[$classes])) {
            return $this->mergedClasses[$classes];
        }

        // Rows reuse the same component styles. Resolve each combination once
        // per request, with a bounded cache for large exports and worker jobs.
        $merged = $this->engine->merge($classes);
        if (count($this->mergedClasses) >= 512) {
            unset($this->mergedClasses[array_key_first($this->mergedClasses)]);
        }

        return $this->mergedClasses[$classes] = $merged;
    }

    /**
     * @return array<int, string>
     */
    private function flatten(mixed $value): array
    {
        if ($value instanceof ComponentAttributeBag) {
            return $this->flatten($value->get('class', ''));
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return [];
            }
            $parts = preg_split('/\s+/', $trimmed);

            return is_array($parts) ? $parts : [];
        }

        if (is_int($value) || is_float($value)) {
            return [(string) $value];
        }

        if (is_bool($value) || $value === null) {
            return [];
        }

        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $entry) {
                if (is_string($key) && is_bool($entry)) {
                    if ($entry) {
                        $result = array_merge($result, $this->flatten($key));
                    }

                    continue;
                }

                $result = array_merge($result, $this->flatten($entry));
            }

            return $result;
        }

        if ($value instanceof Traversable) {
            $result = [];
            foreach ($value as $entry) {
                $result = array_merge($result, $this->flatten($entry));
            }

            return $result;
        }

        return $this->flatten((string) $value);
    }
}
