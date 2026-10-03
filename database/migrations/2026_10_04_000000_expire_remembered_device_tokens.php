<?php

use App\Support\RememberedDeviceManager;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Existing trusted-device rows were nullable. Give each one an
        // absolute lifetime from its original creation time before the
        // application starts rejecting nullable remembered-device tokens.
        $tokens = DB::table('tokens')
            ->where('type', RememberedDeviceManager::DEVICE_TOKEN_TYPE)
            ->whereNull('expires_at')
            ->get(['id', 'created_at']);

        foreach ($tokens as $token) {
            $createdAt = $token->created_at !== null
                ? Carbon::parse((string) $token->created_at)
                : now();

            DB::table('tokens')
                ->where('id', $token->id)
                ->update(['expires_at' => $createdAt->addDays(RememberedDeviceManager::DEVICE_LIFETIME_DAYS)]);
        }
    }

    public function down(): void
    {
        // Do not reintroduce indefinite privileged-device trust on rollback.
    }
};
