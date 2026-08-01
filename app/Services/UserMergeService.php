<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class UserMergeService
{
    /**
     * @var array<string, array<int, string>>
     */
    private const DIRECT_USER_REFERENCES = [
        'audit_logs' => ['actor_user_id'],
        'custom_pages' => ['user_id'],
        'expenses' => ['created_by'],
        'finance_files' => ['user_id'],
        'inbound_sms' => ['acknowledged_by_user_id'],
        'invoices' => ['user_id'],
        'media' => ['user_id'],
        'minecraft_accounts' => ['user_id'],
        'minecraft_blacklist_entries' => ['created_by', 'lifted_by'],
        'minecraft_penalties' => ['by_user_id', 'lifted_by_user_id'],
        'payments' => ['user_id', 'created_by'],
        'posts' => ['user_id'],
        'quotes' => ['user_id'],
        'sent_sms' => ['initiated_by_user_id'],
        'square_ignored_payments' => ['created_by'],
        'store_orders' => ['user_id'],
        'store_order_item_cancellations' => ['cancelled_by_user_id'],
        'store_order_item_collections' => ['collected_by_user_id'],
        'tickets' => ['user_id'],
        'workshops' => ['user_id', 'requested_by_user_id'],
        'workshop_attendances' => ['user_id', 'created_by'],

        // These tables are optional in installations that still contain the retired classroom/forum features.
        'class_chat_messages' => ['user_id', 'deleted_by_user_id'],
        'class_chat_participant_states' => ['disabled_by_user_id'],
        'class_help_requests' => ['user_id', 'requested_by_user_id', 'approved_by_user_id'],
        'class_sessions' => ['created_by_user_id', 'live_broadcast_started_by_user_id', 'live_broadcast_ended_by_user_id'],
        'forum_posts' => ['user_id', 'approved_by_user_id'],
        'forum_post_attachments' => ['uploaded_by_user_id'],
        'forum_topics' => ['user_id', 'last_post_user_id', 'approved_by_user_id'],
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private const UNIQUE_USER_RELATIONSHIPS = [
        'organisation_user' => ['organisation_id'],
        'user_groups' => ['slug'],
        'workshop_interests' => ['workshop_id'],
        'class_enrolments' => ['class_session_id'],
        'class_chat_participant_states' => ['class_session_id'],
        'forum_post_reactions' => ['forum_post_id'],
        'forum_topic_user_states' => ['forum_topic_id'],
    ];

    public function merge(User $source, User $destination): void
    {
        if ((string) $source->id === (string) $destination->id) {
            throw new InvalidArgumentException('A user cannot be merged into themselves.');
        }

        DB::transaction(function () use ($source, $destination): void {
            $lockedUsers = User::query()
                ->whereIn('id', [$source->id, $destination->id])
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (User $user): string => (string) $user->id);

            /** @var User|null $lockedSource */
            $lockedSource = $lockedUsers->get((string) $source->id);
            /** @var User|null $lockedDestination */
            $lockedDestination = $lockedUsers->get((string) $destination->id);

            if (! $lockedSource || ! $lockedDestination) {
                throw new InvalidArgumentException('Both users must still exist before they can be merged.');
            }

            if ($lockedDestination->primary_organisation_id === null && $lockedSource->primary_organisation_id !== null) {
                $lockedDestination->updateQuietly([
                    'primary_organisation_id' => $lockedSource->primary_organisation_id,
                ]);
            }

            foreach (self::UNIQUE_USER_RELATIONSHIPS as $table => $uniqueColumns) {
                $this->mergeUniqueRelationship($table, $uniqueColumns, $lockedSource, $lockedDestination);
            }

            foreach (self::DIRECT_USER_REFERENCES as $table => $columns) {
                foreach ($columns as $column) {
                    $this->transferReference($table, $column, $lockedSource, $lockedDestination);
                }
            }

            $this->removeSourceAuthenticationData($lockedSource);
            $lockedSource->delete();
        });
    }

    /**
     * @param array<int, string> $uniqueColumns
     */
    private function mergeUniqueRelationship(string $table, array $uniqueColumns, User $source, User $destination): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'user_id')) {
            return;
        }

        foreach ($uniqueColumns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return;
            }
        }

        $sourceRows = DB::table($table)
            ->where('user_id', $source->id)
            ->get();

        foreach ($sourceRows as $sourceRow) {
            $duplicateQuery = DB::table($table)->where('user_id', $destination->id);
            foreach ($uniqueColumns as $column) {
                $duplicateQuery->where($column, $sourceRow->{$column});
            }

            $sourceRowQuery = DB::table($table)->where('user_id', $source->id);
            foreach ($uniqueColumns as $column) {
                $sourceRowQuery->where($column, $sourceRow->{$column});
            }

            if ($duplicateQuery->exists()) {
                $sourceRowQuery->delete();
            } else {
                $sourceRowQuery->update(['user_id' => $destination->id]);
            }
        }
    }

    private function transferReference(string $table, string $column, User $source, User $destination): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)
            ->where($column, $source->id)
            ->update([$column => $destination->id]);
    }

    private function removeSourceAuthenticationData(User $source): void
    {
        foreach (['tokens', 'user_backup_codes', 'sessions'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'user_id')) {
                DB::table($table)->where('user_id', $source->id)->delete();
            }
        }

        $sourceEmail = trim((string) ($source->email ?? ''));
        if ($sourceEmail !== '' && Schema::hasTable('password_reset_tokens') && Schema::hasColumn('password_reset_tokens', 'email')) {
            DB::table('password_reset_tokens')->where('email', $sourceEmail)->delete();
        }
    }
}
