<?php

use App\Models\Location;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('locations')->where('name', Location::STEMCRAFT_NAME)->exists()) {
            DB::table('locations')->insert([
                'id' => (string) Str::uuid(),
                'name' => Location::STEMCRAFT_NAME,
                'address' => 'STEMCraft server',
                'address_url' => null,
                'url' => '/stemcraft/join',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('locations')
            ->where('name', Location::STEMCRAFT_NAME)
            ->where('address', 'STEMCraft server')
            ->where('url', '/stemcraft/join')
            ->delete();
    }
};
