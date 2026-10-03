<?php

namespace App\Services;

use App\Jobs\SendEmail;
use App\Mail\InvoiceDocumentBundle;
use App\Mail\SponsorshipCancelled;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\InvoicePaymentAllocation;
use App\Models\Organisation;
use App\Models\Payment;
use App\Models\Sponsor;
use App\Models\Sponsorship;
use App\Models\SponsorshipPayment;
use App\Models\SponsorshipProject;
use App\Models\User;
use App\Services\Finance\SponsorshipTaxService;
use App\Support\InvoiceDueDate;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SponsorshipService
{
    public function __construct(
        private readonly SquareApiService $square,
        private readonly DocumentNumberService $documentNumbers,
        private readonly SponsorshipTaxService $tax
    ) {}

    public function createSponsor(array $data, ?string $userId = null): Sponsor
    {
        $email = strtolower(trim((string) $data['email']));
        $resolvedUserId = $userId;
        if ($resolvedUserId === null && $email !== '') {
            $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
            if (! $user) {
                $user = new User;
                $user->email = $email;
            }

            if ($user->email_verified_at === null) {
                $nameParts = preg_split('/\s+/', trim((string) ($data['contact_name'] ?? ''))) ?: [];
                $user->firstname = $user->firstname ?: ($nameParts[0] ?? null);
                $user->surname = $user->surname ?: (count($nameParts) > 1 ? trim(implode(' ', array_slice($nameParts, 1))) : null);
                $user->phone = $user->phone ?: $this->stringOrNull($data['phone'] ?? null);
            }
            $user->save();
            $resolvedUserId = (string) $user->id;
        }

        if ($resolvedUserId !== null) {
            $sponsor = Sponsor::query()->where('user_id', $resolvedUserId)->first()
                ?? Sponsor::query()->where('email', $email)->whereNull('user_id')->first()
                ?? new Sponsor();
        } else {
            $sponsor = Sponsor::query()->firstOrNew(['email' => $email, 'user_id' => null]);
        }

        $organisation = $this->syncSponsorOrganisationProfile($sponsor, $data, $resolvedUserId);

        $sponsor->fill([
            'user_id' => $resolvedUserId,
            'organisation_id' => $organisation?->id,
            'email' => $email,
            'contact_name' => trim((string) $data['contact_name']),
            'sponsor_type' => (string) $data['sponsor_type'],
            'company_name' => null,
            'abn' => null,
            'foreign_tax_id' => $organisation ? null : $this->stringOrNull($data['foreign_tax_id'] ?? null),
            'country' => trim((string) $data['country']),
            'non_resident_declaration' => (bool) ($data['non_resident_declaration'] ?? false),
            'billing_address' => $organisation ? null : $this->stringOrNull($data['billing_address'] ?? null),
            'billing_address2' => $organisation ? null : $this->stringOrNull($data['billing_address2'] ?? null),
            'billing_city' => $organisation ? null : $this->stringOrNull($data['billing_city'] ?? null),
            'billing_state' => $organisation ? null : $this->stringOrNull($data['billing_state'] ?? null),
            'billing_postcode' => $organisation ? null : $this->stringOrNull($data['billing_postcode'] ?? null),
        ]);
        $sponsor->save();

        return $sponsor;
    }

    public function syncSponsorOrganisationProfile(
        Sponsor $sponsor,
        array $data,
        ?string $userId = null,
        bool $updateRecognition = false,
    ): ?Organisation {
        if (($data['sponsor_type'] ?? $sponsor->sponsor_type) !== 'organisation') {
            $sponsor->organisation_id = null;
            $sponsor->unsetRelation('organisation');

            return null;
        }

        $linkedOrganisation = $sponsor->getRelationValue('organisation');
        $linkedOrganisationName = $linkedOrganisation instanceof Organisation ? $linkedOrganisation->name : null;
        $name = preg_replace('/\s+/u', ' ', trim((string) ($data['company_name'] ?? $linkedOrganisationName ?? $sponsor->getRawOriginal('company_name') ?? ''))) ?: '';
        if ($name === '') return null;

        $organisation = $linkedOrganisation instanceof Organisation
            ? $linkedOrganisation
            : Organisation::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first() ?? new Organisation();

        $values = ['name' => $name];
        if (! $organisation->exists || $organisation->type === 'other') $values['type'] = 'business';
        foreach (['billing_address', 'billing_address2', 'billing_city', 'billing_state', 'billing_postcode', 'billing_country', 'abn', 'foreign_tax_id'] as $field) {
            if (! array_key_exists($field, $data)) continue;
            $value = $this->stringOrNull($data[$field]);
            if ($value !== null || ! $organisation->exists) $values[$field] = $value;
        }
        if (array_key_exists('country', $data)) {
            $country = $this->stringOrNull($data['country']);
            if ($country !== null || ! $organisation->exists) $values['billing_country'] = $country;
        }
        if ($updateRecognition) {
            $values['sponsorship_recognition_public'] = (bool) ($data['recognition_public'] ?? false);
            $values['website_url'] = $this->stringOrNull($data['website_url'] ?? null);
        } elseif (! empty($data['website_url'])) {
            $values['website_url'] = $this->stringOrNull($data['website_url']);
        }

        $organisation->fill($values)->save();
        $sponsor->organisation_id = $organisation->id;
        $sponsor->unsetRelation('organisation');

        $contactId = $userId ?: $sponsor->user_id;
        if ($contactId) {
            $organisation->contacts()->syncWithoutDetaching([$contactId]);
        }

        return $organisation;
    }

    public function createOneTime(Sponsorship $sponsorship, string $sourceId): array
    {
        $this->ensureSquareCustomer($sponsorship->sponsor);
        $response = $this->square->createPayment([
            'idempotency_key' => (string) Str::uuid(),
            'source_id' => $sourceId,
            'customer_id' => $sponsorship->sponsor->square_customer_id,
            'location_id' => (string) config('services.square.location_id'),
            'amount_money' => [
                'amount' => (int) round((float) $sponsorship->amount * 100),
                'currency' => $sponsorship->currency,
            ],
            'reference_id' => 'sponsorship:'.$sponsorship->id,
            'note' => $sponsorship->project->is_primary ? 'STEMMechanics sponsorship' : $sponsorship->project->name.' project sponsorship',
        ]);

        $payment = $response['payment'] ?? [];
        if (is_array($payment) && strtoupper((string) ($payment['status'] ?? '')) === 'COMPLETED') {
            try {
                $this->recordPayment($sponsorship, $payment);
            } catch (Throwable $exception) {
                Log::error('Square captured a sponsorship payment, but local invoice processing failed.', [
                    'sponsorship_id' => $sponsorship->id,
                    'square_payment_id' => (string) ($payment['id'] ?? ''),
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $response;
    }

    public function createMonthly(Sponsorship $sponsorship, string $sourceId): array
    {
        $sponsorship->loadMissing(['sponsor', 'project']);
        $sponsor = $sponsorship->sponsor;
        $this->ensureSquareCustomer($sponsor);
        $card = $this->square->createCard([
            'idempotency_key' => (string) Str::uuid(),
            'source_id' => $sourceId,
            'card' => array_filter([
                'customer_id' => $sponsor->square_customer_id,
                'cardholder_name' => $sponsor->contact_name,
                'billing_address' => $this->squareCardBillingAddress($sponsor),
            ], static fn ($value) => $value !== null && $value !== []),
        ]);
        $cardId = trim((string) data_get($card, 'card.id', ''));
        if ($cardId === '') {
            throw new \RuntimeException('Square did not return a card reference.');
        }

        $sponsor->square_card_id = $cardId;
        $sponsor->save();

        $schedule = app(SponsorshipBillingScheduleService::class);
        $sponsorship->started_at = $sponsorship->started_at ?? now(SponsorshipBillingScheduleService::TIMEZONE);
        $anchorDate = CarbonImmutable::parse($sponsorship->started_at, SponsorshipBillingScheduleService::TIMEZONE)->toDateString();
        $initialPayment = $schedule->initialPayment(
            (float) $sponsorship->amount,
            CarbonImmutable::parse($anchorDate, SponsorshipBillingScheduleService::TIMEZONE),
        );
        $sponsorship->local_recurring_billing = true;
        $sponsorship->billing_anchor_date = Carbon::parse((string) $initialPayment['billing_anchor_date'], SponsorshipBillingScheduleService::TIMEZONE);
        $sponsorship->next_payment_date = Carbon::parse((string) $initialPayment['next_payment_date'], SponsorshipBillingScheduleService::TIMEZONE);
        $sponsorship->save();

        $attempt = $this->createBillingAttempt(
            $sponsorship,
            $initialPayment['billing_period'],
            1,
            $initialPayment['amount_cents'],
        );

        try {
            $response = $this->chargeSavedCard($sponsorship, $attempt);
        } catch (ConnectionException $exception) {
            // Keep the pending attempt and its idempotency key. A later retry can
            // safely ask Square for the same result without charging twice.
            throw $exception;
        } catch (Throwable $exception) {
            $this->markBillingAttemptFailed($sponsorship, $attempt, $exception);
            throw $exception;
        }

        $payment = $response['payment'] ?? [];
        if (is_array($payment)) {
            $this->recordPayment($sponsorship, $payment, billingAttempt: $attempt);
        }

        if (strtoupper((string) ($payment['status'] ?? '')) === 'FAILED') {
            throw new \RuntimeException('Square could not complete the first monthly sponsorship payment.');
        }

        return $response;
    }

    /** Process pending and due app-managed monthly card charges and invoices. */
    public function processDueMonthlyPayments(): array
    {
        $today = CarbonImmutable::now(SponsorshipBillingScheduleService::TIMEZONE)->toDateString();
        $dueSponsorshipIds = Sponsorship::query()
            ->where('frequency', 'monthly')
            ->where('local_recurring_billing', true)
            ->whereIn('status', [Sponsorship::STATUS_ACTIVE, Sponsorship::STATUS_PAST_DUE])
            ->whereNotNull('next_payment_date')
            ->whereDate('next_payment_date', '<=', $today)
            ->whereDoesntHave('payments', fn ($query) => $query
                ->whereNotNull('billing_period')
                ->where('status', SponsorshipPayment::STATUS_PENDING))
            ->orderBy('id')
            ->pluck('id');

        $results = $this->processMonthlySponsorshipIds($dueSponsorshipIds);
        $invoiceResults = $this->processDueMonthlyInvoiceBilling();
        foreach (['processed', 'failed', 'skipped'] as $key) {
            $results[$key] = ($results[$key] ?? 0) + ($invoiceResults[$key] ?? 0);
        }

        return $results;
    }

    /** Issue and email recurring business invoices, retrying any invoice email that was not queued. */
    private function processDueMonthlyInvoiceBilling(): array
    {
        $results = ['processed' => 0, 'failed' => 0, 'skipped' => 0];
        $eligible = fn ($query) => $query
            ->where('frequency', 'monthly')
            ->where('billing_method', 'invoice')
            ->whereIn('status', [Sponsorship::STATUS_PENDING, Sponsorship::STATUS_ACTIVE, Sponsorship::STATUS_PAST_DUE]);

        $unsentInvoices = SponsorshipPayment::query()
            ->where('status', SponsorshipPayment::STATUS_PENDING)
            ->whereNotNull('invoice_id')
            ->whereNull('emailed_at')
            ->whereHas('sponsorship', $eligible)
            ->whereHas('invoice', fn ($query) => $query->whereNotIn('status', [
                Invoice::STATUS_CANCELLED,
                Invoice::STATUS_PAID,
                Invoice::STATUS_WRITTEN_OFF,
            ]))
            ->with(['invoice', 'sponsorship.sponsor'])
            ->orderBy('id')
            ->get();

        foreach ($unsentInvoices as $payment) {
            try {
                if ($this->emailUnpaidInvoice($payment)) {
                    $results['processed']++;
                } else {
                    $results['failed']++;
                }
            } catch (Throwable $exception) {
                $results['failed']++;
                report($exception);
            }
        }

        $today = CarbonImmutable::now(SponsorshipBillingScheduleService::TIMEZONE)->toDateString();
        $dueSponsorshipIds = Sponsorship::query()
            ->where('frequency', 'monthly')
            ->where('billing_method', 'invoice')
            ->whereIn('status', [Sponsorship::STATUS_ACTIVE, Sponsorship::STATUS_PAST_DUE])
            ->whereNotNull('next_payment_date')
            ->whereDate('next_payment_date', '<=', $today)
            ->whereDoesntHave('payments', fn ($query) => $query->where('status', SponsorshipPayment::STATUS_PENDING))
            ->orderBy('id')
            ->pluck('id');

        foreach ($dueSponsorshipIds as $sponsorshipId) {
            try {
                $payment = DB::transaction(function () use ($sponsorshipId, $today): ?SponsorshipPayment {
                    $sponsorship = Sponsorship::query()->with(['sponsor', 'project'])->lockForUpdate()->find($sponsorshipId);
                    if (! $sponsorship || ! $sponsorship->isRecurring()
                        || $sponsorship->billing_method !== 'invoice'
                        || ! in_array($sponsorship->status, [Sponsorship::STATUS_ACTIVE, Sponsorship::STATUS_PAST_DUE], true)
                        || ! $sponsorship->next_payment_date
                        || $sponsorship->next_payment_date->toDateString() > $today
                        || $sponsorship->payments()->where('status', SponsorshipPayment::STATUS_PENDING)->exists()
                        || $sponsorship->payments()
                            ->whereDate('billing_period', $sponsorship->next_payment_date->toDateString())
                            ->exists()) {
                        return null;
                    }

                    return $this->createUnpaidInvoice(
                        $sponsorship,
                        $sponsorship->next_payment_date->toDateString(),
                    );
                }, 3);

                if (! $payment) {
                    $results['skipped']++;
                    continue;
                }

                if ($this->emailUnpaidInvoice($payment)) {
                    $results['processed']++;
                } else {
                    $results['failed']++;
                }
            } catch (Throwable $exception) {
                $results['failed']++;
                report($exception);
            }
        }

        return $results;
    }

    public function emailUnpaidInvoice(SponsorshipPayment $payment): bool
    {
        return DB::transaction(function () use ($payment): bool {
            $lockedPayment = SponsorshipPayment::query()
                ->with(['invoice.user', 'invoice.lines', 'invoice.allocations.customerPayment', 'invoice.storeOrders', 'sponsorship.sponsor'])
                ->lockForUpdate()
                ->find($payment->id);
            if (! $lockedPayment || $lockedPayment->status !== SponsorshipPayment::STATUS_PENDING || ! $lockedPayment->invoice) {
                return false;
            }
            if ($lockedPayment->emailed_at !== null) return true;

            $sponsor = $lockedPayment->sponsorship?->sponsor;
            if (! $sponsor) return false;

            $sent = app(StoreOrderService::class)->sendInvoiceDocumentBundleToCustomer(
                $lockedPayment->invoice,
                (string) $lockedPayment->invoice->billing_email,
                (string) $lockedPayment->invoice->billing_name,
            );
            if ($sent) {
                $lockedPayment->emailed_at = now();
                $lockedPayment->save();
            }

            return $sent;
        }, 3);
    }

    /** Reconcile only outstanding monthly payments and abandoned initial checkouts. */
    public function reconcilePendingMonthlyPayments(?int $sponsorId = null): array
    {
        $pendingSponsorshipIds = SponsorshipPayment::query()
            ->where('status', SponsorshipPayment::STATUS_PENDING)
            ->whereNotNull('billing_period')
            ->where(fn ($query) => $query
                ->whereNotNull('square_payment_id')
                ->orWhere('created_at', '<=', now()->subMinutes(5)))
            ->whereHas('sponsorship', fn ($query) => $query
                ->where('frequency', 'monthly')
                ->where('local_recurring_billing', true)
                ->whereIn('status', [Sponsorship::STATUS_PENDING, Sponsorship::STATUS_ACTIVE, Sponsorship::STATUS_PAST_DUE])
                ->when($sponsorId !== null, fn ($query) => $query->where('sponsor_id', $sponsorId)))
            ->distinct()
            ->pluck('sponsorship_id');

        $results = $this->processMonthlySponsorshipIds($pendingSponsorshipIds);

        $incompleteInitialIds = Sponsorship::query()
            ->where('frequency', 'monthly')
            ->where('status', Sponsorship::STATUS_PENDING)
            ->where('local_recurring_billing', false)
            ->where('created_at', '<=', now()->subMinutes(5))
            ->whereDoesntHave('payments')
            ->whereHas('sponsor', function ($query) use ($sponsorId): void {
                $query->whereNotNull('square_customer_id')->whereNotNull('square_card_id');
                if ($sponsorId !== null) $query->whereKey($sponsorId);
            })
            ->orderBy('id')
            ->pluck('id');

        foreach ($incompleteInitialIds as $sponsorshipId) {
            try {
                $result = $this->resumePendingInitialMonthlyPayment((int) $sponsorshipId);
                $results[$result] = ($results[$result] ?? 0) + 1;
            } catch (Throwable $exception) {
                $results['failed']++;
                report($exception);
            }
        }

        return $results;
    }

    private function resumePendingInitialMonthlyPayment(int $sponsorshipId): string
    {
        $now = CarbonImmutable::now(SponsorshipBillingScheduleService::TIMEZONE);
        $attempt = DB::transaction(function () use ($sponsorshipId, $now): ?SponsorshipPayment {
            $sponsorship = Sponsorship::query()->with('sponsor')->lockForUpdate()->find($sponsorshipId);
            if (! $sponsorship || ! $sponsorship->isRecurring()
                || $sponsorship->status !== Sponsorship::STATUS_PENDING
                || $sponsorship->local_recurring_billing
                || $sponsorship->created_at?->gt(now()->subMinutes(5))
                || $sponsorship->payments()->exists()
                || blank($sponsorship->sponsor->square_customer_id)
                || blank($sponsorship->sponsor->square_card_id)) {
                return null;
            }

            $initialPayment = app(SponsorshipBillingScheduleService::class)->initialPayment(
                (float) $sponsorship->amount,
                $now,
            );
            $sponsorship->started_at = $now->toMutable();
            $sponsorship->local_recurring_billing = true;
            $sponsorship->billing_anchor_date = Carbon::parse((string) $initialPayment['billing_anchor_date'], SponsorshipBillingScheduleService::TIMEZONE);
            $sponsorship->next_payment_date = Carbon::parse((string) $initialPayment['next_payment_date'], SponsorshipBillingScheduleService::TIMEZONE);
            $sponsorship->save();

            return $this->createBillingAttempt(
                $sponsorship,
                $initialPayment['billing_period'],
                1,
                $initialPayment['amount_cents'],
            );
        }, 3);

        if (! $attempt) return 'skipped';

        $sponsorship = $attempt->sponsorship()->with(['sponsor', 'project'])->firstOrFail();

        return $this->chargeMonthlyAttempt($sponsorship, $attempt);
    }

    private function processMonthlySponsorshipIds(iterable $sponsorshipIds): array
    {
        $results = ['processed' => 0, 'failed' => 0, 'skipped' => 0];
        foreach ($sponsorshipIds as $sponsorshipId) {
            try {
                $result = $this->processMonthlySponsorshipPayment((int) $sponsorshipId);
                $results[$result] = ($results[$result] ?? 0) + 1;
            } catch (Throwable $exception) {
                $results['failed']++;
                report($exception);
            }
        }

        return $results;
    }

    private function processMonthlySponsorshipPayment(int $sponsorshipId): string
    {
        $now = CarbonImmutable::now(SponsorshipBillingScheduleService::TIMEZONE);
        $attempt = DB::transaction(function () use ($sponsorshipId, $now): ?SponsorshipPayment {
            $sponsorship = Sponsorship::query()->with('sponsor')->lockForUpdate()->find($sponsorshipId);
            if (! $sponsorship || ! $sponsorship->isRecurring() || ! $sponsorship->local_recurring_billing
                || ! in_array($sponsorship->status, [Sponsorship::STATUS_PENDING, Sponsorship::STATUS_ACTIVE, Sponsorship::STATUS_PAST_DUE], true)) {
                return null;
            }

            $latestPending = $sponsorship->payments()
                ->whereNotNull('billing_period')
                ->where('status', SponsorshipPayment::STATUS_PENDING)
                ->orderByDesc('attempt_number')
                ->first();
            if ($latestPending) {
                if (filled($latestPending->square_payment_id)) return $latestPending;
                if ($latestPending->created_at?->gt(now()->subMinutes(5))) return null;

                // The original request may have reached Square even though its
                // response was lost. Reuse the same idempotency key to recover
                // that result without creating a second charge.
                return $latestPending;
            }

            $billingDate = $sponsorship->next_payment_date?->toDateString();
            if (! $billingDate || $billingDate > $now->toDateString()) return null;

            $attempts = $sponsorship->payments()
                ->whereDate('billing_period', $billingDate)
                ->orderByDesc('attempt_number');
            $lastAttempt = (clone $attempts)->first();
            if ($lastAttempt && $lastAttempt->created_at?->gt(now()->subHours(23))) return null;
            $attemptNumber = ((int) (clone $attempts)->max('attempt_number')) + 1;
            if ($attemptNumber > 3) {
                $sponsorship->status = Sponsorship::STATUS_FAILED;
                $sponsorship->next_payment_date = null;
                $sponsorship->save();

                return null;
            }

            if (blank($sponsorship->sponsor->square_card_id) || blank($sponsorship->sponsor->square_customer_id)) {
                $sponsorship->status = Sponsorship::STATUS_FAILED;
                $sponsorship->next_payment_date = null;
                $sponsorship->save();

                return null;
            }

            return $this->createBillingAttempt(
                $sponsorship,
                $billingDate,
                $attemptNumber,
                (int) round((float) $sponsorship->amount * 100),
            );
        }, 3);

        if (! $attempt) return 'skipped';

        $sponsorship = $attempt->sponsorship()->with(['sponsor', 'project'])->firstOrFail();
        if (filled($attempt->square_payment_id)) {
            try {
                $payment = $this->square->retrievePayment((string) $attempt->square_payment_id)['payment'] ?? [];
            } catch (Throwable $exception) {
                Log::warning('Could not reconcile a pending monthly sponsorship payment with Square.', [
                    'sponsorship_id' => $sponsorship->id,
                    'sponsorship_payment_id' => $attempt->id,
                    'square_payment_id' => $attempt->square_payment_id,
                    'error' => $exception->getMessage(),
                ]);
                return 'skipped';
            }
            if (! is_array($payment) || $payment === []) return 'skipped';

            $this->recordPayment($sponsorship, $payment, billingAttempt: $attempt);
            $paymentStatus = strtoupper((string) ($payment['status'] ?? ''));
            if ($paymentStatus === 'COMPLETED') return 'processed';
            if (in_array($paymentStatus, ['FAILED', 'CANCELED', 'CANCELLED'], true)) {
                $this->applyBillingRetryLimit($sponsorship, $attempt);
                return 'failed';
            }

            return 'skipped';
        }

        return $this->chargeMonthlyAttempt($sponsorship, $attempt);
    }

    private function chargeMonthlyAttempt(Sponsorship $sponsorship, SponsorshipPayment $attempt): string
    {
        if (blank($sponsorship->sponsor->square_card_id) || blank($sponsorship->sponsor->square_customer_id)) {
            $this->markBillingAttemptFailed($sponsorship, $attempt, new \RuntimeException('A saved Square card is not available.'));
            return 'failed';
        }

        try {
            $response = $this->chargeSavedCard($sponsorship, $attempt);
        } catch (ConnectionException $exception) {
            Log::warning('Monthly sponsorship charge response was not received; the same Square idempotency key will be retried.', [
                'sponsorship_id' => $sponsorship->id,
                'sponsorship_payment_id' => $attempt->id,
                'error' => $exception->getMessage(),
            ]);
            return 'skipped';
        } catch (Throwable $exception) {
            $this->markBillingAttemptFailed($sponsorship, $attempt, $exception);
            return 'failed';
        }

        $payment = $response['payment'] ?? [];
        if (! is_array($payment)) {
            throw new \RuntimeException('Square did not return a payment for the monthly sponsorship charge.');
        }

        $this->recordPayment($sponsorship, $payment, billingAttempt: $attempt);
        if (in_array(strtoupper((string) ($payment['status'] ?? '')), ['FAILED', 'CANCELED', 'CANCELLED'], true)) {
            $this->applyBillingRetryLimit($sponsorship, $attempt);
            return 'failed';
        }

        return strtoupper((string) ($payment['status'] ?? '')) === 'COMPLETED' ? 'processed' : 'skipped';
    }

    private function createBillingAttempt(
        Sponsorship $sponsorship,
        string $billingPeriod,
        int $attemptNumber,
        int $amountCents,
    ): SponsorshipPayment {
        $sponsorship->loadMissing('sponsor');
        $gross = max(1, $amountCents) / 100;
        $breakdown = $this->tax->breakdown(
            $gross,
            (string) $sponsorship->sponsor->country,
            (bool) $sponsorship->sponsor->non_resident_declaration,
        );

        return SponsorshipPayment::query()->create([
            'sponsorship_id' => $sponsorship->id,
            'status' => SponsorshipPayment::STATUS_PENDING,
            'subtotal' => $breakdown['subtotal'],
            'gst_amount' => $breakdown['gst_amount'],
            'total_amount' => $breakdown['total'],
            'tax_rate' => $breakdown['tax_rate'],
            'tax_treatment' => $breakdown['treatment'],
            'tax_code' => $breakdown['tax_code'],
            'sponsor_country' => $sponsorship->sponsor->country,
            'billing_period' => $billingPeriod,
            'attempt_number' => $attemptNumber,
            'square_idempotency_key' => (string) Str::uuid(),
        ]);
    }

    private function chargeSavedCard(Sponsorship $sponsorship, SponsorshipPayment $attempt): array
    {
        $sponsorship->loadMissing(['sponsor', 'project']);

        return $this->square->createPayment([
            'idempotency_key' => (string) $attempt->square_idempotency_key,
            'source_id' => (string) $sponsorship->sponsor->square_card_id,
            'customer_id' => (string) $sponsorship->sponsor->square_customer_id,
            'location_id' => (string) config('services.square.location_id'),
            'amount_money' => [
                'amount' => (int) round((float) $attempt->total_amount * 100),
                'currency' => $sponsorship->currency,
            ],
            'autocomplete' => true,
            'reference_id' => 'sponsorship:'.$sponsorship->id,
            'note' => $sponsorship->project->is_primary ? 'STEMMechanics sponsorship' : $sponsorship->project->name.' project sponsorship',
        ]);
    }

    private function markBillingAttemptFailed(Sponsorship $sponsorship, SponsorshipPayment $attempt, Throwable $exception): void
    {
        $attempt->status = SponsorshipPayment::STATUS_FAILED;
        $attempt->save();
        $this->applyBillingRetryLimit($sponsorship, $attempt);
        Log::warning('Monthly sponsorship card charge failed.', [
            'sponsorship_id' => $sponsorship->id,
            'sponsorship_payment_id' => $attempt->id,
            'attempt_number' => $attempt->attempt_number,
            'error' => $exception->getMessage(),
        ]);
    }

    private function applyBillingRetryLimit(Sponsorship $sponsorship, SponsorshipPayment $attempt): void
    {
        $sponsorship->refresh();
        if ($sponsorship->status === Sponsorship::STATUS_CANCELLED) return;

        $attemptCount = $sponsorship->payments()
            ->whereDate('billing_period', $attempt->billing_period?->toDateString())
            ->whereIn('status', [SponsorshipPayment::STATUS_FAILED, SponsorshipPayment::STATUS_PENDING])
            ->count();
        if ($attemptCount >= 3) {
            $sponsorship->status = Sponsorship::STATUS_FAILED;
            $sponsorship->next_payment_date = null;
        } else {
            $sponsorship->status = Sponsorship::STATUS_PAST_DUE;
        }
        $sponsorship->save();
    }

    public function ensureSquareCustomer(Sponsor $sponsor): string
    {
        if (trim((string) $sponsor->square_customer_id) !== '') {
            return (string) $sponsor->square_customer_id;
        }

        $parts = preg_split('/\s+/', trim($sponsor->contact_name), 2) ?: [];
        $response = $this->square->createCustomer(array_filter([
            'idempotency_key' => (string) Str::uuid(),
            'given_name' => (string) ($parts[0] ?? $sponsor->contact_name),
            'family_name' => (string) ($parts[1] ?? ''),
            'company_name' => $sponsor->company_name,
            'email_address' => $sponsor->email,
            'address' => $this->squareBillingAddress($sponsor),
            'reference_id' => 'sponsor:'.$sponsor->id,
        ], static fn ($value) => $value !== null && $value !== ''));
        $customerId = trim((string) data_get($response, 'customer.id', ''));
        if ($customerId === '') {
            throw new \RuntimeException('Square did not return a customer reference.');
        }
        $sponsor->square_customer_id = $customerId;
        $sponsor->save();

        return $customerId;
    }

    private function unmatchedBillingAttempt(Sponsorship $sponsorship): ?SponsorshipPayment
    {
        return SponsorshipPayment::query()
            ->where('sponsorship_id', $sponsorship->id)
            ->whereNotNull('billing_period')
            ->whereNull('square_payment_id')
            ->whereIn('status', [SponsorshipPayment::STATUS_PENDING, SponsorshipPayment::STATUS_FAILED])
            ->orderByDesc('attempt_number')
            ->first();
    }

    public function syncPaymentEvent(array $payment): ?SponsorshipPayment
    {
        $reference = trim((string) ($payment['reference_id'] ?? ''));
        if (preg_match('/^sponsorship:(\d+)$/', $reference, $matches)) {
            $sponsorship = Sponsorship::query()->with(['sponsor', 'project'])->find((int) $matches[1]);

            return $sponsorship
                ? $this->recordPayment($sponsorship, $payment, billingAttempt: $this->unmatchedBillingAttempt($sponsorship))
                : null;
        }

        $orderId = trim((string) ($payment['order_id'] ?? ''));
        if ($orderId === '') return null;

        $existingPayment = SponsorshipPayment::query()->with(['sponsorship.sponsor', 'sponsorship.project'])
            ->where('square_order_id', $orderId)
            ->first();
        if ($existingPayment?->sponsorship) {
            return $this->recordPayment($existingPayment->sponsorship, $payment, $existingPayment->square_invoice_id);
        }

        $order = $this->square->retrieveOrder($orderId)['order'] ?? [];
        $orderReference = trim((string) ($order['reference_id'] ?? ''));
        if (preg_match('/^sponsorship:(\d+)$/', $orderReference, $matches)) {
            $sponsorship = Sponsorship::query()->with(['sponsor', 'project'])->find((int) $matches[1]);

            return $sponsorship ? $this->recordPayment($sponsorship, $payment) : null;
        }

        return null;
    }

    public function recordPayment(
        Sponsorship $sponsorship,
        array $squarePayment,
        ?string $squareInvoiceId = null,
        string $paymentMethod = Payment::PAYMENT_METHOD_CREDIT_CARD,
        ?string $gatewayProvider = 'square',
        ?string $paymentReference = null,
        bool $sendEmail = true,
        ?SponsorshipPayment $billingAttempt = null,
    ): SponsorshipPayment
    {
        $squarePaymentId = trim((string) ($squarePayment['id'] ?? '')) ?: null;
        $squareInvoiceId = $squareInvoiceId ?: null;
        $squareOrderId = trim((string) ($squarePayment['order_id'] ?? '')) ?: null;
        $status = strtoupper((string) ($squarePayment['status'] ?? ''));
        $paymentStatus = match ($status) {
            'COMPLETED' => SponsorshipPayment::STATUS_COMPLETED,
            'FAILED', 'CANCELED', 'CANCELLED' => SponsorshipPayment::STATUS_FAILED,
            default => SponsorshipPayment::STATUS_PENDING,
        };
        $existing = $billingAttempt;
        if ($squarePaymentId || $squareInvoiceId || $squareOrderId) {
            $matched = SponsorshipPayment::query()
                ->where(function ($inner) use ($squarePaymentId, $squareInvoiceId, $squareOrderId): void {
                    if ($squarePaymentId) $inner->where('square_payment_id', $squarePaymentId);
                    if ($squareInvoiceId) {
                        $squarePaymentId ? $inner->orWhere('square_invoice_id', $squareInvoiceId) : $inner->where('square_invoice_id', $squareInvoiceId);
                    }
                    if ($squareOrderId) {
                        ($squarePaymentId || $squareInvoiceId)
                            ? $inner->orWhere('square_order_id', $squareOrderId)
                            : $inner->where('square_order_id', $squareOrderId);
                    }
                })
                ->first();
            $existing = $matched ?? $existing;
        }

        if ($paymentStatus !== SponsorshipPayment::STATUS_COMPLETED) {
            return DB::transaction(function () use ($sponsorship, $squarePaymentId, $squareOrderId, $squareInvoiceId, $paymentStatus, $existing, $billingAttempt): SponsorshipPayment {
                $lockedSponsorship = Sponsorship::query()->whereKey($sponsorship->id)->lockForUpdate()->firstOrFail();
                $record = $squarePaymentId !== null
                    ? SponsorshipPayment::query()->where('square_payment_id', $squarePaymentId)->lockForUpdate()->first()
                    : null;
                if ($record === null && $squareInvoiceId !== null) $record = SponsorshipPayment::query()->where('square_invoice_id', $squareInvoiceId)->lockForUpdate()->first();
                if ($record === null && $squareOrderId !== null) $record = SponsorshipPayment::query()->where('square_order_id', $squareOrderId)->lockForUpdate()->first();
                if ($record === null && $existing !== null) $record = SponsorshipPayment::query()->whereKey($existing->id)->lockForUpdate()->first();
                if ($record === null && $billingAttempt !== null) $record = SponsorshipPayment::query()->whereKey($billingAttempt->id)->lockForUpdate()->first();
                $record ??= new SponsorshipPayment(['sponsorship_id' => $lockedSponsorship->id]);
                $alreadyCompleted = $record->status === SponsorshipPayment::STATUS_COMPLETED || $record->invoice_id !== null;
                $alreadyFailed = $record->status === SponsorshipPayment::STATUS_FAILED
                    && $paymentStatus === SponsorshipPayment::STATUS_PENDING;
                $record->fill([
                    'square_payment_id' => $squarePaymentId ?: $record->square_payment_id,
                    'square_order_id' => $squareOrderId ?: $record->square_order_id,
                    'square_invoice_id' => $squareInvoiceId ?? $record->square_invoice_id,
                    'status' => $alreadyCompleted
                        ? SponsorshipPayment::STATUS_COMPLETED
                        : ($alreadyFailed ? SponsorshipPayment::STATUS_FAILED : $paymentStatus),
                    'sponsor_country' => $record->sponsor_country ?: $lockedSponsorship->sponsor->country,
                ]);
                $record->save();

                if ($alreadyCompleted && $record->billing_period && $lockedSponsorship->isRecurring()
                    && $lockedSponsorship->status !== Sponsorship::STATUS_CANCELLED) {
                    $lockedSponsorship->status = Sponsorship::STATUS_ACTIVE;
                    $lockedSponsorship->next_payment_date = Carbon::parse(app(SponsorshipBillingScheduleService::class)
                        ->nextPaymentDate(
                            $record->billing_period->toDateString(),
                            $lockedSponsorship->billing_anchor_date?->toDateString() ?? $lockedSponsorship->started_at?->toDateString(),
                        ), SponsorshipBillingScheduleService::TIMEZONE);
                    $lockedSponsorship->save();
                } elseif ($paymentStatus === SponsorshipPayment::STATUS_FAILED && $lockedSponsorship->isRecurring()
                    && $lockedSponsorship->status !== Sponsorship::STATUS_CANCELLED) {
                    $lockedSponsorship->status = Sponsorship::STATUS_PAST_DUE;
                    $lockedSponsorship->save();
                }

                return $record;
            }, 3);
        }

        $grossCents = (int) data_get($squarePayment, 'amount_money.amount', 0);
        $existingTotal = $existing instanceof SponsorshipPayment ? $existing->total_amount : null;
        $gross = $grossCents > 0 ? round($grossCents / 100, 2) : round((float) ($existingTotal ?? $sponsorship->amount), 2);
        $billingAttempt ??= $existing && $existing->billing_period ? $existing : null;
        $breakdown = $billingAttempt && $billingAttempt->billing_period
            ? [
                'subtotal' => (float) $billingAttempt->subtotal,
                'gst_amount' => (float) $billingAttempt->gst_amount,
                'total' => (float) $billingAttempt->total_amount,
                'tax_rate' => (float) $billingAttempt->tax_rate,
                'treatment' => (string) $billingAttempt->tax_treatment,
                'tax_code' => (string) $billingAttempt->tax_code,
            ]
            : $this->tax->breakdown($gross, (string) $sponsorship->sponsor->country, (bool) $sponsorship->sponsor->non_resident_declaration);
        $paidAt = $this->squareDateTime($squarePayment['updated_at'] ?? $squarePayment['created_at'] ?? null) ?? now();

        $sponsorshipPayment = DB::transaction(function () use ($sponsorship, $squarePayment, $squarePaymentId, $squareInvoiceId, $squareOrderId, $grossCents, $breakdown, $paidAt, $existing, $paymentMethod, $gatewayProvider, $paymentReference, $sendEmail, $billingAttempt): SponsorshipPayment {
            $lockedSponsorship = Sponsorship::query()->whereKey($sponsorship->id)->lockForUpdate()->firstOrFail();
            $payment = $squarePaymentId !== null
                ? SponsorshipPayment::query()->where('square_payment_id', $squarePaymentId)->lockForUpdate()->first()
                : null;
            if ($payment === null && $squareInvoiceId !== null) {
                $payment = SponsorshipPayment::query()->where('square_invoice_id', $squareInvoiceId)->lockForUpdate()->first();
            }
            if ($payment === null && $squareOrderId !== null) {
                $payment = SponsorshipPayment::query()->where('square_order_id', $squareOrderId)->lockForUpdate()->first();
            }
            if ($payment === null && $billingAttempt !== null) {
                $payment = SponsorshipPayment::query()->whereKey($billingAttempt->id)->lockForUpdate()->first();
            }
            if ($payment === null && $existing !== null) {
                $payment = SponsorshipPayment::query()->whereKey($existing->id)->lockForUpdate()->first();
            }

            if ($payment && $payment->invoice_id) {
                $payment->fill([
                    'square_payment_id' => $squarePaymentId ?: $payment->square_payment_id,
                    'square_order_id' => $squareOrderId ?: $payment->square_order_id,
                    'square_invoice_id' => $squareInvoiceId ?: $payment->square_invoice_id,
                    'status' => SponsorshipPayment::STATUS_COMPLETED,
                    'paid_at' => $payment->paid_at ?? $paidAt,
                    'receipt_email_enabled' => $sendEmail,
                ])->save();

                if ($gatewayProvider === 'square' && $payment->payment) {
                    $financePayment = $payment->payment;
                    $financePayment->gateway_provider = 'square';
                    $financePayment->gateway_status = 'COMPLETED';
                    $financePayment->gateway_reference_id = $squarePaymentId ?: $squareInvoiceId ?: $financePayment->gateway_reference_id;
                    $financePayment->square_payment_id = $squarePaymentId ?: $financePayment->square_payment_id;
                    $financePayment->square_order_id = $squareOrderId ?: $financePayment->square_order_id;
                    $financePayment->square_location_id = (string) ($squarePayment['location_id'] ?? $financePayment->square_location_id ?? '');
                    $financePayment->square_receipt_url = (string) ($squarePayment['receipt_url'] ?? $financePayment->square_receipt_url ?? '');
                    $financePayment->square_card_brand = (string) data_get($squarePayment, 'card_details.card.card_brand', $financePayment->square_card_brand ?? '');
                    $financePayment->square_card_last4 = (string) data_get($squarePayment, 'card_details.card.last_4', $financePayment->square_card_last4 ?? '');
                    if ($grossCents > 0) $financePayment->square_paid_money_amount = $grossCents;
                    $financePayment->square_gateway_created_at = $this->squareDateTime($squarePayment['created_at'] ?? null) ?? $financePayment->square_gateway_created_at;
                    $financePayment->square_gateway_updated_at = $this->squareDateTime($squarePayment['updated_at'] ?? null) ?? $paidAt;
                    $financePayment->save();
                }

                if ($lockedSponsorship->isRecurring() && $payment->billing_period && $lockedSponsorship->status !== Sponsorship::STATUS_CANCELLED) {
                    $lockedSponsorship->status = Sponsorship::STATUS_ACTIVE;
                    $lockedSponsorship->next_payment_date = Carbon::parse(app(SponsorshipBillingScheduleService::class)
                        ->nextPaymentDate(
                            $payment->billing_period->toDateString(),
                            $lockedSponsorship->billing_anchor_date?->toDateString() ?? $lockedSponsorship->started_at?->toDateString(),
                        ), SponsorshipBillingScheduleService::TIMEZONE);
                    $lockedSponsorship->save();
                }

                return $payment->fresh(['invoice', 'payment', 'sponsorship.sponsor', 'sponsorship.project']);
            }

            $sponsor = $sponsorship->sponsor;
            $project = $sponsorship->project;
            $includeRecipientDetails = $sponsorship->checkout_type !== Sponsorship::CHECKOUT_TYPE_COMMUNITY_SUPPORT || (bool) $sponsorship->invoice_recipient_customized;
            $invoiceNumber = $this->documentNumbers->nextInvoiceNumber();
            $invoice = Invoice::query()->create([
                'invoice_number' => $invoiceNumber,
                'user_id' => $sponsor->user_id,
                'billing_name' => $includeRecipientDetails ? $sponsor->contact_name : null,
                'billing_email' => $sponsor->email,
                'billing_company' => $includeRecipientDetails ? $sponsor->company_name : null,
                'billing_address' => $includeRecipientDetails ? $sponsor->billing_address : null,
                'billing_address2' => $includeRecipientDetails ? $sponsor->billing_address2 : null,
                'billing_city' => $includeRecipientDetails ? $sponsor->billing_city : null,
                'billing_state' => $includeRecipientDetails ? $sponsor->billing_state : null,
                'billing_postcode' => $includeRecipientDetails ? $sponsor->billing_postcode : null,
                'billing_country' => $includeRecipientDetails ? $sponsor->country : null,
                'recipient_abn' => $includeRecipientDetails ? $sponsor->abn : null,
                'recipient_foreign_tax_id' => $includeRecipientDetails ? $sponsor->foreign_tax_id : null,
                'tax_treatment_code' => $breakdown['treatment'],
                'status' => Invoice::STATUS_PAID,
                'issue_date' => $paidAt->toDateString(),
                'issued_at' => $paidAt,
                'subtotal_amount' => $breakdown['subtotal'],
                'gst_amount' => $breakdown['gst_amount'],
                'total_amount' => $breakdown['total'],
                'notes' => $this->taxNote($breakdown['treatment']),
            ]);

            InvoiceLine::query()->create([
                'invoice_id' => $invoice->id,
                'line_number' => 1,
                'kind' => 'sponsorship',
                'description' => ($project->is_primary ? 'STEMMechanics Sponsorship' : $project->name.' Project Sponsorship').' - '.($sponsorship->frequency === 'monthly' ? 'Monthly' : 'One-time'),
                'quantity' => 1,
                'unit_price_ex_tax' => $breakdown['subtotal'],
                'tax_rate' => $breakdown['tax_rate'],
                'line_total_ex_tax' => $breakdown['subtotal'],
                'tax_amount' => $breakdown['gst_amount'],
                'line_total_inc_tax' => $breakdown['total'],
            ]);

            $financePayment = $payment?->payment;
            if (! $financePayment) {
                $financePayment = new Payment();
            }
            $financePayment->kind = Payment::KIND_PAYMENT;
            $financePayment->user_id = $sponsor->user_id;
            $financePayment->received_on = $paidAt;
            $financePayment->payment_method = $paymentMethod;
            if ($paymentMethod === Payment::PAYMENT_METHOD_BANK_TRANSFER) {
                $financePayment->cleared_at = $paidAt;
            }
            $financePayment->total_amount = $breakdown['total'];
            $financePayment->gst_amount = $breakdown['gst_amount'];
            $financePayment->reference = $paymentReference ?: ($financePayment->reference ?: 'Sponsorship invoice '.$invoiceNumber);
            $financePayment->gateway_provider = $gatewayProvider;
            $financePayment->gateway_status = $gatewayProvider ? 'COMPLETED' : null;
            $financePayment->gateway_reference_id = $gatewayProvider ? ($squarePaymentId ?: $squareInvoiceId) : null;
            if ($gatewayProvider === 'square') {
                $financePayment->square_payment_id = $squarePaymentId;
                $financePayment->square_order_id = $squareOrderId;
                $financePayment->square_location_id = (string) ($squarePayment['location_id'] ?? '');
                $financePayment->square_receipt_url = (string) ($squarePayment['receipt_url'] ?? '');
                $financePayment->square_card_brand = (string) data_get($squarePayment, 'card_details.card.card_brand', '');
                $financePayment->square_card_last4 = (string) data_get($squarePayment, 'card_details.card.last_4', '');
                $financePayment->square_paid_money_amount = (int) round($breakdown['total'] * 100);
                $financePayment->square_gateway_created_at = $paidAt;
                $financePayment->square_gateway_updated_at = $paidAt;
            }
            $financePayment->save();

            InvoicePaymentAllocation::query()->create([
                'payment_id' => $financePayment->id,
                'invoice_id' => $invoice->id,
                'allocated_amount' => $breakdown['total'],
            ]);

            $payment ??= new SponsorshipPayment(['sponsorship_id' => $sponsorship->id]);
            $payment->fill([
                'invoice_id' => $invoice->id,
                'payment_id' => $financePayment->id,
                'square_payment_id' => $squarePaymentId,
                'square_order_id' => $squareOrderId,
                'square_invoice_id' => $squareInvoiceId,
                'status' => SponsorshipPayment::STATUS_COMPLETED,
                'subtotal' => $breakdown['subtotal'],
                'gst_amount' => $breakdown['gst_amount'],
                'total_amount' => $breakdown['total'],
                'tax_rate' => $breakdown['tax_rate'],
                'tax_treatment' => $breakdown['treatment'],
                'tax_code' => $breakdown['tax_code'],
                'sponsor_country' => $sponsor->country,
                'invoice_number' => $invoiceNumber,
                'paid_at' => $paidAt,
                'receipt_email_enabled' => $sendEmail,
            ])->save();

            if ($lockedSponsorship->status !== Sponsorship::STATUS_CANCELLED) {
                $lockedSponsorship->status = $lockedSponsorship->isRecurring() ? Sponsorship::STATUS_ACTIVE : Sponsorship::STATUS_COMPLETED;
                    $lockedSponsorship->started_at = $lockedSponsorship->started_at ?? $paidAt;
                if ($lockedSponsorship->isRecurring() && $payment->billing_period) {
                    $lockedSponsorship->next_payment_date = Carbon::parse(app(SponsorshipBillingScheduleService::class)
                        ->nextPaymentDate(
                            $payment->billing_period->toDateString(),
                            $lockedSponsorship->billing_anchor_date?->toDateString() ?? $lockedSponsorship->started_at?->toDateString(),
                        ), SponsorshipBillingScheduleService::TIMEZONE);
                }
                $lockedSponsorship->save();
            }

            return $payment->fresh(['invoice', 'payment', 'sponsorship.sponsor', 'sponsorship.project']);
        }, 3);

        $this->ensureInvoicePdfAndEmail($sponsorshipPayment, false, $sendEmail);

        return $sponsorshipPayment->fresh();
    }

    public function createUnpaidInvoice(Sponsorship $sponsorship, ?string $billingPeriod = null): SponsorshipPayment
    {
        $sponsorship->loadMissing(['sponsor', 'project']);

        return DB::transaction(function () use ($sponsorship, $billingPeriod): SponsorshipPayment {
            $lockedSponsorship = Sponsorship::query()
                ->with(['sponsor', 'project'])
                ->whereKey($sponsorship->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existingQuery = SponsorshipPayment::query()
                ->where('sponsorship_id', $lockedSponsorship->id)
                ->whereNotNull('invoice_id');
            if ($billingPeriod !== null) {
                $existingQuery->whereDate('billing_period', $billingPeriod);
            } else {
                $existingQuery->whereNull('billing_period');
            }
            $existing = $existingQuery->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            $sponsor = $lockedSponsorship->sponsor;
            $project = $lockedSponsorship->project;
            $breakdown = $this->tax->breakdown(
                (float) $lockedSponsorship->amount,
                (string) $sponsor->country,
                (bool) $sponsor->non_resident_declaration,
            );
            $issuedAt = now();
            $invoiceNumber = $this->documentNumbers->nextInvoiceNumber();
            $frequencyLabel = $lockedSponsorship->isRecurring() ? 'Monthly' : 'One-time';
            $description = ($project->is_primary ? 'STEMMechanics Sponsorship' : $project->name.' Project Sponsorship').' - '.$frequencyLabel;

            $invoice = Invoice::query()->create([
                'invoice_number' => $invoiceNumber,
                'user_id' => $sponsor->user_id,
                'billing_name' => $sponsor->contact_name,
                'billing_email' => $sponsor->email,
                'billing_company' => $sponsor->company_name,
                'billing_address' => $sponsor->billing_address,
                'billing_address2' => $sponsor->billing_address2,
                'billing_city' => $sponsor->billing_city,
                'billing_state' => $sponsor->billing_state,
                'billing_postcode' => $sponsor->billing_postcode,
                'billing_country' => $sponsor->country,
                'recipient_abn' => $sponsor->abn,
                'recipient_foreign_tax_id' => $sponsor->foreign_tax_id,
                'tax_treatment_code' => $breakdown['treatment'],
                'status' => Invoice::STATUS_ISSUED,
                'issue_date' => $issuedAt->toDateString(),
                'issued_at' => $issuedAt,
                'due_date' => InvoiceDueDate::fromIssueDate($issuedAt),
                'subtotal_amount' => $breakdown['subtotal'],
                'gst_amount' => $breakdown['gst_amount'],
                'total_amount' => $breakdown['total'],
                'notes' => $this->taxNote($breakdown['treatment']),
            ]);

            InvoiceLine::query()->create([
                'invoice_id' => $invoice->id,
                'line_number' => 1,
                'kind' => 'sponsorship',
                'description' => $description,
                'quantity' => 1,
                'unit_price_ex_tax' => $breakdown['subtotal'],
                'tax_rate' => $breakdown['tax_rate'],
                'line_total_ex_tax' => $breakdown['subtotal'],
                'tax_amount' => $breakdown['gst_amount'],
                'line_total_inc_tax' => $breakdown['total'],
            ]);

            return SponsorshipPayment::query()->create([
                'sponsorship_id' => $lockedSponsorship->id,
                'invoice_id' => $invoice->id,
                'status' => SponsorshipPayment::STATUS_PENDING,
                'subtotal' => $breakdown['subtotal'],
                'gst_amount' => $breakdown['gst_amount'],
                'total_amount' => $breakdown['total'],
                'tax_rate' => $breakdown['tax_rate'],
                'tax_treatment' => $breakdown['treatment'],
                'tax_code' => $breakdown['tax_code'],
                'sponsor_country' => $sponsor->country,
                'billing_period' => $billingPeriod,
                'invoice_number' => $invoiceNumber,
                'receipt_email_enabled' => true,
            ])->fresh(['invoice', 'sponsorship.sponsor', 'sponsorship.project']);
        }, 3);
    }

    public function syncPaidInvoice(Invoice $invoice): void
    {
        if ((string) $invoice->status !== Invoice::STATUS_PAID) {
            return;
        }

        $sponsorshipPayment = DB::transaction(function () use ($invoice): ?SponsorshipPayment {
            $lockedInvoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->first();
            if (! $lockedInvoice || (string) $lockedInvoice->status !== Invoice::STATUS_PAID
                || $lockedInvoice->displayOutstandingAmount() > 0.0001) {
                return null;
            }

            $sponsorshipPayment = SponsorshipPayment::query()
                ->where('invoice_id', $lockedInvoice->id)
                ->where('status', SponsorshipPayment::STATUS_PENDING)
                ->lockForUpdate()
                ->first();
            if (! $sponsorshipPayment) {
                return null;
            }

            $financePayment = $lockedInvoice->allocations()
                ->with('customerPayment')
                ->whereNull('tax_adjustment_id')
                ->orderByDesc('id')
                ->get()
                ->map(fn ($allocation) => $allocation->customerPayment)
                ->filter(fn ($payment) => $payment && $payment->kind === Payment::KIND_PAYMENT)
                ->first();
            $paidAt = $financePayment instanceof Payment ? ($financePayment->received_on ?? now()) : now();

            $sponsorshipPayment->fill([
                'payment_id' => $financePayment?->id,
                'square_payment_id' => $financePayment?->square_payment_id,
                'square_order_id' => $financePayment?->square_order_id,
                'status' => SponsorshipPayment::STATUS_COMPLETED,
                'paid_at' => $paidAt,
            ])->save();

            $sponsorship = Sponsorship::query()->whereKey($sponsorshipPayment->sponsorship_id)->lockForUpdate()->first();
            if ($sponsorship && $sponsorship->status !== Sponsorship::STATUS_CANCELLED) {
                $sponsorship->status = $sponsorship->isRecurring()
                    ? Sponsorship::STATUS_ACTIVE
                    : Sponsorship::STATUS_COMPLETED;
                $sponsorship->started_at = $sponsorship->started_at ?? $paidAt;
                if ($sponsorship->isRecurring() && $sponsorshipPayment->billing_period) {
                    $sponsorship->next_payment_date = Carbon::parse(app(SponsorshipBillingScheduleService::class)
                        ->nextPaymentDate(
                            $sponsorshipPayment->billing_period->toDateString(),
                            $sponsorship->billing_anchor_date?->toDateString() ?? $sponsorship->started_at?->toDateString(),
                        ), SponsorshipBillingScheduleService::TIMEZONE);
                }
                $sponsorship->save();
            }

            return $sponsorshipPayment->fresh(['invoice', 'payment', 'sponsorship.sponsor', 'sponsorship.project']);
        }, 3);

        if ($sponsorshipPayment) {
            // The invoice was emailed when issued. Send the paid invoice with its
            // payment receipt as soon as the normal invoice system marks it paid.
            $this->ensureInvoicePdfAndEmail($sponsorshipPayment, true, true);
        }
    }

    public function syncOverdueInvoice(Invoice $invoice): void
    {
        if ((string) $invoice->status !== Invoice::STATUS_OVERDUE) return;

        $payment = SponsorshipPayment::query()
            ->where('invoice_id', $invoice->id)
            ->where('status', SponsorshipPayment::STATUS_PENDING)
            ->with('sponsorship')
            ->first();
        $sponsorship = $payment?->sponsorship;
        if (! $sponsorship || ! $sponsorship->isRecurring() || $sponsorship->billing_method !== 'invoice'
            || $sponsorship->status === Sponsorship::STATUS_CANCELLED) {
            return;
        }

        if ($sponsorship->status !== Sponsorship::STATUS_PAST_DUE) {
            $sponsorship->status = Sponsorship::STATUS_PAST_DUE;
            $sponsorship->save();
        }
    }

    public function recordManualPayment(Sponsorship $sponsorship, string $paymentMethod, bool $sendEmail = false): SponsorshipPayment
    {
        $sponsorship->loadMissing(['sponsor', 'project']);
        $existing = $sponsorship->payments()
            ->where('status', SponsorshipPayment::STATUS_COMPLETED)
            ->first();
        if ($existing) {
            $existing->receipt_email_enabled = $sendEmail;
            $existing->save();
            try {
                $this->ensureInvoicePdfAndEmail($existing, $sendEmail, $sendEmail);
            } catch (Throwable $exception) {
                report($exception);
            }
            return $existing->fresh();
        }

        try {
            return $this->recordPayment($sponsorship, [
                'status' => 'COMPLETED',
                'amount_money' => [
                    'amount' => (int) round((float) $sponsorship->amount * 100),
                    'currency' => $sponsorship->currency,
                ],
                'created_at' => ($sponsorship->started_at ?? now())->toIso8601String(),
                ], null, $paymentMethod, null, 'Offline sponsorship payment', $sendEmail);
        } catch (Throwable $exception) {
            $existing = $sponsorship->payments()
                ->where('status', SponsorshipPayment::STATUS_COMPLETED)
                ->whereNotNull('invoice_id')
                ->first();
            if (! $existing) throw $exception;
            report($exception);
            return $existing;
        }
    }

    public function ensureInvoicePdfAndEmail(SponsorshipPayment $payment, bool $forceEmail = false, bool $sendEmail = true): void
    {
        $payment->loadMissing('invoice.lines', 'payment', 'sponsorship.sponsor', 'sponsorship.project');
        if (! $payment->invoice || ! $payment->sponsorship) {
            return;
        }

        $binary = null;
        $path = trim((string) $payment->invoice_pdf_path);
        if ($path !== '' && Storage::disk('local')->exists($path)) {
            $binary = Storage::disk('local')->get($path);
        }
        if (! is_string($binary) || $binary === '') {
            $line = $payment->invoice->lines->first();
            $lineItem = $line ? [
                'id' => $line->id,
                'kind' => (string) $line->kind,
                'description' => (string) $line->description,
                'notes' => (string) ($line->notes ?? ''),
                'details_json' => is_array($line->details_json) ? $line->details_json : [],
                'quantity' => (float) $line->quantity,
                'unit_price_ex_tax' => (float) $line->unit_price_ex_tax,
                'tax_rate' => (float) $line->tax_rate,
                'line_total_ex_tax' => (float) $line->line_total_ex_tax,
                'tax_amount' => (float) $line->tax_amount,
                'line_total_inc_tax' => (float) $line->line_total_inc_tax,
                'source_type' => $line->source_type,
                'source_id' => $line->source_id,
                'original_invoice_line_id' => $line->original_invoice_line_id,
            ] : null;
            $binary = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.invoice', [
                'invoice' => $payment->invoice,
                'itemPages' => $lineItem ? [[$lineItem]] : [[]],
                'adjustments' => collect(),
                'publicPayUrl' => null,
            ])->setOption(['enable_font_subsetting' => true])->output();
            $path = 'sponsorship-invoices/'.$payment->invoice_number.'.pdf';
            Storage::disk('local')->put($path, $binary);
            $payment->invoice_pdf_path = $path;
            $payment->save();
        }

        if (! $sendEmail || (! $payment->receipt_email_enabled && ! $forceEmail)) {
            return;
        }

        if ($forceEmail || $payment->emailed_at === null) {
            $sponsor = $payment->sponsorship->sponsor;
            $attachments = [[
                'filename' => 'invoice-'.$payment->invoice_number.'.pdf',
                'content' => $binary,
                'mime' => 'application/pdf',
            ]];
            if ($payment->payment) {
                $receiptBinary = $this->buildPaymentReceiptPdf($payment)->output();
                if ($receiptBinary !== '') {
                    $attachments[] = [
                        'filename' => 'payment-receipt-'.$payment->payment->id.'.pdf',
                        'content' => $receiptBinary,
                        'mime' => 'application/pdf',
                    ];
                }
            }

            dispatch(new SendEmail($sponsor->email, new InvoiceDocumentBundle(
                recipientName: $payment->invoice->user?->getName() ?: (string) ($payment->invoice->billing_name ?: $sponsor->contact_name ?: $sponsor->email),
                invoiceNumber: (string) $payment->invoice_number,
                orderNumber: null,
                attachments: $attachments,
                outstandingAmount: (float) $payment->invoice->displayOutstandingAmount(),
                payUrl: null,
            )))->onQueue('mail');
            $payment->emailed_at = now();
            $payment->save();
        }
    }

    private function buildPaymentReceiptPdf(SponsorshipPayment $sponsorshipPayment): \Barryvdh\DomPDF\PDF
    {
        $sponsorshipPayment->loadMissing('invoice.user', 'invoice.lines', 'payment');
        $invoice = $sponsorshipPayment->invoice;
        $payment = $sponsorshipPayment->payment;
        if (! $invoice || ! $payment) {
            throw new \RuntimeException('A sponsorship receipt requires its invoice and payment record.');
        }

        $processedAt = $this->squareDateTime($payment->square_gateway_updated_at)
            ?? $this->squareDateTime($payment->square_gateway_created_at);
        $processedAtLabel = $processedAt?->format('M j, Y g:i a') ?? '';

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.payment-receipt', [
            'isRefund' => false,
            'receiptTitle' => 'Payment Receipt',
            'amountLabel' => 'Amount Paid',
            'receiptNumber' => (string) $payment->id,
            'invoiceNumber' => (string) $invoice->invoice_number,
            'customerName' => $invoice->user?->getName() ?: (string) ($invoice->billing_name ?: 'Customer'),
            'amountPaid' => (float) $payment->total_amount,
            'gstAmount' => abs((float) $payment->gst_amount),
            'paymentMethod' => Payment::paymentMethodLabel((string) ($payment->payment_method ?? Payment::PAYMENT_METHOD_OTHER)),
            'paidOn' => $payment->received_on?->format('M j, Y g:i a') ?? now()->format('M j, Y g:i a'),
            'reference' => (string) ($payment->reference ?? ''),
            'gatewayProvider' => (string) ($payment->gateway_provider ?? ''),
            'gatewayStatus' => (string) ($payment->gateway_status ?? ''),
            'transactionId' => trim((string) ($payment->square_payment_id ?: $payment->gateway_reference_id)),
            'squareOrderId' => (string) ($payment->square_order_id ?? ''),
            'cardBrand' => (string) ($payment->square_card_brand ?? ''),
            'cardLast4' => (string) ($payment->square_card_last4 ?? ''),
            'squareReceiptUrl' => (string) ($payment->square_receipt_url ?? ''),
            'gatewayProcessedAt' => $processedAtLabel,
            'footerMessage' => 'Thank you for your payment.',
            'creditAppliedAmount' => 0,
            'creditReferenceSummary' => null,
            'orderTotalAmount' => round((float) $invoice->total_amount, 2),
        ])->setOption(['enable_font_subsetting' => true]);
    }

    public function refreshInvoicePdfAndEmail(SponsorshipPayment $payment): void
    {
        $path = trim((string) $payment->invoice_pdf_path);
        if ($path !== '' && Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
        $payment->invoice_pdf_path = null;
        $payment->emailed_at = null;
        $payment->save();
        $this->ensureInvoicePdfAndEmail($payment, true);
    }

    public function cancel(Sponsorship $sponsorship): void
    {
        $sponsorship->loadMissing('sponsor');
        if ($sponsorship->isRecurring()) {
            // Removing the due date stops the next locally scheduled charge.
            $sponsorship->next_payment_date = null;
        }
        $sponsorship->status = Sponsorship::STATUS_CANCELLED;
        $sponsorship->cancelled_at = now();
        $sponsorship->save();

        $email = strtolower(trim((string) $sponsorship->sponsor?->email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            dispatch(new SendEmail($email, new SponsorshipCancelled(
                recipientName: trim((string) ($sponsorship->sponsor?->contact_name ?: 'there')),
                amount: strtoupper((string) $sponsorship->currency).' $'.number_format((float) $sponsorship->amount, 2),
                cancelledAt: $sponsorship->cancelled_at->copy()->timezone(config('app.timezone'))->format('j F Y'),
                manageUrl: route('sponsor.manage.request'),
                email: $email,
                billingMethod: (string) $sponsorship->billing_method,
            )))->onQueue('mail');
        } catch (Throwable $exception) {
            report($exception);
            Log::warning('Unable to queue sponsorship cancellation confirmation.', [
                'sponsorship_id' => (int) $sponsorship->id,
                'sponsor_id' => (int) $sponsorship->sponsor_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function renderInvoicePdf(SponsorshipPayment $payment): string
    {
        $this->ensureInvoicePdfAndEmail($payment);
        $payment->refresh();
        return Storage::disk('local')->get((string) $payment->invoice_pdf_path);
    }

    private function squareBillingAddress(Sponsor $sponsor): ?array
    {
        $lines = array_filter([$sponsor->billing_address, $sponsor->billing_address2]);
        if ($lines === [] && ! $sponsor->billing_city && ! $sponsor->billing_state && ! $sponsor->billing_postcode) {
            return null;
        }
        $country = strtoupper(trim((string) $sponsor->country));
        if (in_array(strtolower($country), ['australia', 'aus'], true)) $country = 'AU';
        return array_filter([
            'address_line_1' => $sponsor->billing_address,
            'address_line_2' => $sponsor->billing_address2,
            'locality' => $sponsor->billing_city,
            'administrative_district_level_1' => $sponsor->billing_state,
            'postal_code' => $sponsor->billing_postcode,
            'country' => preg_match('/^[A-Z]{2}$/', $country) ? $country : null,
        ], static fn ($value) => $value !== null && $value !== '');
    }

    private function squareCardBillingAddress(Sponsor $sponsor): ?array
    {
        $address = $this->squareBillingAddress($sponsor);

        // Square's Sandbox CreateCard endpoint only accepts this postal code
        // when storing a sandbox test card. Keep this test value out of sponsor
        // and invoice records; production continues to use the declared address.
        if (config('services.square.environment') === 'sandbox') {
            $address ??= [];
            $address['postal_code'] = '94103';
        }

        return $address;
    }

    private function squareDateTime(mixed $value): ?\Illuminate\Support\Carbon
    {
        if (! is_string($value) || trim($value) === '') return null;
        try { return \Illuminate\Support\Carbon::parse($value); } catch (Throwable) { return null; }
    }

    private function taxNote(string $treatment): string
    {
        return match ($treatment) {
            SponsorshipTaxService::GST_FREE_EXPORT => 'GST-free export supply. GST has not been charged.',
            SponsorshipTaxService::NO_GST => 'No GST has been charged for this supply.',
            default => 'Sponsorship amount includes GST where applicable.',
        };
    }

    private function stringOrNull(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }
}
