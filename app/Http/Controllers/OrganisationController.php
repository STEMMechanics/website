<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use App\Models\User;
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
            ->with('organisations')
            ->whereNotNull('email_verified_at')
            ->where(function ($query) use ($search): void {
                $query->where('firstname', 'like', '%'.$search.'%')
                    ->orWhere('surname', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('company', 'like', '%'.$search.'%');
            })
            ->orderBy('firstname')
            ->orderBy('surname')
            ->limit(25)
            ->get()
            ->map(function (User $user): array {
                $organisation = $user->organisations
                    ->sortByDesc(function (Organisation $organisation): bool {
                        $pivot = $organisation->getAttribute('pivot');

                        return (bool) ($pivot?->getAttribute('is_primary') ?? false);
                    })
                    ->first();
                $organisationId = $organisation instanceof Organisation ? (string) $organisation->id : '';
                $organisationName = $organisation instanceof Organisation ? (string) $organisation->name : '';

                return [
                    'id' => (string) $user->id,
                    'name' => $user->getName(),
                    'email' => (string) $user->email,
                    'company' => (string) ($user->company ?? ''),
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
        $organisation->load(['contacts', 'parent', 'children'])
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

        return redirect()->route('admin.organisation.edit', $organisation)
            ->with('message', 'Organisation has been updated')
            ->with('message-title', 'Organisation updated')
            ->with('message-type', 'success');
    }

    public function destroy(Organisation $organisation): RedirectResponse
    {
        $organisation->delete();

        return redirect()->route('admin.organisation.index')
            ->with('message', 'Organisation has been deleted')
            ->with('message-title', 'Organisation deleted')
            ->with('message-type', 'danger');
    }

    private function formView(?Organisation $organisation = null): View
    {
        $organisations = Organisation::query()
            ->when($organisation, fn ($query) => $query->where('id', '!=', $organisation->id))
            ->orderBy('name')
            ->get();

        return view('admin.organisation.edit', compact('organisation', 'organisations'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Organisation $organisation = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(array_keys(Organisation::TYPES))],
            'parent_id' => [
                'nullable',
                'exists:organisations,id',
                Rule::notIn(array_filter([$organisation?->id])),
            ],
            'notes' => ['nullable', 'string'],
            'contact_ids' => ['nullable', 'array'],
            'contact_ids.*' => ['uuid', 'exists:users,id'],
        ]);
    }
}
