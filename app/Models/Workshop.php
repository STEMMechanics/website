<?php

namespace App\Models;

use App\Helpers;
use App\Traits\HasFiles;
use App\Traits\Slug;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Workshop extends Model
{
    use HasFactory, HasFiles, Slug;

    protected $fillable = [
        'title',
        'content',
        'summary',
        'starts_at',
        'ends_at',
        'publish_at',
        'closes_at',
        'status',
        'price',
        'early_bird_price',
        'early_bird_ends_at',
        'early_bird_ticket_limit',
        'ages',
        'registration',
        'registration_data',
        'private_code',
        'hosted_for',
        'is_private',
        'is_hidden',
        'max_tickets',
        'ticket_group_slug',
        'pick_list_template_id',
        'pick_list_participants',
        'pick_list_checked_item_ids',
        'pick_list_custom_items',
        'pick_list_is_customized',
        'pick_list_notes',
        'pick_list_canvas_data',
        'pick_list_canvas_thumbnail_path',
        'location_id',
        'user_id',
        'hero_media_name',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'publish_at' => 'datetime',
        'closes_at' => 'datetime',
        'early_bird_ends_at' => 'datetime',
        'early_bird_price' => 'decimal:2',
        'is_private' => 'boolean',
        'is_hidden' => 'boolean',
        'max_tickets' => 'integer',
        'early_bird_ticket_limit' => 'integer',
        'pick_list_participants' => 'integer',
        'pick_list_checked_item_ids' => 'array',
        'pick_list_custom_items' => 'array',
        'pick_list_is_customized' => 'boolean',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function hero(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'hero_media_name');
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    /**
     * @return BelongsTo<PickListTemplate, $this>
     */
    public function pickListTemplate(): BelongsTo
    {
        return $this->belongsTo(PickListTemplate::class, 'pick_list_template_id');
    }

    /**
     * @return BelongsToMany<WorkshopCategory, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(WorkshopCategory::class, 'workshop_category_workshop')
            ->withTimestamps()
            ->orderBy('name');
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * @return HasMany<WorkshopAttendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(WorkshopAttendance::class);
    }

    /**
     * @return HasMany<WorkshopInterest, $this>
     */
    public function interests(): HasMany
    {
        return $this->hasMany(WorkshopInterest::class);
    }

    public function getLocationName(): string
    {
        $locationId = trim((string) ($this->location_id ?? ''));

        if ($locationId === '') {
            return 'Online';
        }

        return trim((string) ($this->location->name ?? '')) ?: '-';
    }

    public function getLocationDisplay(bool $includeAddress = true): string
    {
        $locationName = $this->getLocationName();
        if (! $includeAddress || $locationName === 'Online') {
            return $locationName;
        }

        $address = trim((string) ($this->location->address ?? ''));
        if ($address === '') {
            return $locationName;
        }

        if ($locationName === '-' || $locationName === '') {
            return $address;
        }

        return $locationName.' - '.$address;
    }

    public function getPublicLocationLabel(): string
    {
        if (! $this->isPrivate()) {
            return $this->getLocationName();
        }

        $hostedFor = trim((string) ($this->hosted_for ?? ''));

        return $hostedFor !== '' ? $hostedFor : 'Private Location';
    }

    public function newsletterSummary(int $limit = 180): string
    {
        $summary = trim((string) ($this->summary ?? ''));
        if ($summary !== '') {
            return Str::limit((string) Str::of($summary)->squish(), $limit);
        }

        $content = trim((string) ($this->content ?? ''));
        if ($content === '') {
            return '';
        }

        $content = trim((string) Str::of(strip_tags($content))->squish());

        return Str::limit($content, $limit);
    }

    public function baseTicketPriceAmount(): float
    {
        $raw = trim((string) ($this->price ?? ''));
        if ($raw === '') {
            return 0.0;
        }

        $normalized = strtolower($raw);
        if (in_array($normalized, ['free', 'tbd', 'tbc'], true)) {
            return 0.0;
        }

        $number = preg_replace('/[^0-9.]/', '', $raw);
        if (! is_string($number) || $number === '' || ! is_numeric($number)) {
            return 0.0;
        }

        return max(0, round((float) $number, 2));
    }

    public function earlyBirdPriceAmount(): ?float
    {
        if ($this->early_bird_price === null || trim((string) $this->early_bird_price) === '') {
            return null;
        }

        return max(0, round((float) $this->early_bird_price, 2));
    }

    public function earlyBirdTicketLimit(): ?int
    {
        $limit = $this->early_bird_ticket_limit;

        return is_numeric($limit) && (int) $limit > 0 ? (int) $limit : null;
    }

    public function activeTicketCount(): int
    {
        $count = $this->getAttribute('active_tickets_count');
        if (is_numeric($count)) {
            return max(0, (int) $count);
        }

        if ($this->relationLoaded('tickets')) {
            return (int) $this->tickets
                ->filter(fn (Ticket $ticket): bool => in_array((int) $ticket->status, Ticket::activePurchasedStatuses(), true))
                ->count();
        }

        return (int) $this->tickets()
            ->whereIn('status', Ticket::activePurchasedStatuses())
            ->count();
    }

    public function earlyBirdTicketLimitRemaining(): ?int
    {
        $limit = $this->earlyBirdTicketLimit();
        if ($limit === null) {
            return null;
        }

        $holdMinutes = 10;
        try {
            $configuredHoldMinutes = SiteOption::value('tickets.hold-minutes', '10');
            if (is_numeric($configuredHoldMinutes)) {
                $holdMinutes = (int) $configuredHoldMinutes;
            }
        } catch (\Throwable) {
            $holdMinutes = 10;
        }
        $holdMinutes = max(1, min(240, $holdMinutes));
        $threshold = now()->subMinutes($holdMinutes);
        try {
            $reserved = Ticket::query()
                ->where('workshop_id', $this->id)
                ->where('is_early_bird', true)
                ->where(function ($builder) use ($threshold) {
                    $builder->whereIn('status', Ticket::activePurchasedStatuses())
                        ->orWhere(function ($holdQuery) use ($threshold) {
                            $holdQuery->where('status', Ticket::STATUS_HOLD)
                                ->where('created_at', '>=', $threshold);
                        });
                })
                ->count();
        } catch (\Throwable) {
            return max(0, $limit - $this->activeTicketCount());
        }

        return max(0, $limit - $reserved);
    }

    public function hasEarlyBirdOffer(): bool
    {
        return $this->earlyBirdPriceAmount() !== null
            || $this->earlyBirdTicketLimit() !== null
            || $this->early_bird_ends_at !== null;
    }

    public function earlyBirdIsActive(): bool
    {
        if (! $this->hasEarlyBirdOffer()) {
            return false;
        }

        if ($this->early_bird_ends_at !== null && Carbon::parse($this->early_bird_ends_at)->isPast()) {
            return false;
        }

        $remaining = $this->earlyBirdTicketLimitRemaining();
        if ($remaining !== null && $remaining <= 0) {
            return false;
        }

        return true;
    }

    public function currentTicketPriceAmount(): float
    {
        $earlyBirdPrice = $this->earlyBirdPriceAmount();
        if ($earlyBirdPrice !== null && $this->earlyBirdIsActive()) {
            return $earlyBirdPrice;
        }

        return $this->baseTicketPriceAmount();
    }

    /**
     * @return array{
     *     ticketPriceAmount: float,
     *     nonDiscountAmount: float,
     *     earlyBirdSummary: ?string,
     *     earlyBirdPlacesRemaining: ?int
     * }
     */
    public function ticketPricing(): array
    {
        $ticketPriceAmount = $this->currentTicketPriceAmount();
        $nonDiscountAmount = ($this->earlyBirdPriceAmount() !== null && $this->earlyBirdIsActive())
            ? $this->baseTicketPriceAmount()
            : $ticketPriceAmount;

        return [
            'ticketPriceAmount' => $ticketPriceAmount,
            'nonDiscountAmount' => $nonDiscountAmount,
            'earlyBirdSummary' => $this->earlyBirdSummaryLabel(),
            'earlyBirdStatus' => $this->earlyBirdStatusLabel(),
            'earlyBirdPlacesRemaining' => $this->earlyBirdTicketLimitRemaining(),
        ];
    }

    /**
     * Build the standard invoice line notes for a ticket on this workshop.
     *
     * @param  array<int, string>  $extraLines
     */
    public function ticketInvoiceLineNotes(?Ticket $ticket = null, array $extraLines = []): string
    {
        $lines = [
            'Workshop date/time: '.$this->getTicketTimeRangeLabel(),
            'Workshop location: '.((string) $this->getLocationName()),
        ];

        if ($ticket instanceof Ticket && $ticket->isEarlyBirdTicket()) {
            $lines[] = 'Early Bird ticket.';
        }

        foreach ($extraLines as $line) {
            $value = trim((string) $line);
            if ($value !== '') {
                $lines[] = $value;
            }
        }

        return trim(implode("\n", $lines));
    }

    public function earlyBirdCutoffLabel(): ?string
    {
        if (! $this->hasEarlyBirdOffer()) {
            return null;
        }

        $parts = [];

        if ($this->early_bird_ends_at !== null) {
            $parts[] = 'ends '.Carbon::parse($this->early_bird_ends_at)->format('j M Y g:ia');
        }

        $limit = $this->earlyBirdTicketLimit();
        if ($limit !== null) {
            $parts[] = 'first '.$limit.' tickets';
        }

        if ($parts === []) {
            return null;
        }

        return implode(' or ', $parts);
    }

    public function earlyBirdSummaryLabel(): ?string
    {
        if (! $this->earlyBirdIsActive()) {
            return null;
        }

        $savings = max(0, round($this->baseTicketPriceAmount() - $this->currentTicketPriceAmount(), 2));
        if ($savings <= 0.0001) {
            return null;
        }

        $summary = 'Save $'.number_format($savings, 2).' with earlybird pricing';
        if ($this->early_bird_ends_at !== null) {
            $summary .= '. Ends '.Carbon::parse($this->early_bird_ends_at)->format('d M');
        }
        if ($this->earlyBirdTicketLimit() !== null) {
            $summary .= '. Limited tickets available';
        }

        return $summary;
    }

    public function earlyBirdStatusLabel(): ?string
    {
        if (! $this->hasEarlyBirdOffer()) {
            return null;
        }

        $summary = $this->earlyBirdSummaryLabel();
        if ($summary !== null) {
            return $summary;
        }

        if ($this->early_bird_ends_at !== null && Carbon::parse($this->early_bird_ends_at)->isPast()) {
            return 'Early bird ended '.Carbon::parse($this->early_bird_ends_at)->format('d M');
        }

        return null;
    }

    public function getTicketTimeRangeLabel(): string
    {
        if ($this->usesClassroomRegistration()) {
            if ($this->effectiveScheduleEntries() === []) {
                return 'Anytime';
            }

            $start = $this->effectiveStartsAt();
            $end = $this->effectiveEndsAt();

            if (! $start || ! $end) {
                return 'Anytime';
            }

            return Helpers::createTicketTimeDurationStr(
                $start->toDateTimeString(),
                $end->toDateTimeString()
            );
        }

        if (! $this->starts_at || ! $this->ends_at) {
            return $this->starts_at?->format('M j, Y g:i a') ?? '-';
        }

        return Helpers::createTicketTimeDurationStr(
            $this->starts_at->toDateTimeString(),
            $this->ends_at->toDateTimeString()
        );
    }

    public function requiresPrivateTicketCode(): bool
    {
        return $this->registration === 'tickets'
            && $this->isPrivate()
            && trim((string) ($this->private_code ?? '')) !== '';
    }

    public function requiresPrivateAccessCode(): bool
    {
        return $this->isPrivate()
            && trim((string) ($this->private_code ?? '')) !== '';
    }

    public function usesClassroomRegistration(): bool
    {
        return false;
    }

    /**
     * @return array<int, array{starts_at: ?string, ends_at: ?string, label: string}>
     */
    public function effectiveScheduleEntries(): array
    {
        return [];
    }

    public function effectiveStartsAt(): ?CarbonInterface
    {
        return $this->starts_at;
    }

    public function effectiveEndsAt(): ?CarbonInterface
    {
        return $this->ends_at;
    }

    public function courseScheduleFirstStartLabel(): string
    {
        if ($this->usesClassroomRegistration() && $this->effectiveScheduleEntries() === []) {
            return 'Anytime';
        }

        $start = $this->effectiveStartsAt();
        if (! $start) {
            return 'Anytime';
        }

        return $start->format('j/m/Y @ g:i a');
    }

    public function courseScheduleCadenceLabel(): ?string
    {
        return null;
    }

    public function workshopDurationLabel(): ?string
    {
        if ($this->usesClassroomRegistration()) {
            return null;
        }

        $start = $this->starts_at;
        $end = $this->ends_at;

        if (! $start || ! $end) {
            return null;
        }

        $minutes = max(0, (int) $start->diffInMinutes($end));
        if ($minutes === 0) {
            return null;
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;
        $parts = [];

        if ($hours > 0) {
            $parts[] = $hours.' hour'.($hours === 1 ? '' : 's');
        }

        if ($remainingMinutes > 0) {
            $parts[] = $remainingMinutes.' minute'.($remainingMinutes === 1 ? '' : 's');
        }

        return implode(' ', $parts);
    }

    /**
     * @return array<int, string>
     */
    public function courseScheduleDisplayLines(): array
    {
        if (! $this->starts_at || ! $this->ends_at) {
            return ['Anytime'];
        }

        return [
            $this->starts_at->format('D j M Y g:ia').' - '.$this->ends_at->format('g:ia'),
        ];
    }

    public function isPrivate(): bool
    {
        return (bool) ($this->is_private ?? false) || $this->status === 'private';
    }

    public function publicStatus(): string
    {
        if ((bool) ($this->is_hidden ?? false)) {
            return 'hidden';
        }

        if ($this->isPrivate() && $this->status === 'open') {
            return 'private';
        }

        return (string) $this->status;
    }

    public function publicStatusLabel(): string
    {
        $status = $this->publicStatus();

        if ($status === 'scheduled') {
            return 'Opens Soon';
        }

        return ucwords($status);
    }

    public function adminStatusLabel(): string
    {
        if ($this->isPrivate() && $this->status === 'open') {
            return 'Private';
        }

        if ($this->status === 'scheduled') {
            return 'Opens Soon';
        }

        return ucwords((string) $this->status);
    }

    public function calendarStatusLabel(): string
    {
        return match ($this->publicStatus()) {
            'scheduled' => 'Soon',
            'cancelled' => 'Canc.',
            'private' => 'Priv.',
            'hidden' => 'Hid.',
            default => $this->publicStatusLabel(),
        };
    }

    public function isPubliclyVisible(): bool
    {
        if ($this->status === 'draft') {
            return false;
        }

        if ((bool) ($this->is_hidden ?? false)) {
            return true;
        }

        if ($this->publish_at === null) {
            return true;
        }

        return $this->publish_at->lte(now());
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query
            ->where('status', '!=', 'draft')
            ->where(function (Builder $builder) {
                $builder->whereNull('is_hidden')
                    ->orWhere('is_hidden', false);
            })
            ->where(function (Builder $builder) {
                $builder->whereNull('publish_at')
                    ->orWhere('publish_at', '<=', now());
            });
    }

    public function matchesPrivateAccessCode(?string $code): bool
    {
        if (! $this->requiresPrivateAccessCode()) {
            return true;
        }

        $actual = strtolower(trim((string) ($this->private_code ?? '')));
        $provided = strtolower(trim((string) ($code ?? '')));

        return $provided !== '' && hash_equals($actual, $provided);
    }

    public function matchesPrivateTicketCode(?string $code): bool
    {
        if (! $this->requiresPrivateTicketCode()) {
            return true;
        }

        return $this->matchesPrivateAccessCode($code);
    }
}
