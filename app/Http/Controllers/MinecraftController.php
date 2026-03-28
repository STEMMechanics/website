<?php

namespace App\Http\Controllers;

use App\Helpers;
use App\Models\MinecraftAccount;
use App\Models\MinecraftBlacklistEntry;
use App\Models\MinecraftMessage;
use App\Models\MinecraftPenalty;
use App\Models\MinecraftWebhookLog;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\MinecraftWebhookBridgeService;
use App\Services\MinecraftSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MinecraftController extends Controller
{
    private const MAX_LINKED_ACCOUNTS = 5;

    public function __construct(
        private readonly MinecraftSyncService $minecraftSyncService
    ) {}

    public function accountIndex(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureMinecraftAccess($user);

        $accounts = $this->minecraftAccountsForViewer($user);

        return view('account.stemcraft.index', [
            'accounts' => $accounts,
            'canManageAccounts' => $user->canManageMinecraftAccounts(),
            'canCreateAccounts' => $user->canCreateMinecraftAccounts(),
            'ownerOptions' => $user->canManageMinecraftAccounts() ? $this->minecraftAccountOwnerOptions($user) : collect(),
        ]);
    }

    public function accountStore(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->canCreateMinecraftAccounts(), 403);

        $validated = $request->validate([
            'platform' => ['required', Rule::in(MinecraftAccount::PLATFORMS)],
            'username' => ['required', 'string', 'max:80'],
            'user_id' => ['nullable', 'string', 'max:36'],
        ]);

        $requestedOwnerId = trim((string) ($validated['user_id'] ?? ''));
        $owner = $this->resolveMinecraftAccountOwner($user, $requestedOwnerId);
        if ($requestedOwnerId !== '' && (string) $owner->id !== $requestedOwnerId) {
            return back()
                ->withErrors(['user_id' => 'Select yourself or one of your child accounts.'])
                ->withInput();
        }

        $this->enforceLinkedAccountLimit($owner, $validated);

        $account = $this->saveMinecraftAccount(
            owner: $owner,
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
        abort_unless($user->canManageMinecraftAccounts(), 403);

        if (! $this->canManageMinecraftAccountRecord($user, $minecraftAccount)) {
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

    public function accountUpdateOwner(Request $request, MinecraftAccount $minecraftAccount): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->canManageMinecraftAccounts(), 403);
        abort_unless($this->canManageMinecraftAccountRecord($user, $minecraftAccount), 403);

        $validated = $request->validate([
            'user_id' => ['nullable', 'string', 'max:36'],
        ]);

        $targetUserId = trim((string) ($validated['user_id'] ?? ''));
        $allowedUserIds = $this->managedMinecraftAccountOwnerIds($user);

        if ($targetUserId !== '' && ! in_array($targetUserId, $allowedUserIds, true)) {
            return back()
                ->withErrors(['user_id' => 'Select yourself or one of your child accounts.'])
                ->withInput();
        }

        if ($targetUserId === (string) $minecraftAccount->user_id) {
            session()->flash('message', 'Minecraft account ownership is already set.');
            session()->flash('message-title', 'No changes made');
            session()->flash('message-type', 'info');

            return redirect()->route('account.stemcraft.index');
        }

        $minecraftAccount->user_id = $targetUserId !== '' ? $targetUserId : null;
        $minecraftAccount->save();

        session()->flash('message', 'Minecraft account ownership updated.');
        session()->flash('message-title', 'STEMCraft account updated');
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
            ->with(['account.user', 'byUser', 'liftedByUser'])
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
            ->with(['account.user', 'byUser', 'liftedByUser'])
            ->orderByDesc('started_at')
            ->paginate(20)
            ->onEachSide(1);

        $savedAccounts = MinecraftAccount::query()
            ->orderBy('username')
            ->orderBy('platform')
            ->orderBy('uuid')
            ->get(['id', 'platform', 'uuid', 'username'])
            ->filter(fn (MinecraftAccount $account): bool => trim((string) $account->username) !== '')
            ->map(function (MinecraftAccount $account): array {
                return [
                    'id' => (string) $account->id,
                    'username' => (string) $account->username,
                    'label' => $this->formatPunishmentAccountOptionLabel($account),
                ];
            })
            ->values()
            ->all();
        $savedUsernames = collect($savedAccounts)
            ->pluck('label')
            ->unique()
            ->values()
            ->all();

        return view('admin.stemcraft.punishments', [
            'activePenalties' => $activePenalties,
            'legacyActiveBans' => $legacyActiveBans,
            'recentPenalties' => $recentPenalties,
            'savedAccounts' => $savedAccounts,
            'savedUsernames' => $savedUsernames,
        ]);
    }

    public function adminMessagesIndex(Request $request): View
    {
        return view('admin.stemcraft.messages', $this->buildAdminMessagesViewData($request));
    }

    public function adminMessagesSnapshot(Request $request): JsonResponse
    {
        $viewData = $this->buildAdminMessagesViewData($request);

        return response()->json([
            'resultsHtml' => view('admin.stemcraft.partials.messages-results', $viewData)->render(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function adminWebhooksIndex(Request $request): View
    {
        return view('admin.stemcraft.webhooks', $this->buildAdminWebhooksViewData($request));
    }

    public function adminWebhooksSnapshot(Request $request): JsonResponse
    {
        $viewData = $this->buildAdminWebhooksViewData($request);

        return response()->json([
            'resultsHtml' => view('admin.stemcraft.partials.webhooks-results', $viewData)->render(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function adminManagementIndex(MinecraftWebhookBridgeService $minecraftWebhookBridgeService): View
    {
        return view('admin.stemcraft.management', $this->buildAdminManagementViewData($minecraftWebhookBridgeService));
    }

    public function adminManagementSnapshot(MinecraftWebhookBridgeService $minecraftWebhookBridgeService): JsonResponse
    {
        $viewData = $this->buildAdminManagementViewData($minecraftWebhookBridgeService);

        return response()->json([
            'resultsHtml' => view('admin.stemcraft.partials.management-status', $viewData)->render(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function adminManagementExecute(Request $request, MinecraftWebhookBridgeService $minecraftWebhookBridgeService): RedirectResponse|JsonResponse
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
            $result = $minecraftWebhookBridgeService->requestCommand($command);
            $commandSucceeded = (bool) ($result['success'] ?? false);

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => true,
                    'command' => $command,
                    'result' => $result,
                ]);
            }

            session()->flash('message', $commandSucceeded ? 'Server command executed successfully.' : 'Server command returned an unsuccessful result.');
            session()->flash('message-title', 'STEMCraft Management');
            session()->flash('message-type', $commandSucceeded ? 'success' : 'danger');
            session()->flash('minecraft_management.command', $command);
            session()->flash('minecraft_management.command_result', $result);
            session()->flash('minecraft_management.command_error', null);
        } catch (\Throwable $exception) {
            session()->flash('message', 'Server command failed: '.$exception->getMessage());
            session()->flash('message-title', 'STEMCraft Management');
            session()->flash('message-type', 'danger');
            session()->flash('minecraft_management.command', $command);
            session()->flash('minecraft_management.command_result', null);
            session()->flash('minecraft_management.command_error', $exception->getMessage());

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'command' => $command,
                    'error' => $exception->getMessage(),
                ], 500);
            }
        }

        return redirect()->route('admin.stemcraft.management.index');
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
            'minecraft_account_id' => ['nullable', 'integer', 'exists:minecraft_accounts,id'],
            'username' => ['nullable', 'string', 'max:80', 'required_without:minecraft_account_id'],
            'type' => ['required', Rule::in(MinecraftPenalty::TYPES)],
            'reason' => ['nullable', 'string'],
            'ends_at' => ['nullable', 'date', 'after:now'],
        ]);

        $type = (string) $validated['type'];
        $selectedAccountId = (int) ($validated['minecraft_account_id'] ?? 0);
        $selectedAccount = $selectedAccountId > 0
            ? MinecraftAccount::query()->find($selectedAccountId)
            : null;
        $usernameInput = trim((string) ($validated['username'] ?? ''));
        $account = $selectedAccount instanceof MinecraftAccount
            ? $selectedAccount
            : $this->resolveMinecraftAccountFromPunishmentUsername($usernameInput);
        $username = $this->stripPunishmentPlatformSuffix($usernameInput);
        if ($account instanceof MinecraftAccount) {
            $username = trim((string) $account->username);
        }
        if ($username === '') {
            throw ValidationException::withMessages([
                'username' => 'Minecraft username is required.',
            ]);
        }
        $uuid = trim((string) ($account !== null ? $account->uuid : ''));
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
            'uuid' => $uuid !== '' ? strtolower($uuid) : null,
            'username' => $username,
            'type' => $type,
            'reason' => trim((string) ($validated['reason'] ?? '')) ?: null,
            'duration_seconds' => $durationSeconds,
            'started_at' => $startedAt,
            'ends_at' => $endsAt,
            'is_permanent' => $type === MinecraftPenalty::TYPE_KICK ? false : $isPermanent,
            'by_uuid' => null,
            'by_user_id' => (string) $user->id,
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

        $validated = $request->validate([
            'lift_reason' => ['nullable', 'string'],
        ]);

        $minecraftPenalty->lifted_at = now();
        $minecraftPenalty->lifted_by_uuid = null;
        $minecraftPenalty->lifted_by_user_id = (string) $user->id;
        $minecraftPenalty->lifted_by_username = $user->getName();
        $minecraftPenalty->lift_reason = trim((string) ($validated['lift_reason'] ?? '')) ?: null;
        $minecraftPenalty->save();

        $this->minecraftSyncService->liftPenalty($minecraftPenalty);

        session()->flash('message', 'STEMCraft punishment lifted.');
        session()->flash('message-title', 'Punishment updated');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.stemcraft.punishments.index');
    }

    /**
     * @return array{messages: mixed, search: string, selectedMessageType: string, selectedStatus: string, messageTypes: list<string>}
     */
    private function buildAdminMessagesViewData(Request $request): array
    {
        $query = MinecraftMessage::query()
            ->with('account.user')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        $search = trim((string) $request->query('search', ''));
        $messageType = trim((string) $request->query('message_type', ''));
        $status = trim((string) $request->query('status', ''));

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('username', 'like', '%'.$search.'%')
                    ->orWhere('uuid', 'like', '%'.$search.'%')
                    ->orWhere('server_name', 'like', '%'.$search.'%')
                    ->orWhere('world', 'like', '%'.$search.'%')
                    ->orWhere('raw_message', 'like', '%'.$search.'%')
                    ->orWhere('filtered_message', 'like', '%'.$search.'%')
                    ->orWhere('failure_detail', 'like', '%'.$search.'%');
            });
        }

        if ($messageType !== '') {
            $query->where('message_type', $messageType);
        }

        if ($status === 'passed') {
            $query->where('passed', true);
        } elseif ($status === 'blocked') {
            $query->where('passed', false);
        }

        return [
            'messages' => $query->paginate(30)->onEachSide(1),
            'search' => $search,
            'selectedMessageType' => $messageType,
            'selectedStatus' => $status,
            'messageTypes' => MinecraftMessage::query()
                ->select('message_type')
                ->distinct()
                ->orderBy('message_type')
                ->pluck('message_type')
                ->map(fn ($value) => (string) $value)
                ->filter(fn ($value) => $value !== '')
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{webhookLogs: mixed, search: string, selectedDirection: string, selectedStatus: string}
     */
    private function buildAdminWebhooksViewData(Request $request): array
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

        return [
            'webhookLogs' => $query->paginate(30)->onEachSide(1),
            'search' => $search,
            'selectedDirection' => $direction,
            'selectedStatus' => $status,
        ];
    }

    /**
     * @return array{
     *     connection: array{configured: bool, target: string},
     *     status: array<string, mixed>|null,
     *     statusError: string|null,
     *     statusCards: list<array{
     *         label: string,
     *         value: string,
     *         segments?: list<array{label: string, value: string, class: string}>
     *     }>,
     *     worldRows: list<array{name: string, players: string, loaded_chunks: string}>,
     *     serverDetails: list<array{label: string, value: string}>,
     *     lastCommand: string,
     *     lastCommandResult: array<string, mixed>|null,
     *     lastCommandError: string|null
     * }
     */
    private function buildAdminManagementViewData(MinecraftWebhookBridgeService $minecraftWebhookBridgeService): array
    {
        $connection = $minecraftWebhookBridgeService->connectionSummary();
        $status = null;
        $statusError = null;
        $statusCards = [];
        $worldRows = [];
        $serverDetails = [];

        if ($connection['configured']) {
            try {
                $status = $minecraftWebhookBridgeService->requestStatus();
                $statusCards = $this->buildManagementStatusCards($status);
                $worldRows = $this->buildManagementWorldRows($status);
                $serverDetails = $this->buildManagementServerDetails($status);
            } catch (\Throwable $exception) {
                $statusError = $exception->getMessage();
            }
        }

        return [
            'connection' => $connection,
            'status' => $status,
            'statusError' => $statusError,
            'statusCards' => $statusCards,
            'worldRows' => $worldRows,
            'serverDetails' => $serverDetails,
            'lastCommand' => (string) session('minecraft_management.command', ''),
            'lastCommandResult' => session('minecraft_management.command_result'),
            'lastCommandError' => session('minecraft_management.command_error'),
        ];
    }

    /**
     * @param  array<string, mixed>  $status
     * @return list<array{
     *     label: string,
     *     value: string,
     *     segments?: list<array{label: string, value: string, class: string}>
     * }>
     */
    private function buildManagementStatusCards(array $status): array
    {
        $cards = [];
        $cards[] = [
            'label' => 'Server',
            'value' => trim((string) ($status['server_name'] ?? 'STEMCraft')),
        ];

        $cards[] = [
            'label' => 'Version',
            'value' => trim((string) ($status['minecraft_version'] ?? '-')),
        ];

        $onlinePlayers = (int) data_get($status, 'players.online', 0);
        $maxPlayers = (int) data_get($status, 'players.max', 0);
        $cards[] = [
            'label' => 'Players',
            'value' => sprintf('%d / %d', $onlinePlayers, $maxPlayers),
        ];

        $cards[] = [
            'label' => 'TPS',
            'value' => $this->formatTpsSummary(data_get($status, 'tps', [])),
            'segments' => $this->buildManagementTpsSegments($status),
        ];

        $cards[] = [
            'label' => 'Memory',
            'value' => $this->formatMemorySummary(data_get($status, 'memory', [])),
        ];

        $cards[] = [
            'label' => 'Chunks',
            'value' => (string) ((int) ($status['loaded_chunks'] ?? 0)),
        ];

        $cards[] = [
            'label' => 'Worlds',
            'value' => (string) count(is_array($status['worlds'] ?? null) ? $status['worlds'] : []),
        ];

        $cards[] = [
            'label' => 'Online Mode',
            'value' => (bool) ($status['online_mode'] ?? false) ? 'Enabled' : 'Disabled',
        ];

        return $cards;
    }

    /**
     * @param  array<string, mixed>  $status
     * @return list<array{name: string, players: string, loaded_chunks: string}>
     */
    private function buildManagementWorldRows(array $status): array
    {
        $rows = [];
        $worlds = $status['worlds'] ?? null;
        if (! is_array($worlds)) {
            return [];
        }

        foreach ($worlds as $world) {
            if (! is_array($world)) {
                continue;
            }

            $rows[] = [
                'name' => trim((string) ($world['name'] ?? '-')),
                'players' => (string) ((int) ($world['players'] ?? 0)),
                'loaded_chunks' => (string) ((int) ($world['loaded_chunks'] ?? 0)),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $status
     * @return list<array{label: string, value: string}>
     */
    private function buildManagementServerDetails(array $status): array
    {
        return array_values(array_filter([
            [
                'label' => 'Implementation',
                'value' => trim((string) ($status['bukkit_name'] ?? '')),
            ],
            [
                'label' => 'Implementation version',
                'value' => trim((string) ($status['bukkit_version'] ?? '')),
            ],
            [
                'label' => 'Plugin version',
                'value' => trim((string) ($status['plugin_version'] ?? '')),
            ],
            [
                'label' => 'Free memory',
                'value' => $this->formatBytesValue(data_get($status, 'memory.free_bytes'), 0),
            ],
            [
                'label' => 'Captured at',
                'value' => trim((string) ($status['timestamp'] ?? '')),
            ],
        ], static fn (array $detail): bool => $detail['value'] !== ''));
    }

    /**
     * @param  array<string, mixed>|mixed  $memory
     */
    private function formatMemorySummary(mixed $memory): string
    {
        if (! is_array($memory)) {
            return '-';
        }

        $used = $memory['used_bytes'] ?? null;
        $max = $memory['max_bytes'] ?? null;
        if (! is_numeric($used) || ! is_numeric($max)) {
            return '-';
        }

        return $this->formatBytesValue($used).' / '.$this->formatBytesValue($max);
    }

    /**
     * @param  array<string, mixed>|mixed  $tps
     */
    private function formatTpsSummary(mixed $tps): string
    {
        if (! is_array($tps)) {
            return '-';
        }

        $values = [];
        foreach (['one_minute', 'five_minute', 'fifteen_minute'] as $key) {
            $value = $tps[$key] ?? null;
            if (! is_numeric($value)) {
                $values[] = '-';
                continue;
            }

            $values[] = number_format((float) $value, 2);
        }

        return implode(' / ', $values);
    }

    private function formatBytesValue(mixed $bytes, int $precision = 2): string
    {
        if (! is_numeric($bytes)) {
            return '-';
        }

        $value = (float) $bytes;
        $units = ['bytes', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $unitIndex = 0;

        while ($value >= 1024 && $unitIndex < count($units) - 1) {
            $value /= 1024;
            $unitIndex++;
        }

        $rounded = round($value, max(0, $precision));

        return $rounded.' '.$units[$unitIndex];
    }

    /**
     * @param  array<string, mixed>  $status
     * @return list<array{label: string, value: string, class: string}>
     */
    private function buildManagementTpsSegments(array $status): array
    {
        $segments = [];
        $tps = data_get($status, 'tps', []);
        if (! is_array($tps)) {
            return $segments;
        }

        foreach ([
            'one_minute' => '1m',
            'five_minute' => '5m',
            'fifteen_minute' => '15m',
        ] as $key => $label) {
            $value = $tps[$key] ?? null;

            if (! is_numeric($value)) {
                $segments[] = [
                    'label' => $label,
                    'value' => '-',
                    'class' => 'text-gray-500',
                ];

                continue;
            }

            $segments[] = [
                'label' => $label,
                'value' => number_format((float) $value, 2),
                'class' => $this->managementTpsColorClass((float) $value),
            ];
        }

        return $segments;
    }

    private function managementTpsColorClass(float $tps): string
    {
        if ($tps >= 19.8) {
            return 'text-green-700';
        }

        if ($tps >= 18.0) {
            return 'text-amber-700';
        }

        return 'text-red-700';
    }

    private function ensureMinecraftAccess(User $user): void
    {
        abort_unless($user->canAccessMinecraftPage(), 403);
    }

    /**
     * @return Collection<int, MinecraftAccount>
     */
    private function minecraftAccountsForViewer(User $user): Collection
    {
        $query = MinecraftAccount::query()
            ->with(['sessions', 'penalties', 'blacklistEntries', 'user'])
            ->orderBy('username');

        if ($user->canManageMinecraftAccounts()) {
            $ownerIds = $this->managedMinecraftAccountOwnerIds($user);
            if ($ownerIds !== []) {
                $query->whereIn('user_id', $ownerIds);
            }
        } else {
            $query->where('user_id', (string) $user->id);
        }

        return $query->get();
    }

    /**
     * @return list<string>
     */
    private function managedMinecraftAccountOwnerIds(User $user): array
    {
        if (! $user->canManageMinecraftAccounts()) {
            return [(string) $user->id];
        }

        $childIds = $user->children()
            ->whereNull('anonymized_at')
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();

        return array_values(array_unique(array_merge([(string) $user->id], $childIds)));
    }

    private function minecraftAccountOwnerOptions(User $user): Collection
    {
        $options = collect([
            [
                'id' => (string) $user->id,
                'label' => 'You',
            ],
        ]);

        return $options->concat(
            $user->children()
                ->whereNull('anonymized_at')
                ->orderBy('username')
                ->get()
                ->map(fn (User $child): array => [
                    'id' => (string) $child->id,
                    'label' => $child->username ?: $child->getName() ?: 'Child account',
                ])
        )->values();
    }

    private function resolveMinecraftAccountOwner(User $user, string $ownerId): User
    {
        $ownerId = trim($ownerId);
        if ($ownerId === '') {
            return $user;
        }

        if ((string) $user->id === $ownerId) {
            return $user;
        }

        $child = $user->children()
            ->whereNull('anonymized_at')
            ->whereKey($ownerId)
            ->first();

        if ($child instanceof User) {
            return $child;
        }

        return $user;
    }

    private function canManageMinecraftAccountRecord(User $user, MinecraftAccount $minecraftAccount): bool
    {
        if (! $user->canManageMinecraftAccounts()) {
            return false;
        }

        return in_array((string) $minecraftAccount->user_id, $this->managedMinecraftAccountOwnerIds($user), true);
    }

    /**
     * @param  array{platform: string, username: string}  $attributes
     */
    private function enforceLinkedAccountLimit(User $user, array $attributes): void
    {
        if ($user->hasGroup('admin') || $user->hasGroup('minecraft-org')) {
            return;
        }

        $platform = strtolower(trim((string) $attributes['platform']));
        $username = trim((string) $attributes['username']);
        if ($platform === '' || $username === '') {
            return;
        }

        $alreadyLinkedToUser = $user->minecraftAccounts()
            ->where('platform', $platform)
            ->where('username', $username)
            ->exists();
        if ($alreadyLinkedToUser) {
            return;
        }

        if ($user->minecraftAccounts()->count() >= self::MAX_LINKED_ACCOUNTS) {
            throw ValidationException::withMessages([
                'username' => sprintf(
                    'You can only link up to %d Minecraft accounts. Remove an existing account before adding another.',
                    self::MAX_LINKED_ACCOUNTS
                ),
            ]);
        }
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

    private function resolveMinecraftAccountFromPunishmentUsername(string $usernameInput): ?MinecraftAccount
    {
        $usernameInput = trim($usernameInput);
        if ($usernameInput === '') {
            return null;
        }

        $matchedAccount = $this->findMinecraftAccountByUsername($usernameInput);
        if ($matchedAccount instanceof MinecraftAccount) {
            return $matchedAccount;
        }

        if (preg_match('/^(?<username>.+?)\s*\((?<platform>java|bedrock)\)$/i', $usernameInput, $matches) !== 1) {
            return null;
        }

        $username = trim((string) $matches['username']);
        $platform = strtolower(trim((string) $matches['platform']));
        if ($username === '' || $platform === '') {
            return null;
        }

        return MinecraftAccount::query()
            ->where('username', $username)
            ->where('platform', $platform)
            ->orderByRaw('uuid IS NULL')
            ->orderByRaw('user_id IS NULL')
            ->orderByDesc('is_whitelisted')
            ->first();
    }

    private function stripPunishmentPlatformSuffix(string $usernameInput): string
    {
        $usernameInput = trim($usernameInput);
        if (preg_match('/^(?<username>.+?)\s*\((?<platform>java|bedrock)\)$/i', $usernameInput, $matches) !== 1) {
            return $usernameInput;
        }

        return trim((string) $matches['username']);
    }

    private function formatPunishmentAccountOptionLabel(MinecraftAccount $account): string
    {
        $platform = strtolower(trim((string) $account->platform));
        if ($platform === '') {
            $platform = 'unknown';
        }

        return sprintf('%s (%s)', trim((string) $account->username), $platform);
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
