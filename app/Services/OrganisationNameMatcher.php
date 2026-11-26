<?php

namespace App\Services;

use App\Models\Organisation;
use Illuminate\Support\Str;

class OrganisationNameMatcher
{
    /**
     * @return list<array{id: string, name: string, similarity: int}>
     */
    public function suggestions(string $name, int $limit = 3): array
    {
        $needle = $this->normalize($name);
        if (mb_strlen($needle) < 6) {
            return [];
        }

        $matches = [];
        foreach (Organisation::query()->orderBy('name')->get(['id', 'name']) as $organisation) {
            $candidate = $this->normalize((string) $organisation->name);
            if ($candidate === '' || $candidate === $needle || mb_strlen($candidate) < 6) {
                continue;
            }

            $longestLength = max(strlen($needle), strlen($candidate));
            $editDistance = levenshtein($needle, $candidate);
            $editSimilarity = (1 - ($editDistance / max(1, $longestLength))) * 100;
            similar_text($needle, $candidate, $characterSimilarity);
            $wordSimilarity = $this->wordSimilarity($needle, $candidate);
            $similarity = (int) round(max($editSimilarity, $characterSimilarity, $wordSimilarity));

            if ($similarity >= 82) {
                $matches[] = [
                    'id' => (string) $organisation->getKey(),
                    'name' => (string) $organisation->name,
                    'similarity' => $similarity,
                ];
            }
        }

        usort($matches, static fn (array $left, array $right): int => $right['similarity'] <=> $left['similarity']);

        return array_slice($matches, 0, max(1, $limit));
    }

    private function normalize(string $name): string
    {
        $ascii = Str::ascii(mb_strtolower(trim($name)));
        $normalized = preg_replace('/[^a-z0-9]+/', ' ', $ascii) ?? '';

        return trim(preg_replace('/\s+/', ' ', $normalized) ?? '');
    }

    private function wordSimilarity(string $left, string $right): float
    {
        $leftWords = array_values(array_unique(explode(' ', $left)));
        $rightWords = array_values(array_unique(explode(' ', $right)));
        $sharedWords = count(array_intersect($leftWords, $rightWords));

        return $sharedWords === 0 ? 0 : (2 * $sharedWords / (count($leftWords) + count($rightWords))) * 100;
    }
}
