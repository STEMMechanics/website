<?php

namespace Tests\Feature;

use App\Models\SiteOption;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UsernameRestrictionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('site_options')) {
            Schema::create('site_options', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->unique();
                $table->text('value');
                $table->timestamps();
            });
        }

        SiteOption::query()->updateOrCreate(
            ['name' => 'users.restricted-usernames'],
            ['value' => 'stemcraft, admin']
        );
    }

    public function test_username_restrictions_match_whole_words_only(): void
    {
        $this->assertTrue(User::containsRestrictedUsernameTerm('admin-james'));
        $this->assertTrue(User::containsRestrictedUsernameTerm('jack_stemcraft'));
        $this->assertFalse(User::containsRestrictedUsernameTerm('madmin'));
        $this->assertFalse(User::containsRestrictedUsernameTerm('stemcrafty'));
    }
}
