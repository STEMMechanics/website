<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SiteListControls
{
    public function __construct(private ?string $scope = null) {}

    public function parameter(string $name): string
    {
        return ($this->scope ? $this->scope.'_' : 'list_').$name;
    }

    public function paginationReset(): array
    {
        $keys = array_filter(array_keys(request()->query()), fn ($key) => $key === 'page' || str_ends_with($key, '_page') || (request()->routeIs('search.index') && in_array($key, ['product', 'workshop'])));
        return array_fill_keys([...$keys, 'page'], null);
    }

    public function searchParameter(): string
    {
        return $this->scope ? $this->scope.'_search' : 'search';
    }

    public function reportQuery($query)
    {
        $definition = $this->definition();
        if (isset($definition['model'])) {
            $this->apply($query);
            return $query;
        }
        if (!collect(array_keys(request()->query()))->contains(fn ($key) => str_starts_with($key, $this->scope.'_'))) return $query;
        $wrapped = \Illuminate\Support\Facades\DB::query()->fromSub((clone $query)->reorder(), 'list_results');
        $this->apply($wrapped);
        if (!request()->filled($this->parameter('sort'))) {
            foreach ($definition['order'] ?? [] as $column => $direction) $wrapped->orderBy($column, $direction);
        }
        return $wrapped;
    }

    public function definition(): array
    {
        if ($this->scope) return config('report-listings', [])[$this->scope] ?? [];
        $route = request()->route()?->getName() ?? '';
        $route = preg_replace('/\.(csv|pdf)$/', '', $route);
        return config('listings', [])[$route] ?? [];
    }

    public function fields(): array
    {
        return $this->definition()['fields'] ?? [];
    }

    public function nativeFields(): array
    {
        if ($this->scope) return [];
        return app(\App\Support\RequestMemo::class)->remember('list-native-fields:'.request()->route()?->getName(), fn () => $this->resolveNativeFields());
    }

    private function resolveNativeFields(): array
    {
        return match (request()->route()?->getName()) {
            'admin.workshop.history', 'admin.workshop.history.csv', 'admin.workshop.history.pdf', 'admin.workshop.coverage', 'admin.workshop.coverage.csv', 'admin.workshop.coverage.pdf' => [
                'organisation_ids' => ['label' => 'Organisations', 'type' => 'array'],
                'location_ids' => ['label' => 'Locations', 'type' => 'array'],
                'requested_by_user_ids' => ['label' => 'Contacts', 'type' => 'array'],
                'category_ids' => ['label' => 'Categories', 'type' => 'array'],
                'date_from' => ['label' => 'From date', 'type' => 'date'],
                'date_to' => ['label' => 'To date', 'type' => 'date'],
                'past_only' => ['label' => 'Past workshops only', 'type' => 'boolean'],
                'include_cancelled' => ['label' => 'Include cancelled and drafts', 'type' => 'boolean'],
                'include_children' => ['label' => 'Include child organisations', 'type' => 'boolean'],
            ],
            'admin.cost-centre.index' => ['state' => ['label' => 'Status', 'type' => 'select', 'options' => ['all' => 'All', 'active' => 'Active', 'archived' => 'Archived'], 'default' => 'all', 'clear' => 'all']],
            'admin.supplier.index' => [
                'cost_centre_id' => ['label' => 'Cost centre', 'type' => 'select', 'options' => \Illuminate\Support\Facades\DB::table('finance_categories')->where('kind', 'cost')->orderBy('name')->pluck('name', 'id')->all()],
            ],
            'shop.index' => ['category' => ['label' => 'Category', 'type' => 'text']],
            'workshop.index', 'workshop.past.index' => ['category' => ['label' => 'Category', 'type' => 'text']],
            'admin.workshop.index' => ['show_cancelled' => ['label' => 'Include cancelled', 'type' => 'boolean', 'clear' => '1', 'default' => '1']],
            'admin.workshop.attendance' => ['show_cancelled' => ['label' => 'Include cancelled', 'type' => 'boolean', 'clear' => '1']],
            'admin.invoice.index' => [
                'status' => ['label' => 'Status', 'type' => 'array', 'options' => array_combine(\App\Models\Invoice::STATUSES, array_map(fn ($status) => ucwords(str_replace('_', ' ', $status)), \App\Models\Invoice::STATUSES))],
                'customer' => ['label' => 'Customer name or email', 'type' => 'text'],
                'line_types' => ['label' => 'Contains line types', 'type' => 'array', 'options' => ['ticket' => 'Ticket', 'workshop' => 'Workshop', 'multi_workshop' => 'Multi Workshop Delivery', 'travel' => 'Travel', 'product' => 'Product', 'custom' => 'Custom / other']],
                'allocation_state' => ['label' => 'Cost-centre allocation', 'type' => 'select', 'options' => ['not_allocated' => 'Not allocated', 'automatic' => 'Automatic', 'manual' => 'Manual override']],
                'allocation_plan' => ['label' => 'Allocation plan', 'type' => 'select', 'options' => \Illuminate\Support\Facades\DB::table('finance_pricing_versions')->orderBy('name')->get()->mapWithKeys(fn ($plan) => [(string) $plan->id => $plan->name.($plan->is_snapshot ? ' (saved revision)' : ($plan->archived ? ' (archived)' : ''))])->all()],
                'payment_from' => ['label' => 'Payment date — From', 'type' => 'date'],
                'payment_to' => ['label' => 'Payment date — To', 'type' => 'date'],
            ],
            'admin.quote.index', 'admin.shop.order.index', 'admin.server.sent-emails', 'admin.server.sent-sms' => ['status' => ['label' => 'Status', 'type' => 'text']],
            'admin.server.square-events', 'admin.server.square-webhooks' => ['event_type' => ['label' => 'Event type', 'type' => 'text']],
            'admin.workshop.files', 'admin.workshop.photos' => ['visibility' => ['label' => 'Visibility', 'type' => 'select', 'options' => ['public' => 'Public', 'private' => 'Private']]],
            'admin.expense.index', 'admin.supplier.show' => [
                'allocation_state' => ['label' => 'Cost-centre allocation', 'type' => 'select', 'options' => ['not_allocated' => 'Missing or incomplete', 'allocated' => 'Allocated']],
                'supplier_id' => ['label' => 'Supplier account', 'type' => 'select', 'options' => \App\Models\Supplier::orderBy('name')->pluck('name', 'id')->all()],
                'supplier' => ['label' => 'Supplier', 'type' => 'text'],
                'description' => ['label' => 'Description', 'type' => 'text'],
                'invoice_id' => ['label' => 'Invoice ID', 'type' => 'text'],
                'attachment' => ['label' => 'Attachment text or filename', 'type' => 'text'],
                'paid_from' => ['label' => 'Paid from', 'type' => 'date'],
                'paid_to' => ['label' => 'Paid to', 'type' => 'date'],
                'no_attachment' => ['label' => 'Without attachment', 'type' => 'boolean'],
            ],
            'admin.payment.index' => ['unallocated_only' => ['label' => 'Allocation', 'type' => 'select', 'options' => ['1' => 'Unallocated payments']]],
            'admin.server.audit' => ['event' => ['label' => 'Event', 'type' => 'text']],
            'admin.payment.refunds' => ['hide_completed' => ['label' => 'Hide completed', 'type' => 'boolean']],
            'account.order.index' => ['order_scope' => ['label' => 'Orders', 'type' => 'select', 'options' => ['current' => 'Current orders', 'cancelled' => 'Cancelled', 'all' => 'All orders'], 'clear' => 'all']],
            'admin.ticket.index' => [
                'ticket_status' => ['label' => 'Status', 'type' => 'array', 'options' => ['active' => 'Active', 'cancelled' => 'Cancelled', 'reissued' => 'Reissued']],
                'workshop_name' => ['label' => 'Workshop', 'type' => 'text'],
                'workshop_from' => ['label' => 'Workshop date — From', 'type' => 'date'],
                'workshop_to' => ['label' => 'Workshop date — To', 'type' => 'date'],
            ],
            'account.ticket.index' => ['ticket_scope' => ['label' => 'Tickets', 'type' => 'select', 'options' => ['current' => 'Current tickets', 'cancelled' => 'Cancelled / reissued', 'all' => 'All tickets'], 'clear' => 'all']],
            'admin.user.index' => ['account_state' => ['label' => 'Accounts', 'type' => 'select', 'options' => ['all' => 'All users', 'verified' => 'Verified users', 'ghost' => 'Unverified users'], 'clear' => 'all']],
            'admin.shop.product.index' => [
                'status_scope' => ['label' => 'Products', 'type' => 'select', 'options' => ['all' => 'All statuses', 'current' => 'Current products', 'archived' => 'Archived'], 'clear' => 'all'],
                'inventory' => ['label' => 'Inventory', 'type' => 'select', 'options' => ['actionable' => 'Needs attention']],
            ],
            'admin.reminder.index' => ['view' => ['label' => 'Delivery', 'type' => 'select', 'options' => ['all' => 'All reminders', 'upcoming' => 'Upcoming', 'sent' => 'Sent', 'failed' => 'Failed'], 'clear' => 'all']],
            default => [],
        };
    }

    public function capturePresetCounts($query): void
    {
        if (request()->attributes->has('collection_preset_counts')) return;
        $counts = match (request()->route()?->getName()) {
            'admin.user.index' => [
                'Verified users' => (clone $query)->whereNotNull('email_verified_at')->count(),
                'Unverified users' => (clone $query)->whereNull('email_verified_at')->whereNull('anonymized_at')->count(),
                'All users' => (clone $query)->count(),
            ],
            'account.order.index' => [
                'Current orders' => (clone $query)->where('status', '!=', 'cancelled')->count(),
                'Cancelled' => (clone $query)->where('status', 'cancelled')->count(),
                'All orders' => (clone $query)->count(),
            ],
            'admin.ticket.index', 'account.ticket.index' => [
                'Current tickets' => (clone $query)->whereNotIn('status', [\App\Models\Ticket::STATUS_CANCELLED, \App\Models\Ticket::STATUS_REISSUED])->when(request()->routeIs('admin.ticket.index'), fn ($tickets) => $tickets->whereIn('status', \App\Models\Ticket::activePurchasedStatuses())->whereHas('workshop', fn ($workshops) => $workshops->where('starts_at', '>=', today()->startOfDay())))->count(),
                'Cancelled / reissued' => (clone $query)->whereIn('status', [\App\Models\Ticket::STATUS_CANCELLED, \App\Models\Ticket::STATUS_REISSUED])->count(),
                'All tickets' => (clone $query)->count(),
            ],
            'admin.reminder.index' => [
                'Upcoming' => (clone $query)->whereIn('status', [\App\Models\Reminder::STATUS_PENDING, \App\Models\Reminder::STATUS_QUEUED])->count(),
                'Sent' => (clone $query)->where('status', \App\Models\Reminder::STATUS_SENT)->count(),
                'Failed' => (clone $query)->where('status', \App\Models\Reminder::STATUS_FAILED)->count(),
                'All reminders' => (clone $query)->count(),
            ],
            'admin.workshop.index' => [
                'All workshops' => (clone $query)->count(),
                'Current' => (clone $query)->where('status', '!=', 'cancelled')->where('starts_at', '>=', today())->count(),
            ],
            'admin.workshop.attendance' => [
                'Current' => (clone $query)->where('status', '!=', 'cancelled')->count(),
                'Including cancelled' => (clone $query)->count(),
            ],
            'admin.payment.refunds' => [
                'All refunds' => (clone $query)->count(),
                'Unfinished' => (clone $query)->where('status', '!=', \App\Models\SquareRefundOperation::STATUS_COMPLETED)->count(),
            ],
            default => [],
        };
        if ($counts) request()->attributes->set('collection_preset_counts', $counts);
    }

    public function presets(): array
    {
        if ($this->scope) return [];
        $presets = match (request()->route()?->getName()) {
            'admin.workshop.index' => ['All workshops' => ['show_cancelled' => '1'], 'Current' => ['show_cancelled' => '0', 'list_starts_at_min' => today()->toDateString()]],
            'admin.workshop.attendance' => ['Current' => ['show_cancelled' => '0'], 'Including cancelled' => ['show_cancelled' => '1']],
            'admin.payment.index' => ['All payments' => [], 'Unallocated' => ['unallocated_only' => '1']],
            'admin.payment.refunds' => ['All refunds' => [], 'Unfinished' => ['hide_completed' => '1']],
            'account.order.index' => ['Current orders' => ['order_scope' => 'current'], 'Cancelled' => ['order_scope' => 'cancelled'], 'All orders' => ['order_scope' => 'all']],
            'admin.ticket.index' => ['Current tickets' => ['ticket_status' => ['active'], 'workshop_from' => today()->toDateString()], 'Cancelled / reissued' => ['ticket_status' => ['cancelled', 'reissued']], 'All tickets' => []],
            'account.ticket.index' => ['Current tickets' => ['ticket_scope' => 'current'], 'Cancelled / reissued' => ['ticket_scope' => 'cancelled'], 'All tickets' => ['ticket_scope' => 'all']],
            'admin.user.index' => ['Verified users' => ['account_state' => 'verified'], 'Unverified users' => ['account_state' => 'ghost'], 'All users' => ['account_state' => 'all']],
            'admin.shop.product.index' => ['Current products' => ['status_scope' => 'current'], 'Actionable' => ['status_scope' => 'current', 'inventory' => 'actionable'], 'Archived' => ['status_scope' => 'archived']],
            'admin.reminder.index' => ['Upcoming' => ['view' => 'upcoming'], 'Sent' => ['view' => 'sent'], 'Failed' => ['view' => 'failed'], 'All reminders' => ['view' => 'all']],
            default => [],
        };
        $active = array_filter(request()->only([...array_keys($this->filterFields()), 'search']), fn ($value) => is_array($value) ? count($value) > 0 : (is_scalar($value) && (string) $value !== ''));
        return collect($presets)->map(fn ($filters, $title) => [
            'title' => $title, 'active' => $active == $filters, 'count' => request()->attributes->get('collection_preset_counts', [])[$title] ?? null,
            'route' => url()->current().'?'.http_build_query(array_merge(request()->except([...array_keys($this->filterFields()), 'search', 'page', 'backup_page']), $filters)),
        ])->values()->all();
    }

    public function filterFields(): array
    {
        $filters = $this->nativeFields();
        foreach ($this->fields() as $key => $field) {
            if (($field['filter'] ?? true) === false) continue;
            if (isset($filters[$key])) continue;
            if (in_array($field['type'], ['number', 'date'])) {
                foreach (['min' => 'From', 'max' => 'To'] as $suffix => $label) {
                    $filters[$this->parameter($key.'_'.$suffix)] = $field + ['column' => $key, 'operator' => $suffix === 'min' ? '>=' : '<='];
                    $filters[$this->parameter($key.'_'.$suffix)]['label'] .= ' — '.$label;
                }
            } else {
                $filters[$this->parameter($key)] = $field + ['column' => $key];
            }
        }
        return $filters;
    }

    /** Filter complete in-memory inventories before slicing their paginator. */
    public function applyCollection(\Illuminate\Support\Collection $items): \Illuminate\Support\Collection
    {
        $rules = [$this->searchParameter() => ['nullable', 'string', 'max:255'], $this->parameter('sort') => ['nullable', Rule::in(array_keys($this->fields()))], $this->parameter('direction') => ['nullable', Rule::in(['asc', 'desc'])]];
        foreach ($this->filterFields() as $key => $field) {
            $rules[$key] = ['nullable', ...match ($field['type']) {
                'number' => ['numeric'], 'date' => ['date_format:Y-m-d'], default => ['string', 'max:255'],
            }];
        }
        $data = Validator::make(request()->query(), $rules)->validate();
        $items = $items->filter(function ($item) use ($data) {
            if (!empty($data[$this->searchParameter()]) && !collect($this->fields())->filter(fn ($field) => ($field['type'] === 'text' && !isset($field['sort_sql'])))->keys()->contains(fn ($key) => str_contains(mb_strtolower((string) data_get($item, $key)), mb_strtolower($data[$this->searchParameter()])))) return false;
            foreach ($this->filterFields() as $key => $field) {
                if (!isset($data[$key]) || $data[$key] === '') continue;
                $value = data_get($item, $field['column']);
                if ($field['type'] === 'date') $value = substr((string) $value, 0, 10);
                if (isset($field['operator'])) {
                    if ($field['operator'] === '>=' && $value < $data[$key]) return false;
                    if ($field['operator'] === '<=' && $value > $data[$key]) return false;
                } else {
                    $pattern = str_replace(['\\*', '\\?'], ['.*', '.'], preg_quote($data[$key], '/'));
                    if (!preg_match('/'.$pattern.'/iu', (string) $value)) return false;
                }
            }
            return true;
        });
        return empty($data[$this->parameter('sort')]) ? $items->values() : $items->sortBy([[$data[$this->parameter('sort')], $data[$this->parameter('direction')] ?? 'asc']])->values();
    }

    public function apply($query): void
    {
        $definition = $this->definition();
        if (!$definition) return;
        if (!isset($definition['model']) && !$this->scope) return;
        if (request()->routeIs('admin.workshop.index', 'admin.workshop.attendance') && !request()->filled('show_cancelled')) request()->query->set('show_cancelled', request()->routeIs('admin.workshop.index') ? '1' : '0');
        $model = isset($definition['model']) ? new $definition['model'] : null;
        $table = $model ? ($query instanceof Builder ? $query->getModel()->getTable() : $query->from) : 'list_results';
        if ($table !== ($model?->getTable() ?? 'list_results')) return;
        if (!$this->scope) $this->capturePresetCounts($query);
        if (request()->routeIs('admin.ticket.index')) {
            $legacy = request()->query('ticket_scope');
            $explicit = request()->hasAny(['ticket_status', 'workshop_from', 'workshop_to', 'workshop_name', 'ticket_filters']);
            if ($legacy !== null || !$explicit) {
                $legacy = $legacy ?? (request()->boolean('show_inactive') ? 'all' : 'current');
                Validator::make(['ticket_scope' => $legacy], ['ticket_scope' => Rule::in(['current', 'cancelled', 'all'])])->validate();
                request()->query->set('ticket_status', match ($legacy) {
                    'current' => ['active'], 'cancelled' => ['cancelled', 'reissued'], default => [],
                });
                if ($legacy === 'current') request()->query->set('workshop_from', today()->toDateString());
            }
            request()->query->remove('ticket_scope');
            request()->query->remove('show_inactive');
            request()->query->set('ticket_filters', '1');
        }
        if ($this->scope && request()->filled($this->searchParameter())) {
            $search = request()->validate([$this->searchParameter() => ['nullable', 'string', 'max:255']])[$this->searchParameter()];
            $query->where(function ($nested) use ($table, $search) {
                foreach ($this->fields() as $key => $field) if (($field['type'] === 'text' && !isset($field['sort_sql']))) $nested->orWhere($table.'.'.$key, 'like', '%'.$search.'%');
            });
        }
        if (request()->filled('search') && in_array(request()->route()?->getName(), ['admin.shop.category.index', 'admin.workshop-category.index', 'admin.subscription.theme.index', 'admin.user.payments', 'workshop.index', 'workshop.past.index'], true)) {
            $search = request()->validate(['search' => ['nullable', 'string', 'max:255']])['search'];
            $textFields = array_keys(array_filter($this->fields(), fn ($field) => ($field['type'] === 'text' && !isset($field['sort_sql']))));
            $query->where(function ($nested) use ($table, $textFields, $search) {
                foreach ($textFields as $field) $nested->orWhere($table.'.'.$field, 'like', '%'.$search.'%');
            });
        }
        if (request()->routeIs('account.order.index', 'account.ticket.index')) {
            $orders = request()->routeIs('account.order.index');
            $key = $orders ? 'order_scope' : 'ticket_scope';
            $scope = request()->query($key, request()->boolean('show_inactive') ? 'all' : 'current') ?: 'all';
            Validator::make([$key => $scope], [$key => Rule::in(['current', 'cancelled', 'all'])])->validate();
            request()->query->set($key, $scope);
            request()->query->remove('show_inactive');
            $inactive = $orders ? ['cancelled'] : [\App\Models\Ticket::STATUS_CANCELLED, \App\Models\Ticket::STATUS_REISSUED];
            if ($scope === 'current') {
                $query->whereNotIn($table.'.status', $inactive);

            }
            if ($scope === 'cancelled') $query->whereIn($table.'.status', $inactive);
        }
        if (request()->routeIs('admin.workshop.index') && !request()->boolean('show_cancelled')) $query->whereNotIn($table.'.status', ['cancelled']);
        $rules = [$this->parameter('sort') => ['nullable', Rule::in(array_keys($this->fields()))], $this->parameter('direction') => ['nullable', Rule::in(['asc', 'desc'])]];
        foreach ($this->filterFields() as $key => $field) {
            $rules[$key] = match ($field['type']) {
                'array' => ['nullable', 'array', 'max:100'],
                'number' => ['nullable', 'numeric'],
                'date' => ['nullable', 'date_format:Y-m-d'],
                'boolean' => ['nullable', Rule::in(['0', '1'])],
                'select' => ['nullable', Rule::in(array_keys($field['options']))],
                default => ['nullable', 'string', 'max:255'],
            };
        }
        foreach ($this->filterFields() as $key => $field) {
            if ($field['type'] === 'array' && isset($field['options'])) $rules[$key.'.*'] = ['string', Rule::in(array_keys($field['options']))];
        }
        $data = Validator::make(request()->query(), $rules)->validate();
        if (request()->routeIs('admin.invoice.index')) { app(\App\Services\Finance\InvoiceAllocationFilters::class)->apply($query, $data); }
        if (request()->routeIs('admin.expense.index', 'admin.supplier.show') && ! empty($data['allocation_state'])) {
            $match = fn ($part) => app(\App\Services\Finance\FinanceAttention::class)->unallocatedExpenses($part);
            if ($data['allocation_state'] === 'not_allocated') { $query->where($match); }
            else { $query->whereNot($match); }
        }

        if (request()->routeIs('admin.ticket.index')) {
            $statuses = [];
            foreach ($data['ticket_status'] ?? [] as $status) {
                $statuses = [...$statuses, ...match ($status) {
                    'active' => \App\Models\Ticket::activePurchasedStatuses(),
                    'cancelled' => [\App\Models\Ticket::STATUS_CANCELLED],
                    'reissued' => [\App\Models\Ticket::STATUS_REISSUED],
                    default => throw \Illuminate\Validation\ValidationException::withMessages([
                        'ticket_status' => 'Select a valid ticket status.',
                    ]),
                }];
            }
            if ($statuses) $query->whereIn('tickets.status', array_unique($statuses));
            if (!empty($data['workshop_from'])) $query->whereHas('workshop', fn ($workshops) => $workshops->where('starts_at', '>=', $data['workshop_from']));
            if (!empty($data['workshop_to'])) $query->whereHas('workshop', fn ($workshops) => $workshops->where('starts_at', '<', \Carbon\Carbon::parse($data['workshop_to'])->addDay()->toDateString()));
            if (!empty($data['workshop_name'])) {
                $name = trim($data['workshop_name']);
                $pattern = strtr(mb_strtolower($name), ['!' => '!!', '%' => '!%', '_' => '!_', '*' => '%', '?' => '_']);
                if (!str_contains($name, '*') && !str_contains($name, '?')) $pattern = '%'.$pattern.'%';
                $query->whereHas('workshop', fn ($workshops) => $workshops->whereRaw("LOWER(title) LIKE ? ESCAPE '!'", [$pattern]));
            }
        }
        foreach ($this->filterFields() as $key => $field) {
            if (!isset($field['column']) || !isset($data[$key]) || $data[$key] === '') continue;
            $column = $table.'.'.$field['column'];
            if ($field['type'] === 'date') {
                // Include the entire end date without applying a function to the column.
                $end = ($field['operator'] === '<=');
                $query->where($column, $end ? '<' : '>=', $end ? \Carbon\Carbon::parse($data[$key])->addDay()->toDateString() : $data[$key]);
            } elseif ($field['type'] === 'number' && !$model) {
                $wrapped = $query->getGrammar()->wrap($column);
                $operator = $field['operator'] ?? '=';
                $query->whereRaw("$wrapped $operator CAST(? AS DECIMAL(20, 6))", [$data[$key]]);
            } elseif ($field['type'] === 'number' || $field['type'] === 'boolean') {
                $query->where($column, $field['operator'] ?? '=', $field['type'] === 'boolean' ? (int) $data[$key] : (float) $data[$key]);
            } else {
                $value = trim($data[$key]);
                $pattern = strtr(mb_strtolower($value), ['!' => '!!', '%' => '!%', '_' => '!_', '*' => '%', '?' => '_']);
                if (!str_contains($value, '*') && !str_contains($value, '?')) $pattern = '%'.$pattern.'%';
                $wrapped = $query->getGrammar()->wrap($column);
                $query->where(function ($nested) use ($wrapped, $pattern, $field) {
                    $nested->whereRaw("LOWER($wrapped) LIKE ? ESCAPE '!'", [$pattern]);
                    foreach ($field['labels'] ?? [] as $stored => $label) {
                        $nested->orWhere(function ($mapped) use ($wrapped, $stored, $label, $pattern) {
                            $mapped->whereRaw("$wrapped = ?", [$stored])
                                ->whereRaw("LOWER(?) LIKE ? ESCAPE '!'", [$label, $pattern]);
                        });
                    }
                });
            }
        }
        if (!empty($data[$this->parameter('sort')])) {
            $sortField = $data[$this->parameter('sort')];
            $sortDirection = $data[$this->parameter('direction')] ?? 'asc';
            $expression = $this->fields()[$sortField]['sort_sql'] ?? null;
            if ($expression) $query->reorder()->orderByRaw($expression.' '.$sortDirection);
            else $query->reorder($table.'.'.$sortField, $sortDirection);
            if ($model) $query->orderBy($table.'.'.$model->getKeyName());
        }
    }
}
