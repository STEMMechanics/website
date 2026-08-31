<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use App\Models\User;
use App\Services\InvoiceEmailTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

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
            ->paginate(20)
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
            ->with(['organisations', 'primaryOrganisation'])
            ->when(! $request->boolean('include_ghost'), fn ($query) => $query->whereNotNull('email_verified_at'))
            ->where(function ($query) use ($search): void {
                $query->where('firstname', 'like', '%'.$search.'%')
                    ->orWhere('surname', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhereHas('primaryOrganisation', fn ($query) => $query->where('name', 'like', '%'.$search.'%'));
            })
            ->orderBy('firstname')
            ->orderBy('surname')
            ->limit(25)
            ->get()
            ->map(function (User $user): array {
                $organisation = $user->primaryOrganisation ?? $user->organisations->first();
                $organisationId = $organisation instanceof Organisation ? (string) $organisation->id : '';
                $organisationName = $organisation instanceof Organisation ? (string) $organisation->name : '';

                return [
                    'id' => (string) $user->id,
                    'name' => $user->getName(),
                    'email' => (string) $user->email,
                    'is_anonymized' => $user->isAnonymized(),
                    'edit_url' => route('admin.user.edit', $user),
                    'organisation_id' => $organisationId,
                    'organisation_name' => $organisationName,
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

        $organisations = Organisation::query()
            ->with('parent')
            ->where('name', 'like', '%'.$search.'%')
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
        unset($validated['contact_ids']);
        $organisation = Organisation::create($validated);
        $organisation->contacts()->sync($contactIds);

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
        $contactIds = $validated['contact_ids'] ?? [];
        unset($validated['contact_ids']);
        $organisation->update($validated);
        $organisation->contacts()->sync($contactIds);

        User::query()
            ->where('primary_organisation_id', $organisation->id)
            ->when($contactIds !== [], fn ($query) => $query->whereNotIn('id', $contactIds))
            ->update(['primary_organisation_id' => null]);

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
        $organisations = Organisation::query()
            ->when($organisation, fn ($query) => $query->where('id', '!=', $organisation->id))
            ->orderBy('name')
            ->get();

        $invoiceEmailSiteDefaults = app(InvoiceEmailTemplateService::class)->organisationDefaults();

        return view('admin.organisation.edit', compact('organisation', 'organisations', 'invoiceEmailSiteDefaults'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Organisation $organisation = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(array_keys(Organisation::TYPES))],
            'parent_id' => [
                'nullable',
                'exists:organisations,id',
                Rule::notIn(array_filter([$organisation?->id])),
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

        return $validated;
    }
}
