<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analytics_events', function (Blueprint $table): void {
            $table->boolean('is_session_entry')->default(false)->after('session_token');
            $table->string('landing_path')->nullable()->after('path');
            $table->string('acquisition_source')->nullable()->after('referrer_host');
            $table->string('utm_source')->nullable()->after('acquisition_source');
            $table->string('utm_medium')->nullable()->after('utm_source');
            $table->string('utm_campaign')->nullable()->after('utm_medium');
            $table->string('utm_term')->nullable()->after('utm_campaign');
            $table->string('utm_content')->nullable()->after('utm_term');

            $table->index(['is_session_entry', 'created_at']);
            $table->index('acquisition_source');
            $table->index('utm_source');
        });
    }

    public function down(): void
    {
        Schema::table('analytics_events', function (Blueprint $table): void {
            $table->dropIndex(['is_session_entry', 'created_at']);
            $table->dropIndex(['acquisition_source']);
            $table->dropIndex(['utm_source']);
            $table->dropColumn([
                'is_session_entry',
                'landing_path',
                'acquisition_source',
                'utm_source',
                'utm_medium',
                'utm_campaign',
                'utm_term',
                'utm_content',
            ]);
        });
    }
};
