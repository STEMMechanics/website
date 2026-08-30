<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganisationBillingAddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_inherit_primary_organisation_billing_address(): void
    {
        $organisation = Organisation::factory()->create([
            'billing_address' => '119–145 Spence Street',
            'billing_city' => 'Cairns',
            'billing_state' => 'QLD',
            'billing_postcode' => '4870',
            'billing_country' => 'Australia',
        ]);
        $user = User::factory()->create([
            'primary_organisation_id' => $organisation->id,
            'use_organisation_billing_address' => true,
            'billing_address' => 'Personal address',
            'billing_city' => 'Other city',
        ]);

        $this->assertSame([
            'address' => '119–145 Spence Street',
            'address2' => '',
            'city' => 'Cairns',
            'state' => 'QLD',
            'postcode' => '4870',
            'country' => 'Australia',
        ], $user->resolvedBillingAddress());
    }

    public function test_user_can_override_primary_organisation_billing_address(): void
    {
        $organisation = Organisation::factory()->create([
            'billing_address' => 'Organisation address',
        ]);
        $user = User::factory()->create([
            'primary_organisation_id' => $organisation->id,
            'use_organisation_billing_address' => false,
            'billing_address' => 'Personal address',
            'billing_city' => 'Atherton',
            'billing_state' => 'QLD',
            'billing_postcode' => '4883',
            'billing_country' => 'Australia',
        ]);

        $this->assertSame('Personal address', $user->resolvedBillingAddress()['address']);
        $this->assertSame('Atherton', $user->resolvedBillingAddress()['city']);
    }

    public function test_user_can_inherit_primary_organisation_shipping_address_separately(): void
    {
        $organisation = Organisation::factory()->create([
            'billing_address' => 'Accounts office',
            'shipping_address' => 'PO Box 359',
            'shipping_city' => 'Cairns',
            'shipping_state' => 'QLD',
            'shipping_postcode' => '4870',
            'shipping_country' => 'Australia',
        ]);
        $user = User::factory()->create([
            'primary_organisation_id' => $organisation->id,
            'use_organisation_billing_address' => true,
            'use_organisation_shipping_address' => true,
        ]);

        $this->assertSame('Accounts office', $user->resolvedBillingAddress()['address']);
        $this->assertSame('PO Box 359', $user->resolvedShippingAddress()['address']);
    }
}
