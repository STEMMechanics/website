<?php

use App\Models\WorkshopRunSheetTask;
use App\Models\WorkshopTemplateTask;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pick_list_templates', function (Blueprint $table): void {
            $table->string('default_workshop_title')->nullable();
            $table->string('default_workshop_summary', 1000)->nullable();
            $table->longText('default_workshop_content')->nullable();
            $table->string('hero_media_name')->nullable();
            $table->foreign('hero_media_name')->references('name')->on('media')->nullOnDelete();
        });

        Schema::table('workshops', function (Blueprint $table): void {
            $table->boolean('run_sheet_tasks_initialized')->default(false);
        });

        Schema::create('workshop_run_sheet_tasks', function (Blueprint $table): void {
            $table->id();
            $table->string('workshop_id');
            $table->foreign('workshop_id')->references('id')->on('workshops')->cascadeOnDelete();
            $table->foreignId('blueprint_task_id')->nullable()->constrained('workshop_template_tasks')->nullOnDelete();
            $table->string('name');
            $table->longText('notes')->nullable();
            $table->json('subtasks')->nullable();
            $table->boolean('reminder_enabled')->default(false);
            $table->smallInteger('reminder_offset_days')->nullable();
            $table->string('reminder_time', 5)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['workshop_id', 'sort_order'], 'workshop_run_sheet_tasks_sort_idx');
        });

        DB::table('workshops')
            ->whereNotNull('pick_list_template_id')
            ->orderBy('id')
            ->chunkById(100, function ($workshops): void {
                foreach ($workshops as $workshop) {
                    $idMap = [];
                    $blueprint = DB::table('pick_list_templates')->where('id', $workshop->pick_list_template_id)->first();
                    $tasks = DB::table('workshop_template_tasks')
                        ->where('pick_list_template_id', $workshop->pick_list_template_id)
                        ->orderBy('sort_order')
                        ->orderBy('id')
                        ->get();

                    foreach ($tasks as $task) {
                        $newId = DB::table('workshop_run_sheet_tasks')->insertGetId([
                            'workshop_id' => $workshop->id,
                            'blueprint_task_id' => $task->id,
                            'name' => $task->name,
                            'notes' => $task->notes,
                            'subtasks' => $task->subtasks,
                            'reminder_enabled' => $task->reminder_enabled,
                            'reminder_offset_days' => $task->reminder_offset_days,
                            'reminder_time' => $task->reminder_time,
                            'sort_order' => $task->sort_order,
                            'created_at' => $task->created_at,
                            'updated_at' => $task->updated_at,
                        ]);
                        $idMap[(int) $task->id] = (int) $newId;
                    }

                    $completedIds = json_decode((string) ($workshop->run_sheet_completed_task_ids ?? '[]'), true);
                    $completedIds = is_array($completedIds)
                        ? collect($completedIds)->map(fn ($id): ?int => $idMap[(int) $id] ?? null)->filter()->values()->all()
                        : [];

                    DB::table('workshops')->where('id', $workshop->id)->update([
                        'run_sheet_completed_task_ids' => json_encode($completedIds),
                        'run_sheet_tasks_initialized' => true,
                        'workshop_run_sheet' => ($workshop->workshop_run_sheet ?? null) ?: ($blueprint->run_sheet ?? null),
                    ]);

                    foreach ($idMap as $oldId => $newId) {
                        DB::table('reminders')
                            ->where('kind', 'workshop_task')
                            ->where('remindable_type', 'App\\Models\\Workshop')
                            ->where('remindable_id', $workshop->id)
                            ->where('source_type', WorkshopTemplateTask::class)
                            ->where('source_id', (string) $oldId)
                            ->update([
                                'source_type' => WorkshopRunSheetTask::class,
                                'source_id' => (string) $newId,
                            ]);
                    }
                }
            }, 'id');
    }

    public function down(): void
    {
        DB::table('workshop_run_sheet_tasks')
            ->orderBy('id')
            ->chunkById(500, function ($tasks): void {
                foreach ($tasks as $task) {
                    $source = DB::table('reminders')
                        ->where('source_type', WorkshopRunSheetTask::class)
                        ->where('source_id', (string) $task->id);

                    if ($task->blueprint_task_id !== null) {
                        $source->update([
                            'source_type' => WorkshopTemplateTask::class,
                            'source_id' => (string) $task->blueprint_task_id,
                        ]);
                    } else {
                        $source->update(['source_type' => null, 'source_id' => null]);
                    }
                }
            });

        DB::table('workshops')
            ->orderBy('id')
            ->chunkById(100, function ($workshops): void {
                foreach ($workshops as $workshop) {
                    $completedIds = json_decode((string) ($workshop->run_sheet_completed_task_ids ?? '[]'), true);
                    if (! is_array($completedIds) || $completedIds === []) {
                        continue;
                    }

                    $taskMap = DB::table('workshop_run_sheet_tasks')
                        ->where('workshop_id', $workshop->id)
                        ->whereIn('id', collect($completedIds)->map(fn ($id): int => (int) $id)->all())
                        ->whereNotNull('blueprint_task_id')
                        ->pluck('blueprint_task_id', 'id');
                    $restoredIds = collect($completedIds)
                        ->map(fn ($id): ?int => isset($taskMap[(int) $id]) ? (int) $taskMap[(int) $id] : null)
                        ->filter()
                        ->values()
                        ->all();

                    DB::table('workshops')->where('id', $workshop->id)->update([
                        'run_sheet_completed_task_ids' => json_encode($restoredIds),
                    ]);
                }
            }, 'id');

        Schema::dropIfExists('workshop_run_sheet_tasks');

        Schema::table('workshops', function (Blueprint $table): void {
            $table->dropColumn('run_sheet_tasks_initialized');
        });

        Schema::table('pick_list_templates', function (Blueprint $table): void {
            $table->dropForeign(['hero_media_name']);
            $table->dropColumn([
                'default_workshop_title',
                'default_workshop_summary',
                'default_workshop_content',
                'hero_media_name',
            ]);
        });
    }
};
