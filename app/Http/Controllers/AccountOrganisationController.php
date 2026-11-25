<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use App\Models\OrganisationMembershipRequest;
use App\Models\User;
use App\Services\OrganisationNameMatcher;
use App\Services\OrganisationMembershipService;
use App\Support\WebsiteUrl;
use App\Support\ViewAsSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccountOrganisationController extends Controller
{
    public function __construct(private readonly ViewAsSession $viewAs) {}

    public function link(Request $request, OrganisationNameMatcher $nameMatcher, OrganisationMembershipService $membershipService): JsonResponse
    {
        $user = $this->actingUser($request);
        $name = $request->input('organisation_name');
        $name = is_string($name) ? (preg_replace('/\s+/u', ' ', trim($name)) ?? '') : '';
        $organisation = $name === ''
            ? null
            : Organisation::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
        $isNewOrganisation = $name !== '' && ! $organisation;
        $detailFields = [
            'new_organisation_type',
            'new_organisation_website_url',
            'new_organisation_address',
            'new_organisation_address2',
            'new_organisation_city',
            'new_organisation_state',
            'new_organisation_postcode',
            'new_organisation_country',
        ];
        $website = $request->input('new_organisation_website_url');
        $request->merge([
            'organisation_name' => $name,
            'new_organisation_website_url' => ! $isNewOrganisation
                ? null
                : (is_string($website) ? WebsiteUrl::normalize($website) : $website),
        ]);
        if (! $isNewOrganisation) {
            foreach ($detailFields as $field) {
                $request->merge([$field => null]);
            }
        }

        $validated = $request->validate([
            'organisation_name' => ['required', 'string', 'max:255'],
            'organisation_keep_as_entered' => ['nullable', 'boolean'],
            'new_organisation_type' => $isNewOrganisation
                ? ['required', Rule::in(array_keys(Organisation::TYPES))]
                : ['nullable'],
            'new_organisation_website_url' => $isNewOrganisation ? WebsiteUrl::validationRules() : ['nullable'],
            'new_organisation_address' => $isNewOrganisation
                ? ['nullable', 'string', 'max:255', 'required_with:new_organisation_address2,new_organisation_city,new_organisation_state,new_organisation_postcode,new_organisation_country']
                : ['nullable'],
            'new_organisation_address2' => $isNewOrganisation ? ['nullable', 'string', 'max:255'] : ['nullable'],
            'new_organisation_city' => $isNewOrganisation
                ? ['nullable', 'string', 'max:120', 'required_with:new_organisation_address,new_organisation_address2,new_organisation_state,new_organisation_postcode,new_organisation_country']
                : ['nullable'],
            'new_organisation_state' => $isNewOrganisation
                ? ['nullable', 'string', 'max:120', 'required_with:new_organisation_address,new_organisation_address2,new_organisation_city,new_organisation_postcode,new_organisation_country']
                : ['nullable'],
            'new_organisation_postcode' => $isNewOrganisation
                ? ['nullable', 'string', 'max:40', 'required_with:new_organisation_address,new_organisation_address2,new_organisation_city,new_organisation_state,new_organisation_country']
                : ['nullable'],
            'new_organisation_country' => $isNewOrganisation
                ? ['nullable', 'string', 'max:120', 'required_with:new_organisation_address,new_organisation_address2,new_organisation_city,new_organisation_state,new_organisation_postcode']
                : ['nullable'],
        ]);

        if ($isNewOrganisation && ! (bool) ($validated['organisation_keep_as_entered'] ?? false)) {
            $candidates = $nameMatcher->suggestions($name);
            if ($candidates !== []) {
                return response()->json([
                    'state' => 'match_candidates',
                    'organisation_match_candidates_html' => view('account.partials.organisation-match-candidates', [
                        'candidates' => $candidates,
                    ])->render(),
                ]);
            }
        }

        if ($organisation && $user->organisations()->whereKey($organisation->id)->exists()) {
            return response()->json([
                'state' => 'already_linked',
                'organisation_name' => $organisation->name,
                'message' => 'You are already linked to '.$organisation->name.'.',
            ]);
        }

        $hadPendingRequest = $organisation && OrganisationMembershipRequest::query()
            ->where('organisation_id', $organisation->id)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();

        $details = [
            'type' => $validated['new_organisation_type'] ?? null,
            'website_url' => $validated['new_organisation_website_url'] ?? null,
            'billing_address' => $validated['new_organisation_address'] ?? null,
            'billing_address2' => $validated['new_organisation_address2'] ?? null,
            'billing_city' => $validated['new_organisation_city'] ?? null,
            'billing_state' => $validated['new_organisation_state'] ?? null,
            'billing_postcode' => $validated['new_organisation_postcode'] ?? null,
            'billing_country' => $validated['new_organisation_country'] ?? null,
            'shipping_address' => $validated['new_organisation_address'] ?? null,
            'shipping_address2' => $validated['new_organisation_address2'] ?? null,
            'shipping_city' => $validated['new_organisation_city'] ?? null,
            'shipping_state' => $validated['new_organisation_state'] ?? null,
            'shipping_postcode' => $validated['new_organisation_postcode'] ?? null,
            'shipping_country' => $validated['new_organisation_country'] ?? null,
        ];
        $result = $membershipService->applyProfileOrganisation($user, $name, $details);

        if ($result['state'] === 'linked') {
            return response()->json([
                'state' => 'already_linked',
                'organisation_name' => $result['organisation']->name,
                'message' => 'You are already linked to '.$result['organisation']->name.'.',
            ]);
        }

        if ($result['state'] === 'pending' && $hadPendingRequest) {
            return response()->json([
                'state' => 'already_pending',
                'organisation_name' => $result['organisation']->name,
                'message' => 'Your request to join '.$result['organisation']->name.' is already waiting for approval.',
            ]);
        }

        $messages = [
            'pending' => 'Your request to join '.$result['organisation']->name.' is waiting for approval.',
            'auto_approved' => 'Your verified email domain was approved for '.$result['organisation']->name.'.',
            'created' => $result['organisation']->name.' has been added. You are its first manager.',
        ];
        $user->refresh();

        return response()->json([
            'state' => $result['state'],
            'organisation_name' => $result['organisation']->name,
            'message' => $messages[$result['state']] ?? 'Your organisation request has been updated.',
            ...$this->organisationListFragments($user),
        ]);
    }

    public function show(Request $request): View
    {
        /** @var User $user */
        $user = $this->actingUser($request);
        $directOrganisations = $user->organisations()->with('parent')->orderBy('name')->get();
        $directOrganisationIds = $directOrganisations->map(fn (Organisation $candidate): string => (string) $candidate->id)->all();
        $managedOrganisations = $directOrganisations->filter(fn (Organisation $candidate): bool => $candidate->pivot?->role === 'contact');
        $inheritedOrganisationIds = $managedOrganisations
            ->flatMap(fn (Organisation $managedOrganisation): array => $managedOrganisation->descendantIds())
            ->map(fn ($id): string => (string) $id)
            ->unique()
            ->reject(fn (string $id): bool => in_array($id, $directOrganisationIds, true))
            ->values()
            ->all();
        $inheritedOrganisations = $inheritedOrganisationIds === []
            ? collect()
            : Organisation::query()->with('parent')->whereIn('id', $inheritedOrganisationIds)->orderBy('name')->get();
        $organisations = $directOrganisations
            ->concat($inheritedOrganisations)
            ->unique(fn (Organisation $candidate): string => (string) $candidate->id)
            ->sortBy('name')
            ->values();
        abort_unless($organisations->isNotEmpty(), 404);
        $primaryIsLinked = in_array((string) $user->primary_organisation_id, $directOrganisationIds, true);
        $defaultOrganisationId = $primaryIsLinked ? (string) $user->primary_organisation_id : (string) $organisations->first()->id;
        $selectedOrganisationId = (string) $request->query('organisation_id', $defaultOrganisationId);
        $organisation = $organisations->first(fn (Organisation $candidate): bool => (string) $candidate->id === $selectedOrganisationId);
        abort_unless($organisation instanceof Organisation, 404);
        $organisation->load(['contacts', 'parent']);
        $canManageOrganisation = $this->canManage($user, $organisation);
        $isPrimaryOrganisation = (string) $user->primary_organisation_id === (string) $organisation->id;

        $organisationRequestsForApproval = $canManageOrganisation
            ? OrganisationMembershipRequest::query()
                ->with('user')
                ->where('organisation_id', $organisation->id)
                ->where('status', 'pending')
                ->latest()
                ->get()
            : collect();

        return view('account.organisation', compact('organisation', 'organisations', 'directOrganisationIds', 'inheritedOrganisationIds', 'organisationRequestsForApproval', 'canManageOrganisation', 'isPrimaryOrganisation'));
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $this->actingUser($request);

        $request->merge(['website_url' => WebsiteUrl::normalize($request->input('website_url'))]);
        $validated = $request->validate([
            'organisation_id' => ['required', 'uuid', Rule::exists('organisations', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'website_url' => WebsiteUrl::validationRules(),
            'organisation_billing_address' => ['nullable', 'string', 'max:255', 'required_with:organisation_billing_city,organisation_billing_postcode,organisation_billing_country,organisation_billing_state'],
            'organisation_billing_address2' => ['nullable', 'string', 'max:255'],
            'organisation_billing_city' => ['nullable', 'string', 'max:120', 'required_with:organisation_billing_address,organisation_billing_postcode,organisation_billing_country,organisation_billing_state'],
            'organisation_billing_state' => ['nullable', 'string', 'max:120', 'required_with:organisation_billing_address,organisation_billing_city,organisation_billing_postcode,organisation_billing_country'],
            'organisation_billing_postcode' => ['nullable', 'string', 'max:40', 'required_with:organisation_billing_address,organisation_billing_city,organisation_billing_country,organisation_billing_state'],
            'organisation_billing_country' => ['nullable', 'string', 'max:120', 'required_with:organisation_billing_address,organisation_billing_city,organisation_billing_postcode,organisation_billing_state'],
            'organisation_shipping_address' => ['nullable', 'string', 'max:255', 'required_with:organisation_shipping_city,organisation_shipping_postcode,organisation_shipping_country,organisation_shipping_state'],
            'organisation_shipping_address2' => ['nullable', 'string', 'max:255'],
            'organisation_shipping_city' => ['nullable', 'string', 'max:120', 'required_with:organisation_shipping_address,organisation_shipping_postcode,organisation_shipping_country,organisation_shipping_state'],
            'organisation_shipping_state' => ['nullable', 'string', 'max:120', 'required_with:organisation_shipping_address,organisation_shipping_city,organisation_shipping_postcode,organisation_shipping_country'],
            'organisation_shipping_postcode' => ['nullable', 'string', 'max:40', 'required_with:organisation_shipping_address,organisation_shipping_city,organisation_shipping_country,organisation_shipping_state'],
            'organisation_shipping_country' => ['nullable', 'string', 'max:120', 'required_with:organisation_shipping_address,organisation_shipping_city,organisation_shipping_postcode,organisation_shipping_state'],
            'organisation_shipping_same_billing' => ['nullable', 'boolean'],
        ]);

        $organisation = $this->accessibleOrganisation($user, (string) $validated['organisation_id']);
        abort_unless($this->canManage($user, $organisation), 403);
        unset($validated['organisation_id']);

        $name = preg_replace('/\s+/u', ' ', trim((string) $validated['name'])) ?? '';
        $duplicate = Organisation::query()
            ->where('id', '!=', $organisation->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages([
                'name' => 'An organisation with this name already exists. Choose its existing record from your account profile instead.',
            ]);
        }

        if ($request->boolean('organisation_shipping_same_billing')) {
            foreach (['address', 'address2', 'city', 'state', 'postcode', 'country'] as $field) {
                $validated['organisation_shipping_'.$field] = $validated['organisation_billing_'.$field] ?? null;
            }
        }
        unset($validated['organisation_shipping_same_billing']);
        $validated['name'] = $name;
        $organisationData = ['name' => $validated['name'], 'website_url' => $validated['website_url'] ?? null];
        foreach (['billing', 'shipping'] as $addressType) {
            foreach (['address', 'address2', 'city', 'state', 'postcode', 'country'] as $field) {
                $key = 'organisation_'.$addressType.'_'.$field;
                $organisationData[$addressType.'_'.$field] = $validated[$key] ?? null;
            }
        }
        $organisation->update($organisationData);

        return redirect()->route('account.organisation.show', ['organisation_id' => $organisation->id])
            ->with('message', 'Organisation details have been saved.')
            ->with('message-title', 'Organisation updated')
            ->with('message-type', 'success');
    }

    public function review(Request $request, OrganisationMembershipRequest $membershipRequest): RedirectResponse
    {
        $reviewer = $this->actingUser($request);
        $organisation = $membershipRequest->organisation;
        abort_unless($this->canManage($reviewer, $organisation), 403);
        abort_unless($membershipRequest->status === 'pending', 404);

        $decision = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
        ])['decision'];

        DB::transaction(function () use ($membershipRequest, $reviewer, $organisation, $decision): void {
            $lockedRequest = OrganisationMembershipRequest::query()
                ->lockForUpdate()
                ->findOrFail($membershipRequest->id);
            abort_unless($lockedRequest->status === 'pending', 404);

            if ($decision === 'approve') {
                $user = $lockedRequest->user;
                if (! $user->organisations()->whereKey($organisation->id)->exists()) {
                    $user->organisations()->attach($organisation->id, ['role' => 'member']);
                }
                if ($user->primary_organisation_id === null) {
                    $user->updateQuietly([
                        'primary_organisation_id' => $organisation->id,
                        'use_organisation_billing_address' => false,
                        'use_organisation_shipping_address' => false,
                    ]);
                }
                $lockedRequest->status = 'approved';
            } else {
                $lockedRequest->status = 'rejected';
            }

            $lockedRequest->reviewed_by = $reviewer->id;
            $lockedRequest->reviewed_at = now();
            $lockedRequest->save();
        });

        $message = $decision === 'approve' ? 'The organisation request was approved.' : 'The organisation request was declined.';

        if ($reviewer->isAdmin() && $request->boolean('return_to_admin_organisation')) {
            return redirect()->route('admin.organisation.edit', ['organisation' => $organisation->id, 'tab' => 'users'])
                ->with('message', $message)
                ->with('message-title', 'Request updated')
                ->with('message-type', 'success');
        }

        $canOpenOrganisationPage = $this->isLinked($reviewer, $organisation) || $this->hasManagerAncestorAccess($reviewer, $organisation);
        $destination = $canOpenOrganisationPage ? 'account.organisation.show' : 'account.show';

        return redirect()->route($destination, $canOpenOrganisationPage ? ['organisation_id' => $organisation->id] : [])
            ->with('message', $message)
            ->with('message-title', 'Request updated')
            ->with('message-type', 'success');
    }

    public function updateMemberRole(Request $request, User $member): RedirectResponse
    {
        /** @var User $manager */
        $manager = $this->actingUser($request);
        $validated = $request->validate([
            'organisation_id' => ['required', 'uuid', Rule::exists('organisations', 'id')],
            'role' => ['required', Rule::in(['contact', 'member'])],
        ]);
        $organisation = $this->accessibleOrganisation($manager, (string) $validated['organisation_id']);
        abort_unless($this->canManage($manager, $organisation), 403);
        abort_unless($organisation->contacts()->whereKey($member->id)->exists(), 404);
        $role = $validated['role'];

        DB::transaction(function () use ($organisation, $member, $role): void {
            $pivot = DB::table('organisation_user')
                ->where('organisation_id', $organisation->id)
                ->where('user_id', $member->id)
                ->lockForUpdate()
                ->first();
            abort_unless($pivot, 404);

            $currentRole = $pivot->role === 'contact' ? 'contact' : 'member';
            if ($currentRole === $role) {
                return;
            }

            if ($currentRole === 'contact' && $role === 'member') {
                $contacts = DB::table('organisation_user')
                    ->where('organisation_id', $organisation->id)
                    ->where('role', 'contact')
                    ->lockForUpdate()
                    ->get(['user_id']);
                if ($contacts->count() <= 1) {
                    throw ValidationException::withMessages([
                        'role' => 'An organisation needs at least one manager.',
                    ]);
                }
            }

            DB::table('organisation_user')
                ->where('organisation_id', $organisation->id)
                ->where('user_id', $member->id)
                ->update(['role' => $role, 'updated_at' => now()]);
        });

        $message = $role === 'contact'
            ? $member->getName().' can now manage the organisation.'
            : $member->getName().' is now a regular organisation member.';

        return redirect()->route('account.organisation.show', ['organisation_id' => $organisation->id])
            ->with('message', $message)
            ->with('message-title', 'Organisation access updated')
            ->with('message-type', 'success');
    }

    public function removeMember(Request $request, User $member): RedirectResponse
    {
        /** @var User $manager */
        $manager = $this->actingUser($request);
        $validated = $request->validate([
            'organisation_id' => ['required', 'uuid', Rule::exists('organisations', 'id')],
        ]);
        $organisation = $this->accessibleOrganisation($manager, (string) $validated['organisation_id']);
        abort_unless($this->canManage($manager, $organisation), 403);

        DB::transaction(function () use ($organisation, $member): void {
            $membership = DB::table('organisation_user')
                ->where('organisation_id', $organisation->id)
                ->where('user_id', $member->id)
                ->lockForUpdate()
                ->first();
            abort_unless($membership, 404);

            if ($membership->role === 'contact') {
                throw ValidationException::withMessages([
                    'member' => 'Remove manager access before removing this user from the organisation.',
                ]);
            }

            DB::table('organisation_user')
                ->where('organisation_id', $organisation->id)
                ->where('user_id', $member->id)
                ->delete();

            if ((string) $member->primary_organisation_id === (string) $organisation->id) {
                $member->updateQuietly([
                    'primary_organisation_id' => null,
                    'use_organisation_billing_address' => false,
                    'use_organisation_shipping_address' => false,
                    'use_organisation_account_terms' => false,
                ]);
            }
        });

        return redirect()->route('account.organisation.show', ['organisation_id' => $organisation->id])
            ->with('message', $member->getName().' has been removed from '.$organisation->name.'.')
            ->with('message-title', 'Member removed')
            ->with('message-type', 'success');
    }

    public function cancel(Request $request, OrganisationMembershipRequest $membershipRequest): RedirectResponse
    {
        $user = $this->actingUser($request);
        abort_unless((string) $membershipRequest->user_id === (string) $user->id, 403);
        abort_unless($membershipRequest->status === 'pending', 404);

        $membershipRequest->update([
            'status' => 'cancelled',
            'reviewed_at' => now(),
        ]);

        return redirect()->route('account.show')
            ->with('message', 'Your request to join the organisation was cancelled.')
            ->with('message-title', 'Request cancelled')
            ->with('message-type', 'success');
    }

    public function leave(Request $request, Organisation $organisation, OrganisationMembershipService $membershipService): RedirectResponse
    {
        /** @var User $user */
        $user = $this->actingUser($request);

        DB::transaction(function () use ($user, $organisation): void {
            $membership = DB::table('organisation_user')
                ->where('organisation_id', $organisation->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();
            abort_unless($membership, 404);

            DB::table('organisation_user')
                ->where('organisation_id', $organisation->id)
                ->where('user_id', $user->id)
                ->delete();

            if ((string) $user->primary_organisation_id === (string) $organisation->id) {
                $user->updateQuietly([
                    'primary_organisation_id' => null,
                    'use_organisation_billing_address' => false,
                    'use_organisation_shipping_address' => false,
                    'use_organisation_account_terms' => false,
                ]);
            }
        });

        $membershipService->notifyAdminsOfUnmanagedPendingRequests($organisation);

        return redirect()->route('account.show')
            ->with('message', 'You have left '.$organisation->name.'.')
            ->with('message-title', 'Organisation left')
            ->with('message-type', 'success');
    }

    private function organisationListFragments(User $user): array
    {
        $linkedOrganisations = $user->organisations()->with('parent')->orderBy('name')->get();
        $pendingOrganisationRequests = OrganisationMembershipRequest::query()
            ->with('organisation.parent')
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->latest()
            ->get();
        $formatOrganisationAddress = static function (Organisation $organisation, string $type): string {
            $stateAndPostcode = trim(implode(' ', array_filter([
                $organisation->{$type.'_state'},
                $organisation->{$type.'_postcode'},
            ], fn ($value) => filled($value))));

            return implode(', ', array_filter([
                $organisation->{$type.'_address'},
                $organisation->{$type.'_address2'},
                $organisation->{$type.'_city'},
                $stateAndPostcode,
                $organisation->{$type.'_country'},
            ], fn ($value) => filled($value)));
        };
        $viewData = [
            'linkedOrganisations' => $linkedOrganisations,
            'pendingOrganisationRequests' => $pendingOrganisationRequests,
            'primaryOrganisationId' => (string) $user->primary_organisation_id,
            'formatOrganisationAddress' => $formatOrganisationAddress,
        ];

        return [
            'organisation_list_html' => view('account.partials.organisation-list', $viewData)->render(),
            'organisation_action_forms_html' => view('account.partials.organisation-action-forms', $viewData)->render(),
        ];
    }

    private function isLinked(User $user, Organisation $organisation): bool
    {
        return $user->organisations()->whereKey($organisation->id)->exists();
    }

    private function actingUser(Request $request): User
    {
        $viewAsState = $request->session()->get(ViewAsSession::SESSION_KEY);
        if (is_array($viewAsState)) {
            abort_unless($this->viewAs->originalAdmin($request) instanceof User, 403);

            $targetId = trim((string) ($viewAsState['user_id'] ?? ''));
            $target = $targetId === ''
                ? null
                : User::query()->whereKey($targetId)->whereNull('anonymized_at')->first();
            abort_unless($target instanceof User, 403);

            return $target;
        }

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function accessibleOrganisation(User $user, string $organisationId): Organisation
    {
        $organisation = Organisation::query()->with('parent')->whereKey($organisationId)->first();
        abort_unless($organisation instanceof Organisation, 404);
        abort_unless($this->isLinked($user, $organisation) || $this->canManage($user, $organisation), 404);

        return $organisation;
    }

    private function canManage(User $user, Organisation $organisation): bool
    {
        return $user->isAdmin()
            || $user->organisations()
                ->whereKey($organisation->id)
                ->wherePivot('role', 'contact')
                ->exists()
            || $this->hasManagerAncestorAccess($user, $organisation);
    }

    private function hasManagerAncestorAccess(User $user, Organisation $organisation): bool
    {
        $managerOrganisationIds = $user->organisations()
            ->wherePivot('role', 'contact')
            ->pluck('organisations.id')
            ->map(fn ($id): string => (string) $id)
            ->all();

        return array_intersect($managerOrganisationIds, $organisation->ancestorIds()) !== [];
    }
}
