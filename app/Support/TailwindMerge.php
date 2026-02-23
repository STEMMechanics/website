<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\View\ComponentAttributeBag;
use Traversable;

class TailwindMerge
{
    public function merge(...$args): string
    {
        $tokens = [];
        foreach ($args as $arg) {
            $tokens = array_merge($tokens, $this->flatten($arg));
        }

        if ($tokens === []) {
            return '';
        }

        $lastByToken = [];
        foreach ($tokens as $index => $token) {
            if ($token === '') {
                continue;
            }
            $lastByToken[$token] = $index;
        }

        $ordered = [];
        foreach ($tokens as $index => $token) {
            if ($token === '' || ($lastByToken[$token] ?? -1) !== $index) {
                continue;
            }
            $ordered[] = $token;
        }

        return implode(' ', $ordered);
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
