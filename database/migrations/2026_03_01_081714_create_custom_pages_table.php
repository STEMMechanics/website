<?php

use Illuminate\Database\Migrations\Migration;
use App\Traits\UUID;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_pages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title', 200);
            $table->string('path', 200)->unique();
            $table->json('aliases')->nullable();
            $table->longText('content');
            $table->string('hero_media_name')->nullable();
            $table->boolean('show_mast')->default(true);
            $table->string('seo_title', 200)->nullable();
            $table->string('seo_description', 255)->nullable();
            $table->boolean('seo_noindex')->default(false);
            $table->boolean('is_published')->default(true);
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('hero_media_name')->references('name')->on('media')->nullOnDelete();
            $table->index('is_published');
        });

        DB::table('custom_pages')->insert([
            [
                'id' => (string) Str::uuid(),
                'title' => 'STEMCraft',
                'path' => '/stemcraft',
                'aliases' => json_encode(['/minecraft']),
                'content' => '<p>Welcome to <strong>STEMCraft</strong>, our family-friendly Minecraft space for creative play, collaborative builds, and STEM-focused challenges.</p><p>Use the links below to learn how to join, read the rules, and manage your linked account.</p><ul><li><a href="/stemcraft/join">How to Join</a></li><li><a href="/stemcraft/rules">Rules</a></li><li><a href="/stemcraft/punishments">Punishments</a></li></ul>',
                'show_mast' => true,
                'seo_title' => 'STEMCraft',
                'seo_description' => 'STEMCraft is our family-friendly Minecraft space for creative play, collaborative builds, and STEM-focused challenges.',
                'is_published' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'title' => 'Join STEMCraft',
                'path' => '/stemcraft/join',
                'aliases' => json_encode(['/minecraft/join']),
                'content' => '<p>Link your Minecraft account through your website account, wait for whitelist approval if needed, then connect using the server details provided by the STEMCraft team.</p><p>If you are new, contact us if you need help linking a Java or Bedrock account.</p>',
                'show_mast' => true,
                'seo_title' => 'Join STEMCraft',
                'seo_description' => 'Learn how to link your account and join the STEMCraft server.',
                'is_published' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'title' => 'STEMCraft Rules',
                'path' => '/stemcraft/rules',
                'aliases' => json_encode(['/minecraft/rules']),
                'content' => '<p>Be respectful, protect shared builds, use chat appropriately, and follow staff instructions. This page is intended to be edited in admin as your real rules evolve.</p>',
                'show_mast' => true,
                'seo_title' => 'STEMCraft Rules',
                'seo_description' => 'Read the server rules and expectations for STEMCraft players.',
                'is_published' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'title' => 'STEMCraft Punishments',
                'path' => '/stemcraft/punishments',
                'aliases' => null,
                'content' => '<p>This page lists moderation actions recorded by the server integration. Use the filters below to search by player, reason, type, or status.</p>',
                'show_mast' => true,
                'seo_title' => 'STEMCraft Punishments',
                'seo_description' => 'Search public STEMCraft moderation and punishment records.',
                'is_published' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_pages');
    }
};
