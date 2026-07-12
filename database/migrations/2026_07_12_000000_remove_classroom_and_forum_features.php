<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->preserveClassroomWorkshopSchedule();

        if (Schema::hasTable('workshops') && Schema::hasColumn('workshops', 'registration')) {
            DB::table('workshops')
                ->where('registration', 'classroom')
                ->update(['registration' => 'tickets']);
        }

        if (Schema::hasTable('workshops')) {
            Schema::table('workshops', function (Blueprint $table): void {
                if (Schema::hasColumn('workshops', 'classroom_sessions_json')) {
                    $table->dropColumn('classroom_sessions_json');
                }

                if (Schema::hasColumn('workshops', 'classroom_forum_category_id')) {
                    $table->dropConstrainedForeignId('classroom_forum_category_id');
                }

                if (Schema::hasColumn('workshops', 'class_session_id')) {
                    $table->dropConstrainedForeignId('class_session_id');
                }
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table): void {
                foreach ([
                    'child_can_create_forum_topics',
                    'child_can_reply_in_forum',
                    'child_forum_topic_requires_approval',
                    'child_forum_reply_requires_approval',
                    'child_parent_notified_on_forum_topics',
                    'child_parent_notified_on_forum_replies',
                ] as $column) {
                    if (Schema::hasColumn('users', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::disableForeignKeyConstraints();

        foreach ([
            'forum_post_attachments',
            'forum_topic_user_states',
            'forum_post_reactions',
            'forum_posts',
            'forum_topics',
            'class_chat_participant_states',
            'class_chat_messages',
            'class_help_requests',
            'class_enrolments',
            'class_sessions',
            'forum_categories',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        if (Schema::hasTable('workshops')) {
            Schema::table('workshops', function (Blueprint $table): void {
                if (! Schema::hasColumn('workshops', 'class_session_id')) {
                    $table->uuid('class_session_id')->nullable()->after('registration_data');
                }

                if (! Schema::hasColumn('workshops', 'classroom_forum_category_id')) {
                    $table->uuid('classroom_forum_category_id')->nullable()->after('class_session_id');
                }

                if (! Schema::hasColumn('workshops', 'classroom_sessions_json')) {
                    $table->text('classroom_sessions_json')->nullable()->after('classroom_forum_category_id');
                }
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table): void {
                if (! Schema::hasColumn('users', 'child_can_create_forum_topics')) {
                    $table->boolean('child_can_create_forum_topics')->default(true);
                }

                if (! Schema::hasColumn('users', 'child_can_reply_in_forum')) {
                    $table->boolean('child_can_reply_in_forum')->default(true);
                }

                if (! Schema::hasColumn('users', 'child_forum_topic_requires_approval')) {
                    $table->boolean('child_forum_topic_requires_approval')->default(false);
                }

                if (! Schema::hasColumn('users', 'child_forum_reply_requires_approval')) {
                    $table->boolean('child_forum_reply_requires_approval')->default(false);
                }

                if (! Schema::hasColumn('users', 'child_parent_notified_on_forum_topics')) {
                    $table->boolean('child_parent_notified_on_forum_topics')->default(false);
                }

                if (! Schema::hasColumn('users', 'child_parent_notified_on_forum_replies')) {
                    $table->boolean('child_parent_notified_on_forum_replies')->default(false);
                }
            });
        }
    }

    private function preserveClassroomWorkshopSchedule(): void
    {
        if (! Schema::hasTable('workshops') || ! Schema::hasColumn('workshops', 'registration')) {
            return;
        }

        $select = ['id', 'registration'];
        foreach (['starts_at', 'ends_at', 'classroom_sessions_json', 'class_session_id'] as $column) {
            if (Schema::hasColumn('workshops', $column)) {
                $select[] = $column;
            }
        }

        DB::table('workshops')
            ->select($select)
            ->where('registration', 'classroom')
            ->orderBy('id')
            ->get()
            ->each(function (object $workshop): void {
                $schedule = $this->scheduleFromWorkshop($workshop);

                if ($schedule === null && isset($workshop->class_session_id) && Schema::hasTable('class_sessions')) {
                    $schedule = $this->scheduleFromClassSession((string) $workshop->class_session_id);
                }

                if ($schedule === null) {
                    return;
                }

                $updates = [];

                if (Schema::hasColumn('workshops', 'starts_at') && empty($workshop->starts_at)) {
                    $updates['starts_at'] = $schedule['starts_at'];
                }

                if (Schema::hasColumn('workshops', 'ends_at') && empty($workshop->ends_at)) {
                    $updates['ends_at'] = $schedule['ends_at'];
                }

                if ($updates !== []) {
                    DB::table('workshops')->where('id', $workshop->id)->update($updates);
                }
            });
    }

    /**
     * @return array{starts_at: string, ends_at: string}|null
     */
    private function scheduleFromWorkshop(object $workshop): ?array
    {
        if (! isset($workshop->classroom_sessions_json)) {
            return null;
        }

        return $this->scheduleFromJson($workshop->classroom_sessions_json);
    }

    /**
     * @return array{starts_at: string, ends_at: string}|null
     */
    private function scheduleFromClassSession(string $classSessionId): ?array
    {
        $select = ['id'];
        foreach (['starts_at', 'ends_at', 'broadcast_sessions_json'] as $column) {
            if (Schema::hasColumn('class_sessions', $column)) {
                $select[] = $column;
            }
        }

        $classSession = DB::table('class_sessions')->select($select)->where('id', $classSessionId)->first();
        if (! $classSession) {
            return null;
        }

        if (isset($classSession->broadcast_sessions_json)) {
            $schedule = $this->scheduleFromJson($classSession->broadcast_sessions_json);
            if ($schedule !== null) {
                return $schedule;
            }
        }

        if (! empty($classSession->starts_at)) {
            return [
                'starts_at' => $this->formatDateTime($classSession->starts_at),
                'ends_at' => $this->formatDateTime($classSession->ends_at ?? $classSession->starts_at),
            ];
        }

        return null;
    }

    /**
     * @return array{starts_at: string, ends_at: string}|null
     */
    private function scheduleFromJson(mixed $value): ?array
    {
        $entries = is_string($value) ? json_decode($value, true) : $value;
        if (! is_array($entries)) {
            return null;
        }

        $dates = collect($entries)
            ->filter(fn (mixed $entry): bool => is_array($entry) && ! empty($entry['starts_at']))
            ->map(function (array $entry): array {
                return [
                    'starts_at' => $this->formatDateTime($entry['starts_at']),
                    'ends_at' => $this->formatDateTime($entry['ends_at'] ?? $entry['starts_at']),
                ];
            })
            ->sortBy('starts_at')
            ->values();

        if ($dates->isEmpty()) {
            return null;
        }

        return [
            'starts_at' => $dates->first()['starts_at'],
            'ends_at' => $dates->last()['ends_at'],
        ];
    }

    private function formatDateTime(mixed $value): string
    {
        return Carbon::parse($value)->format('Y-m-d H:i:s');
    }
};
