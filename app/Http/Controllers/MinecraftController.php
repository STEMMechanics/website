<?php

namespace App\Http\Controllers;

use App\Models\MinecraftAccount;
use App\Models\MinecraftBlacklistEntry;
use App\Models\MinecraftPenalty;
use App\Models\MinecraftWebhookLog;
use App\Models\SiteOption;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\MinecraftRconService;
use App\Services\MinecraftSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MinecraftController extends Controller
{
    public function __construct(
        private readonly MinecraftSyncService $minecraftSyncService
    ) {}

    public function accountIndex(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureMinecraftAccess($user);

        $accounts = $user->minecraftAccounts()
            ->with(['sessions', 'penalties', 'blacklistEntries'])
            ->orderBy('username')
            ->get();

        return view('account.stemcraft.index', [
            'accounts' => $accounts,
        ]);
    }

    public function accountStore(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureMinecraftAccess($user);

        $validated = $request->validate([
            'platform' => ['required', Rule::in(MinecraftAccount::PLATFORMS)],
            'username' => ['required', 'string', 'max:80'],
        ]);

        $account = $this->saveMinecraftAccount(
            owner: $user,
            attributes: $validated,
            account: null,
            forceWhitelist: true,
        );

        $this->minecraftSyncService->syncAccountState($account);

        session()->flash('message', 'STEMCraft account saved and whitelisted. It may take a few minutes to synchronize with the server.');
        session()->flash('message-title', 'STEMCraft account saved');
        session()->flash('message-type', 'success');

        return redirect()->route('account.stemcraft.index');
    }

    public function accountDestroy(Request $request, MinecraftAccount $minecraftAccount): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureMinecraftAccess($user);

        if ((string) $minecraftAccount->user_id !== (string) $user->id) {
            abort(403);
        }

        $minecraftAccount->user_id = null;
        $minecraftAccount->is_whitelisted = false;
        $minecraftAccount->save();
        $this->minecraftSyncService->removeAccount($minecraftAccount);

        session()->flash('message', 'STEMCraft account removed from your profile and de-whitelisted.');
        session()->flash('message-title', 'STEMCraft account removed');
        session()->flash('message-type', 'success');

        return redirect()->route('account.stemcraft.index');
    }

    public function adminIndex(Request $request): View
    {
        $query = MinecraftAccount::query()
            ->with(['user', 'penalties', 'blacklistEntries'])
            ->orderBy('username');

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('uuid', 'like', '%'.$search.'%')
                    ->orWhere('username', 'like', '%'.$search.'%')
                    ->orWhere('platform', 'like', '%'.$search.'%')
                    ->orWhereHas('user', function ($userQuery) use ($search): void {
                        $userQuery->where('email', 'like', '%'.$search.'%')
                            ->orWhere('firstname', 'like', '%'.$search.'%')
                            ->orWhere('surname', 'like', '%'.$search.'%');
                    });
            });
        }

        if ($request->boolean('only_linked')) {
            $query->whereNotNull('user_id');
        }

        $accounts = $query->paginate(20)->onEachSide(1);

        return view('admin.stemcraft.index', [
            'accounts' => $accounts,
            'minecraftUsers' => $this->minecraftUsers(),
        ]);
    }

    public function adminPunishmentsIndex(Request $request): View
    {
        $activePenalties = MinecraftPenalty::query()
            ->with('account.user')
            ->whereIn('type', [MinecraftPenalty::TYPE_BAN, MinecraftPenalty::TYPE_MUTE])
            ->whereNull('lifted_at')
            ->orderByDesc('started_at')
            ->get()
            ->filter(fn (MinecraftPenalty $penalty): bool => $penalty->isActiveRestriction())
            ->values();

        $legacyActiveBans = MinecraftBlacklistEntry::query()
            ->with('account.user')
            ->orderByDesc('starts_at')
            ->get()
            ->filter(fn (MinecraftBlacklistEntry $entry): bool => $entry->isActive())
            ->values();

        $recentPenalties = MinecraftPenalty::query()
            ->with('account.user')
            ->orderByDesc('started_at')
            ->paginate(20)
            ->onEachSide(1);

        $savedUsernames = MinecraftAccount::query()
            ->orderBy('username')
            ->pluck('username')
            ->filter(fn (?string $username): bool => trim((string) $username) !== '')
            ->values()
            ->all();

        return view('admin.stemcraft.punishments', [
            'activePenalties' => $activePenalties,
            'legacyActiveBans' => $legacyActiveBans,
            'recentPenalties' => $recentPenalties,
            'savedUsernames' => $savedUsernames,
        ]);
    }

    public function adminWebhooksIndex(Request $request): View
    {
        $query = MinecraftWebhookLog::query()
            ->with('retriedFrom')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $search = trim((string) $request->query('search', ''));
        $direction = trim((string) $request->query('direction', ''));
        $status = trim((string) $request->query('status', ''));

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('event', 'like', '%'.$search.'%')
                    ->orWhere('delivery_id', 'like', '%'.$search.'%')
                    ->orWhere('target_url', 'like', '%'.$search.'%')
                    ->orWhere('error_message', 'like', '%'.$search.'%')
                    ->orWhere('raw_body', 'like', '%'.$search.'%');
            });
        }

        if (in_array($direction, [MinecraftWebhookLog::DIRECTION_INBOUND, MinecraftWebhookLog::DIRECTION_OUTBOUND], true)) {
            $query->where('direction', $direction);
        }

        $allowedStatuses = [
            MinecraftWebhookLog::STATUS_QUEUED,
            MinecraftWebhookLog::STATUS_PENDING,
            MinecraftWebhookLog::STATUS_DELIVERED,
            MinecraftWebhookLog::STATUS_FAILED,
            MinecraftWebhookLog::STATUS_RECEIVED,
            MinecraftWebhookLog::STATUS_IGNORED,
            MinecraftWebhookLog::STATUS_REJECTED,
            MinecraftWebhookLog::STATUS_DUPLICATE,
        ];
        if (in_array($status, $allowedStatuses, true)) {
            $query->where('status', $status);
        }

        return view('admin.stemcraft.webhooks', [
            'webhookLogs' => $query->paginate(30)->onEachSide(1),
            'search' => $search,
            'selectedDirection' => $direction,
            'selectedStatus' => $status,
        ]);
    }

    public function adminRconIndex(): View
    {
        $host = trim((string) SiteOption::value('minecraft.rcon-host', SiteOption::defaultValue('minecraft.rcon-host')));
        $port = (int) SiteOption::value('minecraft.rcon-port', SiteOption::defaultValue('minecraft.rcon-port'));

        return view('admin.stemcraft.rcon', [
            'configuredHost' => $host !== '' ? $host : 'Not configured',
            'configuredPort' => $port > 0 ? $port : null,
            'lastCommand' => (string) session('minecraft_rcon.command', ''),
            'lastOutput' => session('minecraft_rcon.output'),
            'lastError' => session('minecraft_rcon.error'),
        ]);
    }

    public function adminRconExecute(Request $request, MinecraftRconService $minecraftRconService): RedirectResponse
    {
        $validated = $request->validate([
            'command' => ['required', 'string', 'max:1000'],
        ]);

        $command = trim((string) $validated['command']);
        if ($command === '') {
            throw ValidationException::withMessages([
                'command' => 'Command is required.',
            ]);
        }

        try {
            $output = $minecraftRconService->execute($command);

            session()->flash('message', 'RCON command sent successfully.');
            session()->flash('message-title', 'STEMCraft RCON');
            session()->flash('message-type', 'success');
            session()->flash('minecraft_rcon.command', $command);
            session()->flash('minecraft_rcon.output', $output !== '' ? $output : '(No response text returned.)');
            session()->flash('minecraft_rcon.error', null);
        } catch (\Throwable $exception) {
            session()->flash('message', 'RCON command failed: '.$exception->getMessage());
            session()->flash('message-title', 'STEMCraft RCON');
            session()->flash('message-type', 'danger');
            session()->flash('minecraft_rcon.command', $command);
            session()->flash('minecraft_rcon.output', null);
            session()->flash('minecraft_rcon.error', $exception->getMessage());
        }

        return redirect()->route('admin.stemcraft.rcon.index');
    }

    public function adminWebhookRetry(MinecraftWebhookLog $minecraftWebhookLog): RedirectResponse
    {
        abort_unless($minecraftWebhookLog->direction === MinecraftWebhookLog::DIRECTION_OUTBOUND, 422);
        abort_unless($minecraftWebhookLog->status === MinecraftWebhookLog::STATUS_FAILED, 422);

        $retryLog = $this->minecraftSyncService->redeliverLog($minecraftWebhookLog);

        session()->flash('message', 'STEMCraft webhook queued for retry.');
        session()->flash('message-title', 'Webhook retry queued');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.stemcraft.webhooks.index', [
            'highlight' => $retryLog?->id,
        ]);
    }

    public function adminCreate(): View
    {
        return view('admin.stemcraft.edit', [
            'account' => new MinecraftAccount(['is_whitelisted' => true]),
            'minecraftUsers' => $this->minecraftUsers(),
            'editing' => false,
            'recentSessions' => collect(),
            'recentPenalties' => collect(),
            'recentBlacklistEntries' => collect(),
        ]);
    }

    public function adminStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'string', 'exists:users,id'],
            'platform' => ['required', Rule::in(MinecraftAccount::PLATFORMS)],
            'username' => ['required', 'string', 'max:80'],
            'is_whitelisted' => ['nullable', 'boolean'],
            'admin_notes' => ['nullable', 'string'],
        ]);

        $owner = null;
        $userId = trim((string) ($validated['user_id'] ?? ''));
        if ($userId !== '') {
            $owner = User::query()->findOrFail($userId);
        }

        $account = $this->saveMinecraftAccount(
            owner: $owner,
            attributes: $validated,
            account: null,
            forceWhitelist: $request->boolean('is_whitelisted'),
        );

        $this->minecraftSyncService->syncAccountState($account);

        session()->flash('message', 'STEMCraft account created.');
        session()->flash('message-title', 'STEMCraft account added');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.stemcraft.index', [
            'highlight' => $account->id,
        ]);
    }

    public function adminEdit(MinecraftAccount $minecraftAccount): View
    {
        return view('admin.stemcraft.edit', [
            'account' => $minecraftAccount->load('user'),
            'minecraftUsers' => $this->minecraftUsers(),
            'editing' => true,
            'recentSessions' => $minecraftAccount->sessions()->limit(25)->get(),
            'recentPenalties' => $minecraftAccount->penalties()->limit(25)->get(),
            'recentBlacklistEntries' => $minecraftAccount->blacklistEntries()->limit(25)->get(),
        ]);
    }

    public function adminUpdate(Request $request, MinecraftAccount $minecraftAccount): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'string', 'exists:users,id'],
            'platform' => ['required', Rule::in(MinecraftAccount::PLATFORMS)],
            'username' => ['required', 'string', 'max:80'],
            'is_whitelisted' => ['nullable', 'boolean'],
            'admin_notes' => ['nullable', 'string'],
        ]);

        $owner = null;
        $userId = trim((string) ($validated['user_id'] ?? ''));
        if ($userId !== '') {
            $owner = User::query()->findOrFail($userId);
        }

        $account = $this->saveMinecraftAccount(
            owner: $owner,
            attributes: $validated,
            account: $minecraftAccount,
            forceWhitelist: $request->boolean('is_whitelisted'),
        );

        $this->minecraftSyncService->syncAccountState($account);

        session()->flash('message', 'STEMCraft account updated.');
        session()->flash('message-title', 'STEMCraft account updated');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.stemcraft.edit', $account);
    }

    public function adminBlacklistStore(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'username' => ['required', 'string', 'max:80'],
            'reason' => ['nullable', 'string'],
            'ends_at' => ['nullable', 'date', 'after:now'],
            'is_permanent' => ['nullable', 'boolean'],
        ]);

        $username = trim((string) $validated['username']);
        $account = MinecraftAccount::query()
            ->where('username', $username)
            ->orderByRaw('user_id IS NULL')
            ->orderByDesc('is_whitelisted')
            ->first();
        $uuid = trim((string) ($account !== null ? $account->uuid : ''));

        $entry = MinecraftBlacklistEntry::query()->create([
            'minecraft_account_id' => $account?->id,
            'uuid' => $uuid !== '' ? strtolower($uuid) : null,
            'username' => $username,
            'reason' => trim((string) ($validated['reason'] ?? '')),
            'starts_at' => now(),
            'ends_at' => ! $request->boolean('is_permanent') && ! empty($validated['ends_at']) ? $validated['ends_at'] : null,
            'is_permanent' => $request->boolean('is_permanent'),
            'created_by' => (string) $user->id,
        ]);

        $this->minecraftSyncService->syncBlacklist($entry);

        session()->flash('message', 'STEMCraft ban added.');
        session()->flash('message-title', 'Ban updated');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.stemcraft.index');
    }

    public function adminBlacklistLift(Request $request, MinecraftBlacklistEntry $minecraftBlacklistEntry): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $minecraftBlacklistEntry->lifted_at = now();
        $minecraftBlacklistEntry->lifted_by = (string) $user->id;
        $minecraftBlacklistEntry->save();
        $this->minecraftSyncService->removeBlacklist($minecraftBlacklistEntry);

        session()->flash('message', 'STEMCraft ban removed.');
        session()->flash('message-title', 'Ban updated');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.stemcraft.punishments.index');
    }

    public function adminPunishmentStore(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'username' => ['required', 'string', 'max:80'],
            'type' => ['required', Rule::in(MinecraftPenalty::TYPES)],
            'reason' => ['nullable', 'string'],
            'ends_at' => ['nullable', 'date', 'after:now'],
        ]);

        $type = (string) $validated['type'];
        $username = trim((string) $validated['username']);
        $account = $this->findMinecraftAccountByUsername($username);
        $uuid = trim((string) ($account?->uuid ?? ''));
        $startedAt = now();
        $isPermanent = $type !== MinecraftPenalty::TYPE_KICK && empty($validated['ends_at']);
        $endsAt = $type === MinecraftPenalty::TYPE_KICK
            ? $startedAt
            : (! empty($validated['ends_at']) ? \Illuminate\Support\Carbon::parse((string) $validated['ends_at']) : null);
        $durationSeconds = ($type === MinecraftPenalty::TYPE_KICK || $isPermanent || $endsAt === null)
            ? null
            : max(1, $startedAt->diffInSeconds($endsAt));

        $penalty = MinecraftPenalty::query()->create([
            'minecraft_account_id' => $account?->id,
            'external_id' => (string) Str::uuid(),
            'uuid' => $uuid !== '' ? strtolower($uuid) : null,
            'username' => $username,
            'type' => $type,
            'reason' => trim((string) ($validated['reason'] ?? '')) ?: null,
            'duration_seconds' => $durationSeconds,
            'started_at' => $startedAt,
            'ends_at' => $endsAt,
            'is_permanent' => $type === MinecraftPenalty::TYPE_KICK ? false : $isPermanent,
            'by_uuid' => null,
            'by_username' => $user->getName(),
        ]);

        $this->minecraftSyncService->syncPenalty($penalty);

        session()->flash('message', 'STEMCraft punishment applied.');
        session()->flash('message-title', 'Punishment updated');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.stemcraft.punishments.index');
    }

    public function adminPunishmentLift(Request $request, MinecraftPenalty $minecraftPenalty): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless(in_array($minecraftPenalty->type, [MinecraftPenalty::TYPE_BAN, MinecraftPenalty::TYPE_MUTE], true), 422);
        abort_unless($minecraftPenalty->isActiveRestriction(), 422);

        $minecraftPenalty->lifted_at = now();
        $minecraftPenalty->lifted_by_uuid = null;
        $minecraftPenalty->lifted_by_username = $user->getName();
        $minecraftPenalty->save();

        $this->minecraftSyncService->liftPenalty($minecraftPenalty);

        session()->flash('message', 'STEMCraft punishment lifted.');
        session()->flash('message-title', 'Punishment updated');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.stemcraft.punishments.index');
    }

    private function ensureMinecraftAccess(User $user): void
    {
        abort_unless($user->hasMinecraftAccess(), 403);
    }

    private function minecraftUsers(): Collection
    {
        return User::query()
            ->orderBy('firstname')
            ->orderBy('surname')
            ->orderBy('email')
            ->get();
    }

    private function findMinecraftAccountByUsername(string $username): ?MinecraftAccount
    {
        return MinecraftAccount::query()
            ->where('username', $username)
            ->orderByRaw('uuid IS NULL')
            ->orderByRaw('user_id IS NULL')
            ->orderByDesc('is_whitelisted')
            ->first();
    }

    private function saveMinecraftAccount(?User $owner, array $attributes, ?MinecraftAccount $account, ?bool $forceWhitelist = null): MinecraftAccount
    {
        $platform = strtolower(trim((string) $attributes['platform']));
        $username = trim((string) $attributes['username']);

        if ($account === null) {
            $matched = MinecraftAccount::query()
                ->where(function ($query) use ($platform, $username): void {
                    $query->where('platform', $platform)
                        ->where('username', $username);
                })
                ->first();

            if ($matched && ($matched->user_id === null || (string) $matched->user_id === (string) $owner?->id)) {
                $account = $matched;
            }
        }

        $account ??= new MinecraftAccount();

        $usernameConflict = MinecraftAccount::query()
            ->where('platform', $platform)
            ->where('username', $username)
            ->when($account->exists, fn ($query) => $query->where('id', '!=', $account->id))
            ->first();
        if ($usernameConflict) {
            throw ValidationException::withMessages([
                'username' => 'That Minecraft username is already linked to another record for this platform.',
            ]);
        }

        $account->user_id = $owner?->id;
        $account->platform = $platform;
        $account->username = $username;
        $account->is_whitelisted = $forceWhitelist ?? $account->is_whitelisted ?? ($owner !== null);
        if (array_key_exists('admin_notes', $attributes)) {
            $account->admin_notes = trim((string) ($attributes['admin_notes'] ?? '')) ?: null;
        }
        $account->save();

        if ($owner !== null) {
            $owner->groups()->firstOrCreate([
                'slug' => UserGroup::normalizeSlug('minecraft'),
            ]);
        }

        return $account;
    }
}
