<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sponsors', function (Blueprint $table): void {
            $table->timestamp('recognition_approved_at')->nullable()->after('recognition_logo_path');
            $table->foreignUuid('recognition_approved_by')->nullable()->after('recognition_approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('recognition_approval_notified_at')->nullable()->after('recognition_approved_by');
            $table->index(['recognition_public', 'recognition_approved_at'], 'sponsors_recognition_approval_idx');
        });

        Schema::table('organisations', function (Blueprint $table): void {
            $table->timestamp('sponsorship_recognition_approved_at')->nullable()->after('sponsorship_recognition_public');
            $table->foreignUuid('sponsorship_recognition_approved_by')->nullable()->after('sponsorship_recognition_approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('sponsorship_recognition_approval_notified_at')->nullable()->after('sponsorship_recognition_approved_by');
            $table->index(['sponsorship_recognition_public', 'sponsorship_recognition_approved_at'], 'organisations_sponsorship_recognition_approval_idx');
        });
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
