<?php

namespace Tests\Unit;

use App\Support\ItemLabelFormatter;
use Tests\TestCase;

class ItemLabelFormatterTest extends TestCase
{
    public function test_it_pluralizes_before_a_trailing_parenthetical_suffix(): void
    {
        $this->assertSame('Coil Wires (40cm)', ItemLabelFormatter::forQuantity('Coil Wire (40cm)', 2));
        $this->assertSame('Coil Wire (40cm)', ItemLabelFormatter::forQuantity('Coil Wire (40cm)', 1));
    }
}
