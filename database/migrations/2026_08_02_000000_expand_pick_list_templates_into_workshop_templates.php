<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pick_list_templates', function (Blueprint $table): void {
            $table->string('duration')->nullable()->after('description');
            $table->string('participants')->nullable()->after('duration');
            $table->longText('run_sheet')->nullable()->after('participants');
            $table->longText('run_sheet_drawing_data')->nullable()->after('run_sheet');
        });

        Schema::create('workshop_template_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pick_list_template_id')->constrained('pick_list_templates')->cascadeOnDelete();
            $table->string('name');
            $table->text('notes')->nullable();
            $table->boolean('reminder_enabled')->default(false);
            $table->smallInteger('reminder_offset_days')->nullable();
            $table->string('reminder_time', 5)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['pick_list_template_id', 'sort_order'], 'workshop_template_tasks_sort_idx');
        });

        Schema::table('workshops', function (Blueprint $table): void {
            $table->json('run_sheet_completed_task_ids')->nullable()->after('pick_list_checked_item_ids');
            $table->longText('workshop_run_sheet')->nullable()->after('run_sheet_completed_task_ids');
            $table->foreignUuid('facilitator_user_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete();
        });

        $fallbackUserId = DB::table('users')->where('email', 'james@stemmechanics.com.au')->value('id');
        DB::table('workshops')->orderBy('id')->chunkById(500, function ($workshops) use ($fallbackUserId): void {
            foreach ($workshops as $workshop) {
                $facilitatorId = $workshop->user_id ?: $fallbackUserId;
                if ($facilitatorId !== null) {
                    DB::table('workshops')->where('id', $workshop->id)->update(['facilitator_user_id' => $facilitatorId]);
                }
            }
        });

        Schema::create('reminders', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 80);
            $table->string('remindable_type')->nullable();
            $table->string('remindable_id')->nullable();
            $table->string('source_type')->nullable();
            $table->string('source_id')->nullable();
            $table->foreignUuid('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_email');
            $table->string('subject');
            $table->text('message')->nullable();
            $table->text('action_url')->nullable();
            $table->string('status', 20)->default('pending');
            $table->dateTime('scheduled_at');
            $table->dateTime('queued_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('failed_at')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
            $table->index(['remindable_type', 'remindable_id']);
            $table->index(['source_type', 'source_id']);
            $table->index(['kind', 'remindable_type', 'remindable_id'], 'reminders_kind_remindable_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');

        Schema::table('workshops', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('facilitator_user_id');
            $table->dropColumn('run_sheet_completed_task_ids');
            $table->dropColumn('workshop_run_sheet');
        });

        Schema::dropIfExists('workshop_template_tasks');

        Schema::table('pick_list_templates', function (Blueprint $table): void {
            $table->dropColumn([
                'duration',
                'participants',
                'run_sheet',
                'run_sheet_drawing_data',
            ]);
        });
    }
};
