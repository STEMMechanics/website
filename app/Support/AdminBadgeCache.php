<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/** Cache display counts only; financial calculations and filters remain live. */
class AdminBadgeCache
{
    private array $values = [];

    private array $dirty = [];

    public function remember(string $group, Closure $resolve): array|int
    {
        $date = today()->toDateString();
        $key = $group.':'.$date;
        // Never publish a count that includes uncommitted changes.
        foreach ($this->dirty as $groups) {
            if (isset($groups[$group])) {
                return $resolve();
            }
        }

        return $this->values[$key] ??= (function () use ($group, $date, $resolve) {
            $generation = Cache::store(config('cache.admin_badges_store'))->get('admin-badges:v1:'.$group.':generation', 'initial');

            return Cache::store(config('cache.admin_badges_store'))->remember('admin-badges:v1:'.$group.':'.$date.':'.$generation, 60, $resolve);
        })();
    }

    public function written(QueryExecuted $event): void
    {
        if (! preg_match('/^\s*(?:insert(?:\s+or\s+\w+|\s+ignore)?\s+into|replace\s+into|update|delete\s+from)\s+[`"\[]?(\w+)/i', $event->sql, $match)) {
            return;
        }
        $table = strtolower($match[1]);
        if (in_array($table, ['site_options', 'user_groups', 'finance_categories', 'finance_supplier_rules', 'finance_pricing_versions'], true)) {
            app(RequestMemo::class)->clear();
        }
        $group = match ($table) {
            'invoices', 'expenses', 'finance_supplier_rules', 'finance_expense_splits', 'finance_budgets', 'finance_budget_invoices', 'tickets', 'workshops', 'invoice_lines', 'payments', 'invoice_payment_allocations', 'tax_adjustments', 'tax_adjustment_lines' => 'finance',
            'store_orders', 'square_refund_operations', 'inbound_sms' => 'operations',
            default => null,
        };
        if ($group === null) {
            return;
        }
        $this->values = [];
        app(RequestMemo::class)->clear();
        $this->dirty[$event->connectionName][$group] = true;
        if ($event->connection->transactionLevel() === 0) {
            $this->committed($event->connectionName);
        }
    }

    public function committed(?string $connection): void
    {
        foreach (array_keys($this->dirty[$connection] ?? []) as $group) {
            // A concurrent reader may finish computing the old generation;
            // subsequent requests will never use that stale result.
            Cache::store(config('cache.admin_badges_store'))->put('admin-badges:v1:'.$group.':generation', (string) Str::uuid(), now()->addDay());
        }
        unset($this->dirty[$connection]);
        $this->values = [];
        app(RequestMemo::class)->clear();
    }

    public function rolledBack(?string $connection): void
    {
        unset($this->dirty[$connection]);
        $this->values = [];
        app(RequestMemo::class)->clear();
    }
}
