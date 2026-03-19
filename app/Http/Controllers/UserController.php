<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserGroup;
use App\Rules\UsernameRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $showGhostUsers = $request->boolean('show_ghost');

        $query = User::query();

        if (! $showGhostUsers) {
            $query->where(function ($builder): void {
                $builder->whereNotNull('email_verified_at')
                    ->orWhereNotNull('parent_user_id');
            });
        }

        if ($request->has('search')) {
            $search = trim((string) $request->search);
            if ($search !== '') {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery->where('firstname', 'like', '%'.$search.'%')
                        ->orWhere('surname', 'like', '%'.$search.'%')
                        ->orWhere('company', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('username', 'like', '%'.$search.'%');
                });
            }
        }

        $users = $query
            ->with(['groups' => function ($groupQuery): void {
                $groupQuery->orderBy('slug');
            }])
            ->withCount('media')
            ->withSum('media', 'size')
            ->orderBy('created_at', 'desc')
            ->paginate(12)
            ->onEachSide(1);

        return view('admin.user.index', [
            'users' => $users,
            'showGhostUsers' => $showGhostUsers,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.user.create', [
            'groupSuggestions' => $this->groupSuggestions(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'firstname' => '',
            'surname' => '',
            'company' => 'nullable|string|max:255',
            'email' => 'email|unique:users',
            'username' => ['nullable', 'string', 'max:32', 'unique:users,username', new UsernameRule($this->groupsContainAdmin((string) $request->input('groups', '')))],
            'phone' => '',
            'subscribed' => 'nullable',
            'groups' => 'nullable|string|max:2000',

            'shipping_address' => 'required_with:shipping_city,shipping_postcode,shipping_country,shipping_state',
            'shipping_address2' => 'nullable|string|max:255',
            'shipping_city' => 'required_with:shipping_address,shipping_postcode,shipping_country,shipping_state',
            'shipping_postcode' => 'required_with:shipping_address,shipping_city,shipping_country,shipping_state',
            'shipping_country' => 'required_with:shipping_address,shipping_city,shipping_postcode,shipping_state',
            'shipping_state' => 'required_with:shipping_address,shipping_city,shipping_postcode,shipping_country',

            'billing_address' => 'required_with:billing_city,billing_postcode,billing_country,billing_state',
            'billing_address2' => 'nullable|string|max:255',
            'billing_city' => 'required_with:billing_address,billing_postcode,billing_country,billing_state',
            'billing_postcode' => 'required_with:billing_address,billing_city,billing_country,billing_state',
            'billing_country' => 'required_with:billing_address,billing_city,billing_postcode,billing_state',
            'billing_state' => 'required_with:billing_address,billing_city,billing_postcode,billing_country',
        ], [
            'firstname.required' => __('validation.custom_messages.firstname_required'),
            'surname.required' => __('validation.custom_messages.surname_required'),
            'email.required' => __('validation.custom_messages.email_required'),
            'email.email' => __('validation.custom_messages.email_invalid'),
            'phone.required' => __('validation.custom_messages.phone_required'),

            'shipping_address.required' => __('validation.custom_messages.shipping_address_required'),
            'shipping_city.required' => __('validation.custom_messages.shipping_city_required'),
            'shipping_postcode.required' => __('validation.custom_messages.shipping_postcode_required'),
            'shipping_country.required' => __('validation.custom_messages.shipping_country_required'),
            'shipping_state.required' => __('validation.custom_messages.shipping_state_required'),

            'billing_address.required' => __('validation.custom_messages.billing_address_required'),
            'billing_city.required' => __('validation.custom_messages.billing_city_required'),
            'billing_postcode.required' => __('validation.custom_messages.billing_postcode_required'),
            'billing_country.required' => __('validation.custom_messages.billing_country_required'),
            'billing_state.required' => __('validation.custom_messages.billing_state_required'),
        ]);

        $payload = $this->userPayloadFromValidated($validated);
        $email = trim((string) ($payload['email'] ?? ''));
        $payload['username'] = User::ensureUniqueUsername(
            (string) ($validated['username'] ?? ''),
            $email,
            $this->groupsContainAdmin((string) ($validated['groups'] ?? ''))
        );
        $payload['email_verified_at'] = $email !== '' ? now() : null;
        $payload['subscribed'] = ($request->input('subscribed') === 'on');

        $user = User::create($payload);
        $this->syncGroups($user, (string) ($validated['groups'] ?? ''));

        session()->flash('message', 'User has been created');
        session()->flash('message-title', 'User created');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.user.index');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        return view('admin.user.edit', [
            'user' => $user->load('groups'),
            'groupSuggestions' => $this->groupSuggestions(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'firstname' => '',
            'surname' => '',
            'company' => 'nullable|string|max:255',
            'email' => ['email', Rule::unique('users')->ignore($user->id)],
            'username' => ['required', 'string', 'max:32', Rule::unique('users', 'username')->ignore($user->id), new UsernameRule($this->groupsContainAdmin((string) $request->input('groups', '')))],
            'phone' => '',
            'subscribed' => 'nullable',
            'groups' => 'nullable|string|max:2000',

            'shipping_address' => 'required_with:shipping_city,shipping_postcode,shipping_country,shipping_state',
            'shipping_address2' => 'nullable|string|max:255',
            'shipping_city' => 'required_with:shipping_address,shipping_postcode,shipping_country,shipping_state',
            'shipping_postcode' => 'required_with:shipping_address,shipping_city,shipping_country,shipping_state',
            'shipping_country' => 'required_with:shipping_address,shipping_city,shipping_postcode,shipping_state',
            'shipping_state' => 'required_with:shipping_address,shipping_city,shipping_postcode,shipping_country',

            'billing_address' => 'required_with:billing_city,billing_postcode,billing_country,billing_state',
            'billing_address2' => 'nullable|string|max:255',
            'billing_city' => 'required_with:billing_address,billing_postcode,billing_country,billing_state',
            'billing_postcode' => 'required_with:billing_address,billing_city,billing_country,billing_state',
            'billing_country' => 'required_with:billing_address,billing_city,billing_postcode,billing_state',
            'billing_state' => 'required_with:billing_address,billing_city,billing_postcode,billing_country',
        ], [
            'firstname.required' => __('validation.custom_messages.firstname_required'),
            'surname.required' => __('validation.custom_messages.surname_required'),
            'email.required' => __('validation.custom_messages.email_required'),
            'email.email' => __('validation.custom_messages.email_invalid'),
            'phone.required' => __('validation.custom_messages.phone_required'),

            'shipping_address.required' => __('validation.custom_messages.shipping_address_required'),
            'shipping_city.required' => __('validation.custom_messages.shipping_city_required'),
            'shipping_postcode.required' => __('validation.custom_messages.shipping_postcode_required'),
            'shipping_country.required' => __('validation.custom_messages.shipping_country_required'),
            'shipping_state.required' => __('validation.custom_messages.shipping_state_required'),

            'billing_address.required' => __('validation.custom_messages.billing_address_required'),
            'billing_city.required' => __('validation.custom_messages.billing_city_required'),
            'billing_postcode.required' => __('validation.custom_messages.billing_postcode_required'),
            'billing_country.required' => __('validation.custom_messages.billing_country_required'),
            'billing_state.required' => __('validation.custom_messages.billing_state_required'),
        ]);

        $payload = $this->userPayloadFromValidated($validated);
        $email = trim((string) ($payload['email'] ?? ''));
        $payload['username'] = User::ensureUniqueUsername(
            (string) ($validated['username'] ?? ''),
            $email,
            $this->groupsContainAdmin((string) ($validated['groups'] ?? '')),
            (string) $user->id
        );
        $payload['email_verified_at'] = $email !== '' ? now() : null;
        $payload['subscribed'] = ($request->input('subscribed') === 'on');

        $user->update($payload);
        $this->syncGroups($user, (string) ($validated['groups'] ?? ''));

        session()->flash('message', 'User details have been updated');
        session()->flash('message-title', 'Details updated');
        session()->flash('message-type', 'success');

        return redirect()->back();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, User $user)
    {
        if ($user->id === '1') {
            return $this->respondDeleteBlocked(
                $request,
                'You cannot delete the main admin user.'
            );
        }

        $blockingCounts = [
            'workshops' => (int) DB::table('workshops')->where('user_id', (string) $user->id)->count(),
            'posts' => (int) DB::table('posts')->where('user_id', (string) $user->id)->count(),
        ];

        $blocking = collect($blockingCounts)
            ->filter(fn (int $count) => $count > 0)
            ->map(fn (int $count, string $label) => $count.' '.$label)
            ->values()
            ->all();

        if ($blocking !== []) {
            return $this->respondDeleteBlocked(
                $request,
                'User cannot be deleted because they own: '.implode(', ', $blocking).'. Reassign or remove those records first.'
            );
        }

        $user->delete();

        session()->flash('message', 'User has been deleted');
        session()->flash('message-title', 'User deleted');
        session()->flash('message-type', 'success');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'redirect' => route('admin.user.index'),
            ]);
        }

        return redirect()->route('admin.user.index');
    }

    private function respondDeleteBlocked(Request $request, string $message): JsonResponse|RedirectResponse
    {
        session()->flash('message', $message);
        session()->flash('message-title', 'User not deleted');
        session()->flash('message-type', 'danger');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'redirect' => route('admin.user.index'),
            ], 422);
        }

        return redirect()->route('admin.user.index');
    }

    public function storeInline(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'firstname' => ['nullable', 'string', 'max:120'],
            'surname' => ['nullable', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'username' => ['nullable', 'string', 'max:32', 'unique:users,username', new UsernameRule(false)],
            'phone' => ['nullable', 'string', 'max:120'],
            'shipping_address' => ['nullable', 'string', 'max:255', 'required_with:shipping_city,shipping_postcode,shipping_country,shipping_state'],
            'shipping_address2' => ['nullable', 'string', 'max:255'],
            'shipping_city' => ['nullable', 'string', 'max:120', 'required_with:shipping_address,shipping_postcode,shipping_country,shipping_state'],
            'shipping_postcode' => ['nullable', 'string', 'max:40', 'required_with:shipping_address,shipping_city,shipping_country,shipping_state'],
            'shipping_country' => ['nullable', 'string', 'max:120', 'required_with:shipping_address,shipping_city,shipping_postcode,shipping_state'],
            'shipping_state' => ['nullable', 'string', 'max:120', 'required_with:shipping_address,shipping_city,shipping_postcode,shipping_country'],
            'billing_address' => ['nullable', 'string', 'max:255', 'required_with:billing_city,billing_postcode,billing_country,billing_state'],
            'billing_address2' => ['nullable', 'string', 'max:255'],
            'billing_city' => ['nullable', 'string', 'max:120', 'required_with:billing_address,billing_postcode,billing_country,billing_state'],
            'billing_postcode' => ['nullable', 'string', 'max:40', 'required_with:billing_address,billing_city,billing_country,billing_state'],
            'billing_country' => ['nullable', 'string', 'max:120', 'required_with:billing_address,billing_city,billing_postcode,billing_state'],
            'billing_state' => ['nullable', 'string', 'max:120', 'required_with:billing_address,billing_city,billing_postcode,billing_country'],
        ]);

        $user = User::create([
            'firstname' => trim((string) ($validated['firstname'] ?? '')),
            'surname' => trim((string) ($validated['surname'] ?? '')),
            'company' => trim((string) ($validated['company'] ?? '')),
            'email' => trim((string) ($validated['email'] ?? '')),
            'username' => User::ensureUniqueUsername(
                (string) ($validated['username'] ?? ''),
                trim((string) ($validated['email'] ?? '')),
                false
            ),
            'phone' => trim((string) ($validated['phone'] ?? '')),
            'shipping_address' => trim((string) ($validated['shipping_address'] ?? '')),
            'shipping_address2' => trim((string) ($validated['shipping_address2'] ?? '')),
            'shipping_city' => trim((string) ($validated['shipping_city'] ?? '')),
            'shipping_postcode' => trim((string) ($validated['shipping_postcode'] ?? '')),
            'shipping_country' => trim((string) ($validated['shipping_country'] ?? '')),
            'shipping_state' => trim((string) ($validated['shipping_state'] ?? '')),
            'billing_address' => trim((string) ($validated['billing_address'] ?? '')),
            'billing_address2' => trim((string) ($validated['billing_address2'] ?? '')),
            'billing_city' => trim((string) ($validated['billing_city'] ?? '')),
            'billing_postcode' => trim((string) ($validated['billing_postcode'] ?? '')),
            'billing_country' => trim((string) ($validated['billing_country'] ?? '')),
            'billing_state' => trim((string) ($validated['billing_state'] ?? '')),
        ]);
        $user->email_verified_at = now();
        $user->save();

        $label = trim((string) $user->getName());
        if ((string) $user->company !== '') {
            $label .= ' - '.$user->company;
        }
        if ((string) $user->email !== '') {
            $label .= ' ('.$user->email.')';
        }

        return response()->json([
            'success' => true,
            'user' => [
                'id' => (string) $user->id,
                'label' => $label,
            ],
        ]);
    }

    private function userPayloadFromValidated(array $validated): array
    {
        return [
            'firstname' => trim((string) ($validated['firstname'] ?? '')),
            'surname' => trim((string) ($validated['surname'] ?? '')),
            'company' => trim((string) ($validated['company'] ?? '')),
            'email' => trim((string) ($validated['email'] ?? '')),
            'username' => trim((string) ($validated['username'] ?? '')),
            'phone' => trim((string) ($validated['phone'] ?? '')),
            'shipping_address' => trim((string) ($validated['shipping_address'] ?? '')),
            'shipping_address2' => trim((string) ($validated['shipping_address2'] ?? '')),
            'shipping_city' => trim((string) ($validated['shipping_city'] ?? '')),
            'shipping_postcode' => trim((string) ($validated['shipping_postcode'] ?? '')),
            'shipping_country' => trim((string) ($validated['shipping_country'] ?? '')),
            'shipping_state' => trim((string) ($validated['shipping_state'] ?? '')),
            'billing_address' => trim((string) ($validated['billing_address'] ?? '')),
            'billing_address2' => trim((string) ($validated['billing_address2'] ?? '')),
            'billing_city' => trim((string) ($validated['billing_city'] ?? '')),
            'billing_postcode' => trim((string) ($validated['billing_postcode'] ?? '')),
            'billing_country' => trim((string) ($validated['billing_country'] ?? '')),
            'billing_state' => trim((string) ($validated['billing_state'] ?? '')),
        ];
    }

    private function groupSuggestions(): array
    {
        return UserGroup::query()
            ->select('slug')
            ->distinct()
            ->orderBy('slug')
            ->pluck('slug')
            ->map(fn ($slug) => (string) $slug)
            ->filter(fn ($slug) => $slug !== '')
            ->values()
            ->all();
    }

    private function syncGroups(User $user, string $raw): void
    {
        $slugs = collect(preg_split('/[\s,]+/', $raw) ?: [])
            ->map(fn ($value) => UserGroup::normalizeSlug((string) $value))
            ->filter(fn ($slug) => $slug !== '')
            ->unique()
            ->values()
            ->all();

        $user->groups()->whereNotIn('slug', $slugs)->delete();

        foreach ($slugs as $slug) {
            $user->groups()->firstOrCreate([
                'slug' => $slug,
            ]);
        }
    }

    private function groupsContainAdmin(string $raw): bool
    {
        return collect(preg_split('/[\s,]+/', $raw) ?: [])
            ->map(fn ($value) => UserGroup::normalizeSlug((string) $value))
            ->contains('admin');
    }
}
