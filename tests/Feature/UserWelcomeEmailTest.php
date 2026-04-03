<?php

namespace Tests\Feature;

use App\Mail\UserWelcome;
use Tests\TestCase;

class UserWelcomeEmailTest extends TestCase
{
    public function test_welcome_email_highlights_discord_and_uses_community_image(): void
    {
        $mailable = new UserWelcome('subscriber@example.com');
        $mailable->withUnsubscribeLink('https://www.stemmechanics.com.au/unsubscribe/test-token');

        $rendered = $mailable->render();

        $this->assertStringContainsString('Welcome to STEMMechanics', $rendered);
        $this->assertStringContainsString('Join Discord', $rendered);
        $this->assertStringContainsString('community-discord.webp', $rendered);
        $this->assertStringContainsString('Workshop chat', $rendered);
        $this->assertStringContainsString('The quickest way to keep up', $rendered);
        $this->assertStringNotContainsString('sell your data', $rendered);
        $this->assertStringNotContainsString("can't wait to see you at one of our workshops", $rendered);
    }
}
