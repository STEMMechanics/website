<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use App\Models\OrganisationMembershipRequest;
use App\Models\User;
use App\Services\InvoiceEmailTemplateService;
use App\Support\WebsiteUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Validation\ValidationException;

class OrganisationController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $organisations = Organisation::query()
            ->with('parent')
            ->withCount(['contacts', 'workshops'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('type', 'like', '%'.$search.'%');
            }))
            ->orderBy('name')
            ->tap(fn ($listingQuery) => app(\App\Services\SiteListControls::class)->apply($listingQuery))->paginate(\App\Support\ListPageSize::resolve(25))
            ->withQueryString();

        return view('admin.organisation.index', compact('organisations', 'search'));
    }

    public function create(): View
    {
        return $this->formView();
    }

    public function contactOptions(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        if (mb_strlen($search) < 2) {
            return response()->json(['users' => []]);
        }

        $users = User::query()
            ->with(['organisations.parent', 'primaryOrganisation.parent'])
            ->when(! $request->boolean('include_ghost'), fn ($query) => $query->whereNotNull('email_verified_at'))
            ->where(function ($query) use ($search): void {
                $query->where('firstname', 'like', '%'.$search.'%')
                    ->orWhere('surname', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhereHas('primaryOrganisation', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('primaryOrganisation.parent', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('organisations', fn ($query) => $query->where('organisations.name', 'like', '%'.$search.'%'))
                    ->orWhereHas('organisations.parent', fn ($query) => $query->where('name', 'like', '%'.$search.'%'));
            })
            ->orderBy('firstname')
            ->orderBy('surname')
            ->limit(25)
            ->get()
            ->map(function (User $user): array {
                $linkedOrganisations = $user->organisations->sortBy('name')->keyBy(fn (Organisation $organisation): string => (string) $organisation->id);
                $primaryOrganisation = $user->primaryOrganisation;
                if ($primaryOrganisation instanceof Organisation) {
                    $linkedOrganisations->put((string) $primaryOrganisation->id, $primaryOrganisation);
                }
                $linkedOrganisations = $linkedOrganisations->sortBy('name')->values();
                $singleOrganisation = $linkedOrganisations->count() === 1 ? $linkedOrganisations->first() : null;
                $organisation = $primaryOrganisation ?? $singleOrganisation ?? $linkedOrganisations->first();
                $organisationName = $organisation instanceof Organisation
                    ? ($organisation->parent ? $organisation->parent->name.' — '.$organisation->name : (string) $organisation->name)
                    : '';
                $organisationSummary = $linkedOrganisations
                    ->map(fn (Organisation $linkedOrganisation): string => ($linkedOrganisation->parent ? $linkedOrganisation->parent->name.' — ' : '').$linkedOrganisation->name.((string) $linkedOrganisation->id === (string) $primaryOrganisation?->id ? ' (primary)' : ''))
                    ->implode(', ');

                return [
                    'id' => (string) $user->id,
                    'name' => $user->getName(),
                    'email' => (string) $user->email,
                    'is_anonymized' => $user->isAnonymized(),
                    'edit_url' => route('admin.user.edit', $user),
                    'organisation_id' => $singleOrganisation instanceof Organisation ? (string) $singleOrganisation->id : '',
                    'organisation_name' => $organisationName,
                    'organisation_summary' => $organisationSummary,
                    'organisation_count' => $linkedOrganisations->count(),
                ];
            })
            ->values();

        return response()->json(['users' => $users]);
    }

    public function organisationOptions(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        if (mb_strlen($search) < 2) {
            return response()->json(['organisations' => []]);
        }

        $excludedIds = [];
        $excludedOrganisation = Organisation::query()->find($request->query('exclude_id'));
        if ($excludedOrganisation) {
            $excludedIds = [(string) $excludedOrganisation->id, ...$excludedOrganisation->descendantIds()];
        }

        $organisations = Organisation::query()
            ->with('parent')
            ->when($excludedIds !== [], fn ($query) => $query->whereNotIn('id', $excludedIds))
            ->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhereHas('parent', fn ($parentQuery) => $parentQuery->where('name', 'like', '%'.$search.'%'));
            })
            ->orderBy('name')
            ->limit(25)
            ->get()
            ->map(fn (Organisation $organisation): array => [
                'id' => (string) $organisation->id,
                'name' => (string) $organisation->name,
                'label' => $organisation->parent
                    ? $organisation->parent->name.' — '.$organisation->name
                    : (string) $organisation->name,
            ])
            ->values();

        return response()->json(['organisations' => $organisations]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $contactIds = $validated['contact_ids'] ?? [];
        $logo = $request->file('logo');
        unset($validated['contact_ids'], $validated['logo']);
        $organisation = Organisation::create($validated);
        $this->syncContacts($organisation, $contactIds);
        $this->saveOrganisationLogo($organisation, $logo);

        return redirect()->route('admin.organisation.edit', $organisation)
            ->with('message', 'Organisation has been created')
            ->with('message-title', 'Organisation created')
            ->with('message-type', 'success');
    }

    public function edit(Organisation $organisation): View
    {
        $organisation->load(['contacts.primaryOrganisation', 'parent', 'children'])
            ->loadCount('workshops');

        return $this->formView($organisation);
    }

    public function update(Request $request, Organisation $organisation): RedirectResponse
    {
        $validated = $this->validated($request, $organisation);
        $contactIds = $validated['contact_ids'] ?? null;
        $logo = $request->file('logo');
        unset($validated['contact_ids'], $validated['logo']);
        $organisation->update($validated);
        if ($contactIds !== null) {
            $this->syncContacts($organisation, $contactIds);
        }
        $this->saveOrganisationLogo($organisation, $logo);

        return redirect()->route('admin.organisation.edit', $organisation)
            ->with('message', 'Organisation has been updated')
            ->with('message-title', 'Organisation updated')
            ->with('message-type', 'success');
    }

    public function destroy(Request $request, Organisation $organisation): RedirectResponse|JsonResponse
    {
        $organisation->delete();

        session()->flash('message', 'Organisation has been deleted');
        session()->flash('message-title', 'Organisation deleted');
        session()->flash('message-type', 'danger');

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'redirect' => route('admin.organisation.index'),
            ]);
        }

        return redirect()->route('admin.organisation.index');
    }

    private function formView(?Organisation $organisation = null): View
    {
        $organisationRequestsForApproval = $organisation
            ? OrganisationMembershipRequest::query()
                ->with('user')
                ->where('organisation_id', $organisation->id)
                ->where('status', 'pending')
                ->latest()
                ->get()
            : collect();

        $organisations = Organisation::query()
            ->with('parent')
            ->when($organisation, fn ($query) => $query->where('id', '!=', $organisation->id))
            ->orderBy('name')
            ->get();

        $invoiceEmailSiteDefaults = app(InvoiceEmailTemplateService::class)->organisationDefaults();

        return view('admin.organisation.edit', compact('organisation', 'organisations', 'invoiceEmailSiteDefaults', 'organisationRequestsForApproval'));
    }

    public function sponsorshipLogo(Organisation $organisation): Response
    {
        $path = trim((string) $organisation->logo_path);
        abort_unless($path !== '' && str_starts_with($path, 'sponsorship-assets/'), 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), ['Cache-Control' => 'private, no-store']);
    }

    private function saveOrganisationLogo(Organisation $organisation, mixed $file): void
    {
        if (! $file) return;

        $previousPath = $organisation->logo_path;
        $organisation->logo_path = $file->store('sponsorship-assets/sponsors', 'local');
        $organisation->save();

        if ($previousPath && $previousPath !== $organisation->logo_path) {
            Storage::disk('local')->delete($previousPath);
        }
    }

    /** @param array<int, string> $contactIds */
    private function syncContacts(Organisation $organisation, array $contactIds): void
    {
        $contactIds = array_values(array_unique(array_map('strval', $contactIds)));
        $currentContactIds = $organisation->contacts()
            ->wherePivot('role', 'contact')
            ->pluck('users.id')
            ->map(fn ($id): string => (string) $id)
            ->all();

        foreach (array_diff($currentContactIds, $contactIds) as $contactId) {
            $organisation->contacts()->updateExistingPivot($contactId, ['role' => 'member']);
        }

        $pivotData = [];
        foreach ($contactIds as $contactId) {
            $pivotData[$contactId] = ['role' => 'contact'];
        }

        $organisation->contacts()->syncWithoutDetaching($pivotData);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Organisation $organisation = null): array
    {
        $request->merge(['website_url' => WebsiteUrl::normalize($request->input('website_url'))]);
        $request->merge([
            'auto_approved_email_domains_text' => (string) $request->input('auto_approved_email_domains_text', ''),
        ]);
        $invalidParentIds = $organisation
            ? [(string) $organisation->id, ...$organisation->descendantIds()]
            : [];

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(array_keys(Organisation::TYPES))],
            'website_url' => WebsiteUrl::validationRules(),
            'auto_approved_email_domains_text' => ['nullable', 'string', 'max:4000'],
            'abn' => ['nullable', 'string', 'max:20'],
            'foreign_tax_id' => ['nullable', 'string', 'max:100'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'parent_id' => [
                'nullable',
                'exists:organisations,id',
                Rule::notIn($invalidParentIds),
            ],
            'billing_address' => ['nullable', 'string', 'max:255', 'required_with:billing_city,billing_postcode,billing_country,billing_state'],
            'billing_address2' => ['nullable', 'string', 'max:255'],
            'billing_city' => ['nullable', 'string', 'max:120', 'required_with:billing_address,billing_postcode,billing_country,billing_state'],
            'billing_state' => ['nullable', 'string', 'max:120', 'required_with:billing_address,billing_city,billing_postcode,billing_country'],
            'billing_postcode' => ['nullable', 'string', 'max:40', 'required_with:billing_address,billing_city,billing_country,billing_state'],
            'billing_country' => ['nullable', 'string', 'max:120', 'required_with:billing_address,billing_city,billing_postcode,billing_state'],
            'shipping_address' => ['nullable', 'string', 'max:255', 'required_with:shipping_city,shipping_postcode,shipping_country,shipping_state'],
            'shipping_address2' => ['nullable', 'string', 'max:255'],
            'shipping_city' => ['nullable', 'string', 'max:120', 'required_with:shipping_address,shipping_postcode,shipping_country,shipping_state'],
            'shipping_state' => ['nullable', 'string', 'max:120', 'required_with:shipping_address,shipping_city,shipping_postcode,shipping_country'],
            'shipping_postcode' => ['nullable', 'string', 'max:40', 'required_with:shipping_address,shipping_city,shipping_country,shipping_state'],
            'shipping_country' => ['nullable', 'string', 'max:120', 'required_with:shipping_address,shipping_city,shipping_postcode,shipping_state'],
            'shipping_same_billing' => ['nullable', 'boolean'],
            'account_terms_days' => ['nullable', 'integer', Rule::in(User::ACCOUNT_TERMS_OPTIONS)],
            'invoice_email_to' => ['nullable', 'string', 'max:2000', 'required_with:invoice_email_subject,invoice_email_message'],
            'invoice_email_cc' => ['nullable', 'string', 'max:2000'],
            'invoice_email_subject' => ['nullable', 'string', 'max:255', 'required_with:invoice_email_to,invoice_email_message'],
            'invoice_email_message' => ['nullable', 'string', 'max:10000', 'required_with:invoice_email_to,invoice_email_subject'],
            'notes' => ['nullable', 'string'],
            'contact_ids' => ['nullable', 'array'],
            'contact_ids.*' => ['uuid', 'exists:users,id'],
        ]);

        if ((bool) ($validated['shipping_same_billing'] ?? false)) {
            foreach (['address', 'address2', 'city', 'state', 'postcode', 'country'] as $field) {
                $validated['shipping_'.$field] = $validated['billing_'.$field] ?? null;
            }
        }
        unset($validated['shipping_same_billing']);

        $domains = collect(preg_split('/[,\s]+/', mb_strtolower((string) ($validated['auto_approved_email_domains_text'] ?? ''))) ?: [])
            ->map(fn (string $domain): string => trim($domain, " .\t\n\r\0\x0B"))
            ->filter()
            ->unique()
            ->values();
        foreach ($domains as $domain) {
            if (! preg_match('/^(?=.{3,253}$)[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)+$/', $domain)) {
                throw ValidationException::withMessages([
                    'auto_approved_email_domains_text' => 'Enter email domains such as trc.qld.gov.au, separated by spaces.',
                ]);
            }
        }
        $validated['auto_approved_email_domains'] = $domains->all() ?: null;
        unset($validated['auto_approved_email_domains_text']);

        return $validated;
    }
}
