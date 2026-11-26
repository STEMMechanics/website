<?php

namespace App\Services;

use App\Jobs\SendEmail;
use App\Mail\OrganisationMembershipRequestAdminNotification;
use App\Models\Organisation;
use App\Models\OrganisationMembershipRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class OrganisationMembershipService
{
    /**
     * @return array{state:string,organisation:?Organisation}
     */
    public function applyProfileOrganisation(User $user, string $name, array $details = []): array
    {
        $name = preg_replace('/\s+/u', ' ', trim($name)) ?? '';

        if ($name === '') {
            return ['state' => 'unchanged', 'organisation' => null];
        }

        $result = DB::transaction(function () use ($user, $name, $details): array {
            $organisation = Organisation::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->lockForUpdate()
                ->first();

            if (! $organisation) {
                $organisation = Organisation::create([
                    'name' => $name,
                    'type' => ($details['type'] ?? null) ?: 'other',
                    'website_url' => $details['website_url'] ?? null,
                    'billing_address' => $details['billing_address'] ?? null,
                    'billing_address2' => $details['billing_address2'] ?? null,
                    'billing_city' => $details['billing_city'] ?? null,
                    'billing_state' => $details['billing_state'] ?? null,
                    'billing_postcode' => $details['billing_postcode'] ?? null,
                    'billing_country' => $details['billing_country'] ?? null,
                    'shipping_address' => $details['shipping_address'] ?? null,
                    'shipping_address2' => $details['shipping_address2'] ?? null,
                    'shipping_city' => $details['shipping_city'] ?? null,
                    'shipping_state' => $details['shipping_state'] ?? null,
                    'shipping_postcode' => $details['shipping_postcode'] ?? null,
                    'shipping_country' => $details['shipping_country'] ?? null,
                ]);
                $user->organisations()->syncWithoutDetaching([
                    $organisation->id => ['role' => 'contact'],
                ]);
                if ($user->primary_organisation_id === null) {
                    $user->updateQuietly(['primary_organisation_id' => $organisation->id]);
                }

                return ['state' => 'created', 'organisation' => $organisation];
            }

            $isMember = $user->organisations()->whereKey($organisation->id)->exists();
            if ($isMember) {
                $this->approvePendingRequestsFor($user, $organisation, null);

                return ['state' => 'linked', 'organisation' => $organisation];
            }

            if ($this->hasVerifiedAllowedDomain($user, $organisation)) {
                $user->organisations()->syncWithoutDetaching([
                    $organisation->id => ['role' => 'member'],
                ]);
                if ($user->primary_organisation_id === null) {
                    $user->updateQuietly(['primary_organisation_id' => $organisation->id]);
                }
                $this->approvePendingRequestsFor($user, $organisation, null);

                return ['state' => 'auto_approved', 'organisation' => $organisation];
            }

            $pendingRequest = OrganisationMembershipRequest::query()
                ->where('organisation_id', $organisation->id)
                ->where('user_id', $user->id)
                ->where('status', 'pending')
                ->first();

            if (! $pendingRequest) {
                OrganisationMembershipRequest::create([
                    'organisation_id' => $organisation->id,
                    'user_id' => $user->id,
                    'status' => 'pending',
                ]);
            }
            return ['state' => 'pending', 'organisation' => $organisation];
        });

        if ($result['state'] === 'pending') {
            $this->notifyAdminsOfUnmanagedPendingRequests($result['organisation']);
        }

        return $result;
    }

    public function notifyAdminsOfUnmanagedPendingRequests(Organisation $organisation): void
    {
        $recipients = preg_split('/[;,]+/', (string) config('mail.admin_bcc', 'admin@stemmechanics.com.au')) ?: [];
        $recipients = collect($recipients)
            ->map(fn ($email): string => strtolower(trim((string) $email)))
            ->filter(fn (string $email): bool => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();

        if ($recipients === []) {
            return;
        }

        $requestIds = OrganisationMembershipRequest::query()
            ->where('organisation_id', $organisation->id)
            ->where('status', 'pending')
            ->whereNull('admin_notified_at')
            ->orderBy('created_at')
            ->pluck('id');
        $adminUrl = route('admin.organisation.edit', [
            'organisation' => $organisation->id,
            'tab' => 'users',
        ]);

        foreach ($requestIds as $requestId) {
            $membershipRequest = DB::transaction(function () use ($organisation, $requestId): ?OrganisationMembershipRequest {
                $membershipRequest = OrganisationMembershipRequest::query()
                    ->lockForUpdate()
                    ->find($requestId);

                if (! $membershipRequest instanceof OrganisationMembershipRequest
                    || $membershipRequest->status !== 'pending'
                    || $membershipRequest->admin_notified_at !== null
                    || $this->hasManagerAccess($organisation)) {
                    return null;
                }

                $membershipRequest->loadMissing(['organisation', 'user']);
                $membershipRequest->admin_notified_at = now();
                $membershipRequest->save();

                return $membershipRequest;
            });

            if (! $membershipRequest instanceof OrganisationMembershipRequest) {
                continue;
            }

            try {
                foreach ($recipients as $recipient) {
                    dispatch(new SendEmail(
                        $recipient,
                        new OrganisationMembershipRequestAdminNotification($membershipRequest, $adminUrl)
                    ))->onQueue('mail');
                }
            } catch (Throwable $exception) {
                OrganisationMembershipRequest::query()
                    ->whereKey($membershipRequest->id)
                    ->where('status', 'pending')
                    ->update(['admin_notified_at' => null]);
                Log::warning('Could not queue admin notification for an organisation join request.', [
                    'organisation_id' => (string) $organisation->id,
                    'membership_request_id' => (string) $membershipRequest->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function hasManagerAccess(Organisation $organisation): bool
    {
        $organisationIds = array_values(array_unique([
            (string) $organisation->id,
            ...$organisation->ancestorIds(),
        ]));

        return DB::table('organisation_user')
            ->whereIn('organisation_id', $organisationIds)
            ->where('role', 'contact')
            ->exists();
    }

    public function hasVerifiedAllowedDomain(User $user, Organisation $organisation): bool
    {
        if ($user->email_verified_at === null) {
            return false;
        }

        $email = mb_strtolower(trim((string) $user->email));
        $domain = strrchr($email, '@');
        if (! is_string($domain) || $domain === '') {
            return false;
        }
        $domain = ltrim($domain, '@');

        return in_array($domain, $organisation->auto_approved_email_domains ?? [], true);
    }

    public function approvePendingRequestsFor(User $user, Organisation $organisation, ?User $reviewer): void
    {
        $requests = OrganisationMembershipRequest::query()
            ->where('organisation_id', $organisation->id)
            ->where('user_id', $user->id)
            ->where('status', 'pending');

        if ($requests->exists()) {
            $requests->update([
                'status' => 'approved',
                'reviewed_by' => $reviewer?->id,
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
