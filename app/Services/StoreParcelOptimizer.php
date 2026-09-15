<?php

namespace App\Services;

/** Searches whole-item parcel partitions, with a bounded search for larger carts. */
class StoreParcelOptimizer
{
    public function pack(array $items, callable $quoteParcel): ?array
    {
        $cache = [];
        $quote = function (array $indices) use ($items, $quoteParcel, &$cache): ?array {
            sort($indices);
            $key = implode(',', $indices);
            if (! array_key_exists($key, $cache)) {
                // Bound memory for unusually large carts.
                if (count($cache) >= 10000) {
                    $cache = [];
                }
                $cache[$key] = $quoteParcel(array_map(fn (int $i): array => $items[$i], $indices));
            }

            return $cache[$key];
        };

        if (count($items) <= 10) {
            // Every partition is considered; fixing the first item avoids duplicate partitions.
            $solutions = [0 => []];
            $solve = function (int $mask) use (&$solve, &$solutions, $quote, $items): ?array {
                if (array_key_exists($mask, $solutions)) {
                    return $solutions[$mask];
                }
                $first = $mask & -$mask;
                $best = null;
                for ($subset = $mask; $subset > 0; $subset = ($subset - 1) & $mask) {
                    if (! ($subset & $first)) {
                        continue;
                    }
                    $indices = [];
                    foreach (array_keys($items) as $i) {
                        if ($subset & (1 << $i)) {
                            $indices[] = $i;
                        }
                    }
                    $parcel = $quote($indices);
                    if ($parcel === null) {
                        continue;
                    }
                    $rest = $solve($mask ^ $subset);
                    if ($rest !== null) {
                        $candidate = [$parcel, ...$rest];
                        if ($best === null || $this->score($candidate) < $this->score($best)) {
                            $best = $candidate;
                        }
                    }
                }

                return $solutions[$mask] = $best;
            };

            return $solve((1 << count($items)) - 1);
        }

        $orders = [];
        foreach (['volume', 'weight', 'longest'] as $sort) {
            $indices = array_keys($items);
            usort($indices, fn (int $a, int $b): int => match ($sort) {
                'volume' => array_product($items[$b]['dimensions']) <=> array_product($items[$a]['dimensions']),
                'weight' => $items[$b]['weight_grams'] <=> $items[$a]['weight_grams'],
                default => max($items[$b]['dimensions']) <=> max($items[$a]['dimensions']),
            });
            $orders[] = $indices;
            $orders[] = array_reverse($indices);
        }
        $best = null;
        foreach (array_unique($orders, SORT_REGULAR) as $order) {
            $groups = [];
            $parcels = [];
            foreach ($order as $index) {
                $single = $quote([$index]);
                $choice = $single === null ? null : ['index' => count($groups), 'parcel' => $single, 'cost' => $single['package']['price']];
                foreach ($groups as $i => $group) {
                    $parcel = $quote([...$group, $index]);
                    if ($parcel === null) {
                        continue;
                    }
                    $cost = $parcel['package']['price'] - $parcels[$i]['package']['price'];
                    if ($choice === null || $cost <= $choice['cost']) {
                        $choice = ['index' => $i, 'parcel' => $parcel, 'cost' => $cost];
                    }
                }
                if ($choice === null) {
                    continue 2;
                }
                $i = $choice['index'];
                $groups[$i][] = $index;
                $parcels[$i] = $choice['parcel'];
            }
            // A tier can make merging complete parcels cheaper than incremental placement.
            do {
                $merge = null;
                foreach ($groups as $a => $left) {
                    foreach ($groups as $b => $right) {
                        if ($a >= $b) {
                            continue;
                        }
                        $parcel = $quote([...$left, ...$right]);
                        if ($parcel === null) {
                            continue;
                        }
                        $saving = $parcels[$a]['package']['price'] + $parcels[$b]['package']['price'] - $parcel['package']['price'];
                        if ($saving >= 0 && ($merge === null || $saving > $merge['saving'])) {
                            $merge = compact('a', 'b', 'parcel', 'saving');
                        }
                    }
                }
                if ($merge !== null) {
                    ['a' => $a, 'b' => $b, 'parcel' => $parcel] = $merge;
                    $groups[$a] = [...$groups[$a], ...$groups[$b]];
                    $parcels[$a] = $parcel;
                    unset($groups[$b], $parcels[$b]);
                }
            } while ($merge !== null);
            if ($best === null || $this->score($parcels) < $this->score($best)) {
                $best = array_values($parcels);
            }
        }

        return $best;
    }

    private function score(array $parcels): array
    {
        return [array_sum(array_map(fn (array $parcel): int => (int) round($parcel['package']['price'] * 100), $parcels)), count($parcels)];
    }

    /**
     * Keep several physically achievable bounding boxes while stacking whole items.
     * Dimensions are sorted because a completed box may itself be rotated.
     * This is a packing estimate, not the sum of item volumes (which ignores empty space).
     */
    public function calculatedDimensions(array $items): array
    {
        $best = null;
        $orders = [$items, array_reverse($items)];
        foreach ($orders as $order) {
            $boxes = [[0, 0, 0]];
            foreach ($order as $item) {
                [$a, $b, $c] = $item['dimensions'];
                $orientations = [[$a, $b, $c], [$a, $c, $b], [$b, $a, $c], [$b, $c, $a], [$c, $a, $b], [$c, $b, $a]];
                $candidates = [];
                foreach ($boxes as $box) {
                    foreach ($orientations as $dimensions) {
                        for ($axis = 0; $axis < 3; $axis++) {
                            $next = [];
                            for ($i = 0; $i < 3; $i++) {
                                $next[] = $i === $axis ? $box[$i] + $dimensions[$i] : max($box[$i], $dimensions[$i]);
                            }
                            sort($next);
                            $candidates[implode(':', $next)] = $next;
                        }
                    }
                }
                usort($candidates, fn (array $a, array $b): int => [array_product($a), array_sum($a)] <=> [array_product($b), array_sum($b)]);
                $boxes = array_slice($candidates, 0, 8);
            }
            if ($best === null || array_product($boxes[0]) < array_product($best)) {
                $best = $boxes[0];
            }
        }

        return $best;
    }
}
