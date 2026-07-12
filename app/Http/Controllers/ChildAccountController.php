<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\SiteOption;
use App\Models\User;
use App\Rules\UsernameRule;
use App\Support\UserAnonymizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ChildAccountController extends Controller
{
    public function __construct(
        private readonly UserAnonymizer $userAnonymizer
    ) {}

    public function index(Request $request): View
    {
        $parent = $request->user();
        abort_unless($parent && $parent->isFullAccount(), 403);

        $childAccounts = $this->managedChildAccounts($parent);

        return view('account.children.index', [
            'childAccounts' => $childAccounts,
            'childAccountsEnabled' => SiteOption::booleanValue('users.child-accounts-enabled', true),
        ]);
    }

    public function create(Request $request): View
    {
        $parent = $request->user();
        abort_unless($parent && $parent->isFullAccount(), 403);
        abort_unless(SiteOption::booleanValue('users.child-accounts-enabled', true), 404);

        return view('account.children.edit', [
            'child' => new User([
                'child_can_select_avatar_media' => true,
                'child_can_use_avatar_camera' => true,
            ]),
            'isNew' => true,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $parent = $request->user();
        abort_unless($parent && $parent->isFullAccount(), 403);
        abort_unless(SiteOption::booleanValue('users.child-accounts-enabled', true), 404);

        $validated = $request->validate([
            'username' => ['required', 'string', 'max:32', 'unique:users,username', new UsernameRule(false)],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'child_can_select_avatar_media' => ['nullable', 'boolean'],
            'child_can_use_avatar_camera' => ['nullable', 'boolean'],
            ...$this->avatarValidationRules($parent),
        ]);

        $child = new User();
        $child->parent_user_id = (string) $parent->id;
        $child->username = User::normalizeUsername((string) $validated['username']);
        $child->password = (string) $validated['password'];
        $child->email = null;
        $child->email_verified_at = null;
        if (User::hasDatabaseColumn('child_can_select_avatar_media')) {
            $child->child_can_select_avatar_media = $request->boolean('child_can_select_avatar_media', true);
        }
        if (User::hasDatabaseColumn('child_can_use_avatar_camera')) {
            $child->child_can_use_avatar_camera = $request->boolean('child_can_use_avatar_camera', true);
        }
        if (
            User::hasDatabaseColumn('child_can_select_avatar_media')
            && User::hasDatabaseColumn('child_can_use_avatar_camera')
            && ! $child->child_can_select_avatar_media
        ) {
            $child->child_can_use_avatar_camera = false;
        }
        $this->fillAvatarSettings($child, $validated);
        $child->save();

        session()->flash('message', 'Child account created.');
        session()->flash('message-title', 'Child account saved');
        session()->flash('message-type', 'success');

        return redirect()->route('account.children.index');
    }

    public function edit(Request $request, User $child): View
    {
        $parent = $request->user();
        $this->authorizeChildManagement($parent, $child);

        return view('account.children.edit', [
            'child' => $child,
            'isNew' => false,
        ]);
    }

    public function update(Request $request, User $child): RedirectResponse
    {
        $parent = $request->user();
        $this->authorizeChildManagement($parent, $child);

        $validated = $request->validate([
            'username' => ['required', 'string', 'max:32', 'unique:users,username,'.$child->id, new UsernameRule(false)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'child_can_select_avatar_media' => ['nullable', 'boolean'],
            'child_can_use_avatar_camera' => ['nullable', 'boolean'],
            ...$this->avatarValidationRules($parent),
        ]);

        $child->username = User::normalizeUsername((string) $validated['username']);
        if (User::hasDatabaseColumn('child_can_select_avatar_media')) {
            $child->child_can_select_avatar_media = $request->boolean('child_can_select_avatar_media');
        }
        if (User::hasDatabaseColumn('child_can_use_avatar_camera')) {
            $child->child_can_use_avatar_camera = $request->boolean('child_can_use_avatar_camera');
        }
        if (
            User::hasDatabaseColumn('child_can_select_avatar_media')
            && User::hasDatabaseColumn('child_can_use_avatar_camera')
            && ! $child->child_can_select_avatar_media
        ) {
            $child->child_can_use_avatar_camera = false;
        }
        $this->fillAvatarSettings($child, $validated);

        if (trim((string) ($validated['password'] ?? '')) !== '') {
            $child->password = (string) $validated['password'];
        }

        $child->save();

        session()->flash('message', 'Child account updated.');
        session()->flash('message-title', 'Child account saved');
        session()->flash('message-type', 'success');

        return redirect()->route('account.children.index');
    }

    public function destroy(Request $request, User $child): RedirectResponse
    {
        $parent = $request->user();
        $this->authorizeChildManagement($parent, $child);
        $remainingChildCount = $parent?->children()
            ->whereNull('anonymized_at')
            ->whereKeyNot((string) $child->id)
            ->count() ?? 0;

        $this->userAnonymizer->anonymize($child, false);

        session()->flash('message', 'Child account deleted.');
        session()->flash('message-title', 'Child account removed');
        session()->flash('message-type', 'success');

        return redirect()->route($remainingChildCount > 0 ? 'account.children.index' : 'account.show');
    }

    private function authorizeChildManagement(?User $parent, User $child): void
    {
        abort_unless(
            $parent && $parent->canManageChildAccount($child) && ! $child->isAnonymized(),
            403
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function avatarValidationRules(User $parent): array
    {
        return [
            'avatar_mode' => ['nullable', 'string', Rule::in(User::avatarModes())],
            'avatar_letters' => ['nullable', 'string', 'max:3'],
            'avatar_icon_class' => ['nullable', 'string', Rule::in(User::avatarIconOptions())],
            'avatar_background_color' => ['nullable', 'string', 'max:7'],
            'avatar_media_name' => [
                'nullable',
                'string',
                'exists:media,name',
                function (string $attribute, mixed $value, \Closure $fail) use ($parent): void {
                    $mediaName = trim((string) $value);
                    if ($mediaName === '') {
                        return;
                    }

                    $media = Media::query()->find($mediaName);
                    if (! $media) {
                        return;
                    }

                    if (! $parent->isAdmin() && (string) $media->user_id !== (string) $parent->id) {
                        $fail('You can only use media that you uploaded for this avatar.');
                    }
                },
            ],
            'avatar_zoom' => ['nullable', 'integer', 'min:100', 'max:250'],
            'avatar_offset_x' => ['nullable', 'integer', 'min:-50', 'max:50'],
            'avatar_offset_y' => ['nullable', 'integer', 'min:-50', 'max:50'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function fillAvatarSettings(User $child, array $validated): void
    {
        if (User::hasDatabaseColumn('avatar_mode')) {
            $child->avatar_mode = User::normalizeAvatarMode((string) ($validated['avatar_mode'] ?? ''));
        }
        if (User::hasDatabaseColumn('avatar_letters')) {
            $child->avatar_letters = User::normalizeAvatarLetters((string) ($validated['avatar_letters'] ?? ''));
        }
        if (User::hasDatabaseColumn('avatar_icon_class')) {
            $child->avatar_icon_class = User::normalizeAvatarIconClass((string) ($validated['avatar_icon_class'] ?? ''));
        }
        if (User::hasDatabaseColumn('avatar_background_color')) {
            $child->avatar_background_color = User::normalizeAvatarBackgroundColor((string) ($validated['avatar_background_color'] ?? ''));
        }
        if (User::hasDatabaseColumn('avatar_media_name')) {
            $child->avatar_media_name = trim((string) ($validated['avatar_media_name'] ?? '')) ?: null;
        }
        if (User::hasDatabaseColumn('avatar_zoom')) {
            $child->avatar_zoom = (int) ($validated['avatar_zoom'] ?? 100);
        }
        if (User::hasDatabaseColumn('avatar_offset_x')) {
            $child->avatar_offset_x = (int) ($validated['avatar_offset_x'] ?? 0);
        }
        if (User::hasDatabaseColumn('avatar_offset_y')) {
            $child->avatar_offset_y = (int) ($validated['avatar_offset_y'] ?? 0);
        }

        if (
            User::hasDatabaseColumn('avatar_mode')
            && User::hasDatabaseColumn('avatar_media_name')
            && $child->avatar_mode === User::AVATAR_MODE_MEDIA
            && $child->avatar_media_name === null
        ) {
            $child->avatar_mode = $child->avatar_icon_class ? User::AVATAR_MODE_ICON : User::AVATAR_MODE_LETTERS;
        }

        if (User::hasDatabaseColumn('avatar_media_name') && $child->avatar_media_name === null) {
            if (User::hasDatabaseColumn('avatar_zoom')) {
                $child->avatar_zoom = 100;
            }
            if (User::hasDatabaseColumn('avatar_offset_x')) {
                $child->avatar_offset_x = 0;
            }
            if (User::hasDatabaseColumn('avatar_offset_y')) {
                $child->avatar_offset_y = 0;
            }
        }
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    private function managedChildAccounts(User $parent)
    {
        return $parent->children()
            ->whereNull('anonymized_at')
            ->orderBy('username')
            ->get();
    }
}
