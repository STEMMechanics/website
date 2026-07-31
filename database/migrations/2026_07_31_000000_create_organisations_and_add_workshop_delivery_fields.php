<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('organisations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('type', 50)->nullable();
            $table->foreignUuid('parent_id')->nullable()->constrained('organisations')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('name');
            $table->index('type');
        });

        Schema::create('organisation_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['organisation_id', 'user_id']);
        });

        $this->backfillUserCompanies();

        Schema::table('workshops', function (Blueprint $table): void {
            $table->foreignUuid('hosted_for_organisation_id')->nullable()->after('location_id')
                ->constrained('organisations')->nullOnDelete();
            $table->foreignUuid('requested_by_user_id')->nullable()->after('hosted_for_organisation_id')
                ->constrained('users')->nullOnDelete();

            $table->index(['hosted_for_organisation_id', 'starts_at'], 'workshops_hosted_for_org_starts_at_index');
            $table->index(['requested_by_user_id', 'starts_at'], 'workshops_requester_starts_at_index');
        });

        $this->backfillWorkshopHostedFor();

        Schema::table('workshops', function (Blueprint $table): void {
            $table->dropColumn('hosted_for');
        });
    }

    public function down(): void
    {
        Schema::table('workshops', function (Blueprint $table): void {
            $table->string('hosted_for')->nullable()->after('private_code');
        });

        DB::table('workshops')
            ->whereNotNull('hosted_for_organisation_id')
            ->orderBy('id')
            ->chunk(500, function ($workshops): void {
                $organisationNames = DB::table('organisations')
                    ->whereIn('id', $workshops->pluck('hosted_for_organisation_id')->filter()->unique())
                    ->pluck('name', 'id');

                foreach ($workshops as $workshop) {
                    $name = $organisationNames[(string) $workshop->hosted_for_organisation_id] ?? null;
                    if (is_string($name) && $name !== '') {
                        DB::table('workshops')->where('id', $workshop->id)->update(['hosted_for' => $name]);
                    }
                }
            });

        Schema::table('workshops', function (Blueprint $table): void {
            $table->dropForeign('workshops_requested_by_user_id_foreign');
            $table->dropForeign('workshops_hosted_for_organisation_id_foreign');
            $table->dropIndex('workshops_hosted_for_org_starts_at_index');
            $table->dropIndex('workshops_requester_starts_at_index');
            $table->dropColumn(['requested_by_user_id', 'hosted_for_organisation_id']);
        });

        Schema::dropIfExists('organisation_user');
        Schema::dropIfExists('organisations');
    }

    private function backfillUserCompanies(): void
    {
        if (! Schema::hasColumn('users', 'company')) {
            return;
        }

        /** @var array<string, string> $organisationIdsByName */
        $organisationIdsByName = [];

        DB::table('users')
            ->select(['id', 'company'])
            ->whereNotNull('company')
            ->orderBy('id')
            ->chunk(500, function ($users) use (&$organisationIdsByName): void {
                foreach ($users as $user) {
                    $company = preg_replace('/\s+/u', ' ', trim((string) $user->company)) ?? '';
                    if ($company === '') {
                        continue;
                    }

                    $normalizedName = mb_strtolower($company);
                    $organisationId = $organisationIdsByName[$normalizedName] ?? null;

                    if ($organisationId === null) {
                        $organisationId = (string) Str::uuid();
                        DB::table('organisations')->insert([
                            'id' => $organisationId,
                            'name' => $company,
                            'type' => 'other',
                            'parent_id' => null,
                            'notes' => '',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $organisationIdsByName[$normalizedName] = $organisationId;
                    }

                    DB::table('organisation_user')->insertOrIgnore([
                        'organisation_id' => $organisationId,
                        'user_id' => (string) $user->id,
                        'role' => null,
                        'is_primary' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    private function backfillWorkshopHostedFor(): void
    {
        if (! Schema::hasColumn('workshops', 'hosted_for')) {
            return;
        }

        /** @var array<string, string> $organisationIdsByName */
        $organisationIdsByName = DB::table('organisations')
            ->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name): array => [$this->normalizeName((string) $name) => (string) $id])
            ->all();

        DB::table('workshops')
            ->select(['id', 'hosted_for'])
            ->whereNotNull('hosted_for')
            ->orderBy('id')
            ->chunk(500, function ($workshops) use (&$organisationIdsByName): void {
                foreach ($workshops as $workshop) {
                    $hostedFor = preg_replace('/\s+/u', ' ', trim((string) $workshop->hosted_for)) ?? '';
                    if ($hostedFor === '') {
                        continue;
                    }

                    $normalizedName = $this->normalizeName($hostedFor);
                    $organisationId = $organisationIdsByName[$normalizedName] ?? null;
                    if ($organisationId === null) {
                        $organisationId = (string) Str::uuid();
                        DB::table('organisations')->insert([
                            'id' => $organisationId,
                            'name' => $hostedFor,
                            'type' => 'other',
                            'parent_id' => null,
                            'notes' => 'Created from an existing workshop Hosted For value during migration.',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $organisationIdsByName[$normalizedName] = $organisationId;
                    }

                    DB::table('workshops')->where('id', $workshop->id)->update([
                        'hosted_for_organisation_id' => $organisationId,
                    ]);
                }
            });
    }

    private function normalizeName(string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($name)) ?? '');
    }
};
