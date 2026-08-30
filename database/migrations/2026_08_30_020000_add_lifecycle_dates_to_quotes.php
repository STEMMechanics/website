<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            $table->date('valid_until')->nullable()->after('quote_date');
            $table->date('follow_up_at')->nullable()->after('valid_until')->index();
        });

        DB::table('quotes')->whereNotNull('quote_date')->orderBy('id')->chunkById(200, function ($quotes): void {
            foreach ($quotes as $quote) {
                DB::table('quotes')->where('id', $quote->id)->update([
                    'valid_until' => Carbon::parse($quote->quote_date)->addDays(28)->toDateString(),
                    'follow_up_at' => Carbon::parse($quote->updated_at)->addDays(3)->toDateString(),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            $table->dropIndex(['follow_up_at']);
            $table->dropColumn(['valid_until', 'follow_up_at']);
        });
    }
};
