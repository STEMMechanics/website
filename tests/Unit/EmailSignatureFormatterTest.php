<?php

namespace Tests\Unit;

use App\Support\EmailSignatureFormatter;
use Tests\TestCase;

class EmailSignatureFormatterTest extends TestCase
{
    public function test_resolve_uses_first_name_when_sender_name_is_present(): void
    {
        config()->set('app.name', 'STEMMechanics');

        $this->assertSame('James / STEMMechanics', EmailSignatureFormatter::resolve('James Collins'));
    }

    public function test_resolve_falls_back_to_brand_when_sender_name_is_blank(): void
    {
        config()->set('app.name', 'STEMMechanics');

        $this->assertSame('STEMMechanics', EmailSignatureFormatter::resolve(''));
    }
}
