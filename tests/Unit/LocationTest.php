<?php

namespace Tests\Unit;

use App\Models\Location;
use Tests\TestCase;

class LocationTest extends TestCase
{
    public function test_formatted_address_adds_missing_structured_fields(): void
    {
        $location = new Location([
            'address' => '14 Mill Street',
            'suburb' => 'Mossman',
            'state' => 'QLD',
            'postcode' => '4873',
        ]);

        $this->assertSame('14 Mill Street, Mossman, QLD 4873', $location->formattedAddress());
    }

    public function test_formatted_address_does_not_repeat_fields_already_in_address(): void
    {
        $location = new Location([
            'address' => 'Raintrees Shopping Centre, 63 Alfred St, Manunda QLD 4870',
            'suburb' => 'Manunda',
            'state' => 'QLD',
            'postcode' => '4870',
        ]);

        $this->assertSame($location->address, $location->formattedAddress());
    }

    public function test_suburb_detection_checks_the_name_and_address(): void
    {
        $inName = new Location(['name' => 'Mossman Library', 'suburb' => 'Mossman']);
        $inAddress = new Location(['name' => 'Herberton Library', 'address' => 'Main Street, Herberton QLD 4885', 'suburb' => 'Herberton']);
        $notIncluded = new Location(['name' => 'Adventure Centre', 'address' => '14 Mill Street', 'suburb' => 'Mossman']);

        $this->assertTrue($inName->suburbIsContainedInNameOrAddress());
        $this->assertTrue($inAddress->suburbIsContainedInNameOrAddress());
        $this->assertFalse($notIncluded->suburbIsContainedInNameOrAddress());
    }
}
