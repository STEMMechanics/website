<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureApprovalFields(
            'sponsors',
            'recognition_public',
            'recognition_approved_at',
            'recognition_approved_by',
            'recognition_approval_notified_at',
            'sponsors_recognition_approval_idx'
        );

        $this->ensureApprovalFields(
            'organisations',
            'sponsorship_recognition_public',
            'sponsorship_recognition_approved_at',
            'sponsorship_recognition_approved_by',
            'sponsorship_recognition_approval_notified_at',
            'organisations_sponsorship_recognition_approval_idx'
        );
    }

    private function ensureApprovalFields(
        string $tableName,
        string $publicColumn,
        string $approvedAtColumn,
        string $approvedByColumn,
        string $notifiedAtColumn,
        string $indexName
    ): void {
        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, $publicColumn)) {
            throw new \RuntimeException("Cannot add sponsorship approval fields to {$tableName}; its public recognition schema is missing.");
        }

        $missingColumns = [
            $approvedAtColumn => ! Schema::hasColumn($tableName, $approvedAtColumn),
            $approvedByColumn => ! Schema::hasColumn($tableName, $approvedByColumn),
            $notifiedAtColumn => ! Schema::hasColumn($tableName, $notifiedAtColumn),
        ];

        if (in_array(true, $missingColumns, true)) {
            Schema::table($tableName, function (Blueprint $table) use (
                $publicColumn,
                $approvedAtColumn,
                $approvedByColumn,
                $notifiedAtColumn,
                $missingColumns
            ): void {
                if ($missingColumns[$approvedAtColumn]) {
                    $table->timestamp($approvedAtColumn)->nullable()->after($publicColumn);
                }
                if ($missingColumns[$approvedByColumn]) {
                    $table->foreignUuid($approvedByColumn)->nullable()->after($approvedAtColumn)->constrained('users')->nullOnDelete();
                }
                if ($missingColumns[$notifiedAtColumn]) {
                    $table->timestamp($notifiedAtColumn)->nullable()->after($approvedByColumn);
                }
            });
        }

        $foreignKeyExists = false;
        foreach (Schema::getForeignKeys($tableName) as $foreignKey) {
            if (in_array($approvedByColumn, $foreignKey['columns'] ?? [], true)) {
                $foreignKeyExists = true;
                break;
            }
        }

        if (! $foreignKeyExists) {
            Schema::table($tableName, function (Blueprint $table) use ($approvedByColumn): void {
                $table->foreign($approvedByColumn)->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasIndex($tableName, $indexName)
            && ! Schema::hasIndex($tableName, [$publicColumn, $approvedAtColumn])) {
            Schema::table($tableName, function (Blueprint $table) use ($publicColumn, $approvedAtColumn, $indexName): void {
                $table->index([$publicColumn, $approvedAtColumn], $indexName);
            });
        }
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table): void {
            $table->dropForeign(['sponsorship_recognition_approved_by']);
            $table->dropIndex('organisations_sponsorship_recognition_approval_idx');
            $table->dropColumn([
                'sponsorship_recognition_approved_at',
                'sponsorship_recognition_approved_by',
                'sponsorship_recognition_approval_notified_at',
            ]);
        });

        Schema::table('sponsors', function (Blueprint $table): void {
            $table->dropForeign(['recognition_approved_by']);
            $table->dropIndex('sponsors_recognition_approval_idx');
            $table->dropColumn([
                'recognition_approved_at',
                'recognition_approved_by',
                'recognition_approval_notified_at',
            ]);
        });
    }
};
