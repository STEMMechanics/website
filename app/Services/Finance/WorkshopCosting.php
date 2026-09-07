<?php

namespace App\Services\Finance;

class WorkshopCosting
{
    public function guide(object $version, array $names): array
    {
        $planner = app(FinancePlanner::class);
        $rules = $planner->decode($version->rules);
        $prices = $planner->decode($version->prices);
        $participants = max(1, (int) ($prices['pricing_participants'] ?? 10));
        $supplied = [];
        foreach ($rules as $rule) {
            if ($rule['basis'] === 'venue_hour' || (! empty($rule['suppliable']) && ! empty($rule['venue_default']))) {
                $supplied[$rule['category_id']] = true;
            }
        }
        $durations = [];
        foreach ([1, 2, 3, 4] as $hours) {
            $input = ['hours' => $hours, 'participants' => $participants, 'travel_hours' => 0, 'supplied_categories' => []];
            $durations[$hours] = [
                'standard' => $this->calculate($version, $input)['ticket'],
                'school' => $this->calculate($version, array_replace($input, ['supplied_categories' => $supplied]))['ticket'],
            ];
        }
        $travelRate = $this->calculate($version, ['hours' => 1, 'participants' => $participants, 'travel_hours' => 0.25, 'supplied_categories' => []])['travel']['gross'];
        $breakdown = [];
        $travelDetails = [];
        $basis = ['workshop' => 'per workshop', 'hour' => 'per hour', 'participant' => 'per participant', 'venue_hour' => 'per hour'];
        foreach ($rules as $rule) {
            $name = $names[$rule['category_id']] ?? 'Cost centre';
            $amount = '$'.number_format($rule['rate_cents'] / 100, 2);
            if ($rule['basis'] === 'travel') {
                $travelDetails[] = $name.' '.$amount;
            } else {
                $breakdown[$name][] = $amount.' '.($basis[$rule['basis']] ?? $rule['basis']).(isset($supplied[$rule['category_id']]) && ($rule['basis'] === 'venue_hour' || ! empty($rule['suppliable'])) ? ' (excluded for school/library)' : '');
            }
        }
        $travelBreakdown = implode(', ', $travelDetails);
        $freeMinutes = (int) ($prices['travel_free_minutes'] ?? 30);
        // Billable region units from the user's 2026-27 Pricing reference sheet.
        $regions = array_map(fn ($name, $units) => compact('name', 'units'),
            ['Babinda', 'Mareeba', 'Port Douglas', 'Innisfail', 'Atherton', 'Malanda', 'Herberton', 'Ravenshoe'],
            [1, 2, 2, 2, 3, 3, 4, 5]);

        return compact('version', 'participants', 'durations', 'travelRate', 'travelBreakdown', 'freeMinutes', 'regions', 'breakdown');
    }

    public function calculate(object $version, array $inputs): array
    {
        $planner = app(FinancePlanner::class);
        $rules = $planner->decode($version->rules);
        $prices = $planner->decode($version->prices);
        // Travel is entered as billable hours, with the free allowance already removed.
        $inputs = array_replace($inputs, ['travel_minutes' => $inputs['travel_hours'] * 60, 'travel_free_minutes' => 0, 'venue_supplied' => false]);
        $rows = [];
        foreach ($rules as $rule) {
            $rows[] = $rule + ['amount' => array_sum($planner->targets([$rule], $inputs))];
        }
        $travelCost = array_sum(array_column(array_filter($rows, fn ($row) => $row['basis'] === 'travel'), 'amount'));
        $cost = array_sum(array_column($rows, 'amount'));
        $quantity = $inputs['hours'] * $inputs['participants'];
        $workshop = $this->line($cost - $travelCost, $quantity, (int) ($prices['rounding_step'] ?? 0));
        $travelUnits = $inputs['travel_hours'] * 4;
        $travelRules = array_filter($rules, fn ($rule) => $rule['basis'] === 'travel');
        $travel = $this->line($travelRules ? $travelCost : ($prices['travel_cents'] ?? 0) / 1.1 * $travelUnits, $travelUnits, (int) ($prices['travel_rounding_step'] ?? 0));
        $ticketStep = max(1, (int) ($prices['rounding_step'] ?? 0));
        $ticket = (int) (ceil((($cost - $travelCost) * 1.1 / $inputs['participants'] - 0.000001) / $ticketStep) * $ticketStep);

        return ['rows' => $rows, 'cost' => $cost, 'workshop' => $workshop, 'travel' => $travel,
            'gross' => $workshop['gross'] + $travel['gross'], 'tax' => $workshop['tax'] + $travel['tax'],
            'balance' => $workshop['net'] + $travel['net'] - $cost, 'ticket' => $ticket];
    }

    private function line(float $cost, float $quantity, int $step): array
    {
        if ($quantity <= 0) {
            return ['net' => 0, 'tax' => 0, 'gross' => 0, 'unit_gross' => 0];
        }
        // Match the quote/invoice editor: rounded inclusive units when a step is set,
        // otherwise a cent-rounded net unit and GST rounded on the line total.
        $unitGross = $cost * 1.1 / $quantity;
        if ($step > 0) {
            $unitGross = ceil(($unitGross - 0.000001) / $step) * $step;
            $gross = (int) round($unitGross * $quantity);
            $net = (int) round($gross / 1.1);
            $tax = $gross - $net;
        } else {
            $net = (int) round(round($cost / $quantity) * $quantity);
            $tax = (int) round($net * 0.1);
            $gross = $net + $tax;
            $unitGross = round(round($cost / $quantity) * 1.1);
        }

        return ['net' => $net, 'tax' => $tax, 'gross' => $gross, 'unit_gross' => (int) round($unitGross)];
    }
}
